<?php

namespace Tests\Feature;

use App\Jobs\MigrateServer;
use App\Models\Allocation;
use App\Models\ApiToken;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Services\ServerMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Moving a server between nodes.
 *
 * The refusals matter more than the happy path here. A migration that goes
 * ahead when it should not have is one that copies a world while the game is
 * writing to it, or lands on a node with nowhere to put it, and the failure is
 * discovered by a customer rather than by us.
 */
class MigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Node $source;

    private Node $target;

    private Server $server;

    /** Http::fake merges stubs rather than replacing them, so a second fake in
     *  a test never wins against the one in setUp. This is how a test says the
     *  restore should fail. */
    private bool $restoreFails = false;

    private bool $fetchFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::fake(function ($request) {
            if ($this->restoreFails && str_contains($request->url(), '/restore')) {
                return Http::response('', 500);
            }
            if ($this->fetchFails && str_contains($request->url(), '/backups/fetch')) {
                return Http::response('', 502);
            }

            return Http::response(['ok' => true, 'bytes' => 100, 'checksum' => 'x'], 200);
        });
        Setting::create(['key' => 'setup_complete', 'value' => '1']);
        Cache::put('licence.status', [
            'state' => 'valid', 'ok' => true, 'licence' => ['edition' => 'plus'],
            'message' => 't', 'checked_at' => now()->toIso8601String(),
        ], now()->addHour());

        $admin = User::create([
            'name' => 'A', 'email' => 'a@test.local', 'password' => 'secret1234', 'role' => 'admin',
        ]);
        $plain = 'gm_'.Str::random(48);
        ApiToken::create([
            'user_id' => $admin->id, 'name' => 'T',
            'token' => hash('sha256', $plain), 'scope' => 'application',
        ]);
        $this->token = $plain;

        $location = Location::create(['short' => 'lax', 'name' => 'LA']);
        $this->source = $this->node('source', '10.0.0.1', $location);
        $this->target = $this->node('target', '10.0.0.2', $location);

        $game = Game::create(['name' => 'MC', 'slug' => 'mc']);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'Paper', 'runtime' => 'docker',
            'startup' => 'run', 'default_port' => 25565, 'default_protocol' => 'tcp',
        ]);

        $this->server = Server::create([
            'name' => 'S', 'owner_id' => $admin->id, 'node_id' => $this->source->id,
            'template_id' => $template->id, 'runtime' => 'docker', 'startup' => 'run',
            'memory' => 1024, 'disk' => 2048, 'cpu' => 100, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
            'power_state' => 'offline',
        ]);

        $held = Allocation::where('node_id', $this->source->id)->first();
        $held->update(['server_id' => $this->server->id, 'role' => 'game']);
        $this->server->forceFill(['allocation_id' => $held->id])->save();
    }

    private function node(string $name, string $ip, Location $location): Node
    {
        $node = Node::create([
            'name' => $name, 'location_id' => $location->id, 'scheme' => 'http',
            'fqdn' => $ip, 'daemon_port' => 8942, 'public' => true,
            'memory' => 32000, 'disk' => 200000, 'cpu' => 800,
        ]);
        $node->forceFill(['daemon_secret' => 's'])->save();

        foreach (range(25565, 25570) as $port) {
            Allocation::create(['node_id' => $node->id, 'ip' => $ip, 'port' => $port]);
        }

        return $node;
    }

    private function api(string $method, string $uri, array $body = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)->json($method, $uri, $body);
    }

    private function transfer(array $body = null)
    {
        return $this->api('POST', '/api/application/servers/'.$this->server->uuid_short.'/transfer',
            $body ?? ['node_id' => $this->target->id]);
    }

    public function test_a_stopped_server_can_be_queued_for_a_move(): void
    {
        $this->transfer()
            ->assertStatus(202)
            ->assertJsonPath('attributes.to', 'target')
            ->assertJsonPath('attributes.status', 'queued');

        Queue::assertPushed(MigrateServer::class, fn ($job) => $job->serverId === $this->server->id
            && $job->targetNodeId === $this->target->id);
    }

    /**
     * The most important refusal. Copying a world while the game is writing to
     * it produces an archive of a half-written save, and the customer finds out
     * when they load it.
     */
    public function test_a_running_server_is_refused(): void
    {
        $this->server->forceFill(['power_state' => 'running'])->save();

        $this->transfer()->assertStatus(409);
        Queue::assertNotPushed(MigrateServer::class);
    }

    public function test_moving_to_the_node_it_is_already_on_is_refused(): void
    {
        $this->transfer(['node_id' => $this->source->id])->assertStatus(409);
        Queue::assertNotPushed(MigrateServer::class);
    }

    public function test_a_node_that_cannot_run_the_runtime_is_refused(): void
    {
        $this->target->forceFill(['runtimes' => ['linuxgsm']])->save();

        $this->transfer()->assertStatus(409);
        Queue::assertNotPushed(MigrateServer::class);
    }

    public function test_a_node_with_no_room_is_refused(): void
    {
        $this->target->forceFill(['memory' => 128, 'disk' => 128])->save();

        $this->transfer()->assertStatus(409);
        Queue::assertNotPushed(MigrateServer::class);
    }

    public function test_a_node_with_no_free_ports_is_refused(): void
    {
        Allocation::where('node_id', $this->target->id)->delete();

        $this->transfer()->assertStatus(409);
        Queue::assertNotPushed(MigrateServer::class);
    }

    public function test_a_suspended_server_is_refused(): void
    {
        $this->server->forceFill(['status' => 'suspended'])->save();

        $this->transfer()->assertStatus(409);
        Queue::assertNotPushed(MigrateServer::class);
    }

    /**
     * The one that must never be destructive: a restore that fails leaves the
     * server on the node it started on, still holding its own ports.
     */
    public function test_a_failed_restore_leaves_the_server_where_it_was(): void
    {
        // The backup succeeds and the restore does not.
        $this->restoreFails = true;

        $originalAllocation = $this->server->allocation_id;

        $ok = app(ServerMigrator::class)->migrate($this->server->fresh(), $this->target);
        $this->assertFalse($ok);

        $fresh = $this->server->fresh();
        $this->assertSame($this->source->id, $fresh->node_id, 'the server moved despite the restore failing');
        $this->assertSame($originalAllocation, $fresh->allocation_id, 'the server lost its original address');
        $this->assertNull($fresh->status, 'the server was left stuck in a migrating state');
        $this->assertSame($this->server->id, Allocation::find($originalAllocation)->server_id,
            'the original port was freed even though the migration failed');
    }

    /**
     * The step that was missing entirely: Backup writes the archive on the
     * source node's disk and Restore reads it from the target's, so without a
     * transfer between them the target opens a path that never existed there.
     *
     * The earlier tests all passed with it missing, because a faked HTTP layer
     * answers "ok" to a restore that would have failed on a real node. This one
     * asserts the fetch actually happens, and in the right order.
     */
    public function test_the_archive_is_carried_to_the_target_before_the_restore(): void
    {
        app(ServerMigrator::class)->migrate($this->server->fresh(), $this->target);

        $calls = [];
        Http::assertSent(function ($request) use (&$calls) {
            $calls[] = $request->url();

            return true;
        });

        $fetch = null;
        $restore = null;
        foreach ($calls as $i => $url) {
            if ($fetch === null && str_contains($url, '/backups/fetch')) {
                $fetch = $i;
            }
            if ($restore === null && str_contains($url, '/restore')) {
                $restore = $i;
            }
        }

        $this->assertNotNull($fetch, 'the archive was never carried to the target node');
        $this->assertNotNull($restore, 'nothing was restored');
        $this->assertLessThan($restore, $fetch, 'the restore ran before the archive had arrived');
    }

    /**
     * The row must still be on the SOURCE when the archive is fetched.
     *
     * This is the one the old ordering got wrong, and it cost a real two-node
     * test to find. `backups.download` resolves which node to stream from off
     * the server row itself, so moving the row before the fetch pointed that
     * link at the target: the target asked the panel for the archive, the panel
     * turned round and asked the target, and the fetch failed with the archive
     * still on the source.
     *
     * Faking HTTP hides it completely, because a fake answers every URL the
     * same way, and so does a dev stack where two node rows share one daemon.
     * The only thing that catches it without two real machines is asserting
     * WHERE the row is at the moment the fetch goes out.
     */
    public function test_the_row_is_still_on_the_source_when_the_archive_is_fetched(): void
    {
        $nodeAtFetch = null;
        $id = $this->server->id;

        Http::fake(function ($request) use (&$nodeAtFetch, $id) {
            if (str_contains($request->url(), '/backups/fetch')) {
                $nodeAtFetch = Server::withoutGlobalScopes()->find($id)?->node_id;
            }

            return Http::response(['ok' => true, 'bytes' => 100, 'checksum' => 'x'], 200);
        });

        $this->assertTrue(app(ServerMigrator::class)->migrate($this->server->fresh(), $this->target));

        $this->assertNotNull($nodeAtFetch, 'the archive was never fetched');
        $this->assertSame(
            $this->source->id,
            $nodeAtFetch,
            'the server row had already been moved to the target when the archive was fetched, so the signed '.
            'download link pointed at the node that does not have the archive yet',
        );

        // And it did land on the target once the copy was done.
        $this->assertSame($this->target->id, $this->server->fresh()->node_id);
    }

    /** A transfer that fails leaves the server where it was, like every other failure. */
    public function test_a_failed_transfer_leaves_the_server_where_it_was(): void
    {
        $this->fetchFails = true;

        $this->assertFalse(app(ServerMigrator::class)->migrate($this->server->fresh(), $this->target));

        $fresh = $this->server->fresh();
        $this->assertSame($this->source->id, $fresh->node_id);
        $this->assertNull($fresh->status);
    }
}
