<?php

namespace Tests\Feature;

use App\Jobs\InstallServer;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reinstall keeps the data directory or empties it first.
 *
 * Keep is what it always did and stays the default. Wipe is new plumbing all
 * the way to the node, and the flag going missing anywhere along the way would
 * look exactly like a successful reinstall that quietly kept everything, which
 * is the failure worth testing for.
 */
class ReinstallTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $this->admin = User::create([
            'name' => 'Allen', 'email' => 'admin@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);

        $location = Location::create(['short' => 'lax', 'name' => 'Los Angeles']);
        $node = Node::create([
            'name' => 'n1', 'location_id' => $location->id, 'scheme' => 'http',
            'fqdn' => '127.0.0.1', 'daemon_port' => 8942,
        ]);
        $game = Game::create(['name' => 'Minecraft', 'slug' => 'minecraft-'.Str::random(5)]);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'Paper', 'runtime' => 'docker', 'startup' => 'run',
        ]);

        $this->server = Server::create([
            'name' => 'Survival', 'owner_id' => $this->admin->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'docker', 'startup' => 'run',
            'memory' => 1024, 'disk' => 4096, 'cpu' => 100, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
        ]);
    }

    public function test_a_plain_reinstall_keeps_the_data_directory(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.servers.reinstall', $this->server))
            ->assertRedirect();

        Queue::assertPushed(InstallServer::class, fn (InstallServer $job) => $job->serverId === $this->server->id
            && $job->wipe === false);
    }

    public function test_asking_for_a_wipe_carries_all_the_way_to_the_job(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.servers.reinstall', $this->server), ['wipe' => 1])
            ->assertRedirect();

        Queue::assertPushed(InstallServer::class, fn (InstallServer $job) => $job->wipe === true);
    }

    /** The server goes back to installing either way, so the UI shows progress. */
    public function test_reinstalling_marks_the_server_as_installing(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.servers.reinstall', $this->server), ['wipe' => 1]);

        $fresh = $this->server->fresh();
        $this->assertSame('installing', $fresh->status);
        $this->assertNull($fresh->installed_at);
    }

    /** Which of the two happened has to be in the audit log, not just in a flash message. */
    public function test_the_audit_log_says_which_kind_of_reinstall_it_was(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.servers.reinstall', $this->server), ['wipe' => 1]);

        $entry = \App\Models\AuditLog::where('action', 'server.reinstall')->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('wiping', $entry->description);
    }

    public function test_a_client_cannot_wipe_somebody_elses_server(): void
    {
        $stranger = User::create([
            'name' => 'Stranger', 'email' => 'stranger@test.local',
            'password' => 'secret1234', 'role' => 'client',
        ]);

        $this->actingAs($stranger)
            ->post(route('admin.servers.reinstall', $this->server))
            ->assertForbidden();

        Queue::assertNotPushed(InstallServer::class);
    }
}
