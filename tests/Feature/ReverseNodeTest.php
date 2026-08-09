<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Node;
use App\Models\NodeCall;
use App\Services\Node\DirectTransport;
use App\Services\Node\ReverseTransport;
use App\Services\Node\Transport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reverse mode: a node behind NAT that the panel cannot dial.
 *
 * The panel had offered this in the node form since the schema was written and
 * had no transport behind it, so choosing it produced a node the panel could
 * not control and whose calls fell back to dialling 127.0.0.1, which is the
 * panel's own machine. These tests pin the parts of the replacement that are
 * not obvious by reading it: who may claim a call, what happens when two
 * pollers race, and that a node cannot see another node's work.
 */
class ReverseNodeTest extends TestCase
{
    use RefreshDatabase;

    private Node $node;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $location = Location::create(['short' => 'lax', 'name' => 'Los Angeles']);

        $this->node = Node::create([
            'name' => 'nuc-at-home',
            'location_id' => $location->id,
            'connection_mode' => 'reverse',
            'scheme' => 'https',
            'daemon_port' => 8942,
        ]);

        $this->token = Str::random(64);
        $this->node->forceFill(['daemon_token' => hash('sha256', $this->token)])->save();
    }

    private function poll(array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/node/calls?wait=0', $headers ?: $this->auth());
    }

    private function auth(?string $token = null): array
    {
        return ['Authorization' => 'Bearer '.($token ?? $this->token)];
    }

    private function park(?Node $node = null): NodeCall
    {
        return NodeCall::park($node ?? $this->node, 'POST', '/api/servers/x/power', [], '{"action":"start"}', 30);
    }

    public function test_the_transport_is_chosen_by_the_connection_mode(): void
    {
        $this->assertInstanceOf(ReverseTransport::class, Transport::for($this->node));

        $this->node->forceFill(['connection_mode' => 'direct', 'fqdn' => 'node.example.com'])->save();

        $this->assertInstanceOf(DirectTransport::class, Transport::for($this->node->fresh()));
    }

    public function test_a_daemon_receives_the_call_parked_for_it(): void
    {
        $call = $this->park();

        $this->poll()
            ->assertOk()
            ->assertJsonPath('call.uuid', $call->uuid)
            ->assertJsonPath('call.method', 'POST')
            ->assertJsonPath('call.path', '/api/servers/x/power')
            ->assertJsonPath('call.body', '{"action":"start"}');

        $this->assertSame('claimed', $call->fresh()->state);
    }

    /**
     * The one that would be silent. The daemon runs several pollers, and a
     * read-then-write claim hands the same work to two of them: "stop" twice is
     * survivable, "install" twice is a wiped world directory.
     */
    public function test_a_call_can_only_be_claimed_once(): void
    {
        $this->park();

        $first = NodeCall::claimFor($this->node);
        $second = NodeCall::claimFor($this->node);

        $this->assertNotNull($first);
        $this->assertNull($second, 'a claimed call must not be handed out again');
    }

    public function test_a_node_never_sees_another_nodes_work(): void
    {
        $other = Node::create([
            'name' => 'somebody-elses-box',
            'location_id' => $this->node->location_id,
            'connection_mode' => 'reverse',
        ]);
        $theirs = $this->park($other);

        // Nothing for this node, even though a call exists.
        $this->poll()->assertNoContent();

        // And it cannot answer one it was never given. Scoped to the node, so
        // "wrong node" and "no such call" are the same 404: one node must not
        // be able to probe for another's.
        $this->postJson("/api/node/calls/{$theirs->uuid}/result", ['status' => 200], $this->auth())
            ->assertNotFound();

        $this->assertSame('pending', $theirs->fresh()->state);
    }

    public function test_a_result_completes_the_call_and_cannot_be_sent_twice(): void
    {
        $call = $this->park();
        NodeCall::claimFor($this->node);

        $this->postJson("/api/node/calls/{$call->uuid}/result", [
            'status' => 200,
            'body' => base64_encode('{"ok":true}'),
            'encoding' => 'base64',
        ], $this->auth())->assertOk();

        $call->refresh();
        $this->assertSame('done', $call->state);
        $this->assertSame('{"ok":true}', $call->response_body);

        $this->postJson("/api/node/calls/{$call->uuid}/result", ['status' => 500], $this->auth())
            ->assertStatus(409);

        $this->assertSame(200, (int) $call->fresh()->response_status);
    }

    public function test_an_answer_that_is_already_waiting_is_returned_without_a_sleep(): void
    {
        // The daemon answered before the panel started waiting, which is what
        // happens whenever a poll is already parked. The transport must check
        // once before sleeping or every call pays 100ms it did not need to.
        $this->node->forceFill(['daemon_secret' => 'x'])->save();

        NodeCall::creating(function (NodeCall $call) {
            $call->state = 'done';
            $call->response_status = 200;
            $call->response_body = '{"pong":true}';
            $call->completed_at = now();
        });

        $started = microtime(true);
        $response = (new ReverseTransport($this->node))->send('GET', '/healthz');
        $elapsed = microtime(true) - $started;

        NodeCall::flushEventListeners();

        $this->assertNotNull($response);
        $this->assertTrue($response->ok());
        $this->assertSame(['pong' => true], $response->json());
        $this->assertLessThan(0.5, $elapsed, 'an answer already on the row must not wait for the next poll tick');
    }

    public function test_a_call_nobody_answers_reads_as_unreachable(): void
    {
        // Null, the same answer a dead direct node gives, because every caller
        // in the panel already knows how to render that and none of them know
        // what a reverse node is.
        $response = (new ReverseTransport($this->node))->send('GET', '/healthz', timeout: 1);

        $this->assertNull($response);
        $this->assertSame('pending', NodeCall::first()->state);
    }

    public function test_progress_is_appended_in_order_and_refused_once_the_call_is_done(): void
    {
        $call = $this->park();

        $this->postJson("/api/node/calls/{$call->uuid}/progress", [
            'lines' => [['event' => 'message', 'data' => 'downloading']],
        ], $this->auth())->assertOk();

        $this->postJson("/api/node/calls/{$call->uuid}/progress", [
            'lines' => [['event' => 'message', 'data' => 'verifying']],
        ], $this->auth())->assertOk();

        $this->assertSame(
            "message\tdownloading\nmessage\tverifying\n",
            $call->fresh()->progress,
        );

        $this->postJson("/api/node/calls/{$call->uuid}/result", ['status' => 200], $this->auth())->assertOk();

        $this->postJson("/api/node/calls/{$call->uuid}/progress", [
            'lines' => [['event' => 'message', 'data' => 'too late']],
        ], $this->auth())->assertStatus(409);
    }

    public function test_an_upload_too_large_for_the_tunnel_is_refused_with_a_reason(): void
    {
        config(['node.reverse.max_payload' => 16]);

        $response = (new ReverseTransport($this->node))->sendRaw(
            'POST',
            '/api/servers/x/files/upload',
            [],
            str_repeat('a', 64),
            64,
        );

        $this->assertSame(413, $response->status);
        // In a unit that is not zero: a cap under a megabyte used to round to
        // "0 MB", which reads as a refusal for no reason.
        $this->assertStringContainsString('capped at 1 KB', $response->json()['error']);
        // Refused here, not parked: nobody should be woken up to be told no.
        $this->assertSame(0, NodeCall::count());
    }

    public function test_polling_counts_as_a_heartbeat(): void
    {
        $this->assertNull($this->node->last_seen_at);

        $this->poll()->assertNoContent();

        $this->assertNotNull($this->node->fresh()->last_seen_at,
            'a node holding a poll is alive; without this nodes:poll would flip a busy reverse node offline');
    }

    public function test_the_heartbeat_tells_the_node_which_mode_it_is_in(): void
    {
        $this->postJson('/api/node/heartbeat', ['cpu' => 1], $this->auth())
            ->assertOk()
            ->assertJsonPath('reverse', true);

        $this->node->forceFill(['connection_mode' => 'direct'])->save();

        $this->postJson('/api/node/heartbeat', ['cpu' => 1], $this->auth())
            ->assertJsonPath('reverse', false);
    }

    public function test_an_unauthenticated_daemon_gets_nothing(): void
    {
        $this->park();

        $this->getJson('/api/node/calls?wait=0')->assertUnauthorized();
        $this->getJson('/api/node/calls?wait=0', $this->auth('not-a-real-token'))->assertUnauthorized();
    }

    /**
     * Migration streams the archive out of the source node through the panel,
     * which a reverse node cannot do. Refused before anything moves, rather
     * than at the fetch with the server already marked migrating.
     */
    public function test_a_server_cannot_be_migrated_off_a_reverse_node(): void
    {
        $target = Node::create([
            'name' => 'reachable-01',
            'location_id' => $this->node->location_id,
            'connection_mode' => 'direct',
            'fqdn' => 'node.example.com',
            'memory' => 65536, 'disk' => 500000, 'cpu' => 1600,
            'runtimes' => ['docker'],
        ]);

        $owner = \App\Models\User::create([
            'name' => 'A Person', 'email' => 'nat@test.local',
            'password' => 'correct-horse', 'role' => 'client',
        ]);
        $game = \App\Models\Game::create(['name' => 'Minecraft', 'slug' => 'minecraft-'.Str::random(6)]);
        $template = \App\Models\Template::create([
            'game_id' => $game->id, 'name' => 'Paper', 'runtime' => 'docker', 'startup' => 'java -jar server.jar',
        ]);
        $server = \App\Models\Server::create([
            'name' => 'Stuck At Home',
            'owner_id' => $owner->id,
            'node_id' => $this->node->id,
            'template_id' => $template->id,
            'runtime' => 'docker',
            'startup' => 'java -jar server.jar',
            'memory' => 2048, 'disk' => 8192, 'cpu' => 200, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
            // Stopped, so the refusal under test is the one that comes back
            // rather than "stop the server first", which is checked earlier.
            'power_state' => 'offline',
        ]);

        $reason = app(\App\Services\ServerMigrator::class)->reasonItCannotRun($server, $target);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('connects out to the panel', $reason);
    }

    public function test_the_prune_drops_finished_and_abandoned_calls_but_not_live_ones(): void
    {
        config(['node.reverse.prune_after' => 60]);

        $live = $this->park();
        $done = $this->park();
        $done->forceFill(['state' => 'done', 'completed_at' => now()->subHour()])->save();
        $abandoned = $this->park();
        $abandoned->forceFill(['deadline_at' => now()->subHour()])->save();

        NodeCall::prune();

        $this->assertNotNull($live->fresh());
        $this->assertNull($done->fresh());
        $this->assertNull($abandoned->fresh());
    }
}
