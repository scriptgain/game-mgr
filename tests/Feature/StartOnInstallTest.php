<?php

namespace Tests\Feature;

use App\Jobs\InstallServer;
use App\Models\AuditLog;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A server comes up once its files have arrived.
 *
 * A fifteen gigabyte install used to finish into silence, leaving the server
 * offline until somebody noticed. For an unattended install that means until
 * the morning.
 */
class StartOnInstallTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $admin = User::create([
            'name' => 'Allen', 'email' => 'admin@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);
        $location = Location::create(['short' => 'ewr', 'name' => 'New Jersey']);
        $node = Node::create([
            'name' => 'n1', 'location_id' => $location->id, 'scheme' => 'http',
            'fqdn' => '127.0.0.1', 'daemon_port' => 8942,
        ]);
        $game = Game::create(['name' => 'TF2', 'slug' => 'tf2-'.Str::random(5)]);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'TF2 Dedicated', 'runtime' => 'steamcmd', 'startup' => 'run',
        ]);

        $this->server = Server::create([
            'name' => 'TF2', 'owner_id' => $admin->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'steamcmd', 'startup' => 'run',
            'memory' => 2048, 'disk' => 20480, 'cpu' => 200, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
            'status' => 'installing',
        ]);
    }

    /** The stream that a finished install produces, then the power call's answer. */
    private function fakeInstallThenStart(): void
    {
        Http::fakeSequence()
            ->push("event: message\ndata: [gamemgr] install complete\n\n", 200)
            ->push(['ok' => true, 'state' => 'starting'], 200);
    }

    public function test_a_finished_install_starts_the_server(): void
    {
        $this->fakeInstallThenStart();

        (new InstallServer($this->server->id))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/power') && $request['action'] === 'start');

        $fresh = $this->server->fresh();
        $this->assertSame('starting', $fresh->power_state);
        $this->assertNotNull($fresh->last_started_at);
    }

    public function test_the_toggle_is_respected(): void
    {
        $this->server->forceFill(['start_on_install' => false])->save();
        Http::fake(['*' => Http::response("event: message\ndata: [gamemgr] install complete\n\n", 200)]);

        (new InstallServer($this->server->id))->handle();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/power'));
        $this->assertNull($this->server->fresh()->last_started_at);
    }

    /** A failed install has no files, so starting it would only produce a crash. */
    public function test_a_failed_install_does_not_start_anything(): void
    {
        Http::fake(['*' => Http::response("event: error\ndata: steamcmd failed\n\n", 200)]);

        (new InstallServer($this->server->id))->handle();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/power'));
        $this->assertSame('install_failed', $this->server->fresh()->status);
    }

    /**
     * Reinstalling a server somebody had deliberately stopped must not start it
     * behind their back. They stopped it for a reason and a reinstall is not
     * permission to undo that.
     */
    public function test_a_reinstall_leaves_a_deliberately_stopped_server_down(): void
    {
        $this->server->forceFill([
            'stopped_intentionally' => true, 'power_state' => 'offline',
        ])->save();
        Http::fake(['*' => Http::response("event: message\ndata: [gamemgr] install complete\n\n", 200)]);

        (new InstallServer($this->server->id))->handle();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/power'));
    }

    /** A reinstall of a server that WAS running should end with it running again. */
    public function test_a_reinstall_of_a_running_server_brings_it_back(): void
    {
        $this->server->forceFill([
            'stopped_intentionally' => true, 'power_state' => 'running',
        ])->save();
        $this->fakeInstallThenStart();

        (new InstallServer($this->server->id))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/power') && $request['action'] === 'start');
    }

    /**
     * The intent flag has to be cleared, or the watchdog reads the server as
     * deliberately down and declines to restart it if it crashes on first boot,
     * which is exactly when a new server is most likely to crash.
     */
    public function test_starting_clears_the_stopped_on_purpose_flag(): void
    {
        $this->server->forceFill(['stopped_intentionally' => true, 'power_state' => 'running'])->save();
        $this->fakeInstallThenStart();

        (new InstallServer($this->server->id))->handle();

        $this->assertFalse((bool) $this->server->fresh()->stopped_intentionally);
    }

    /**
     * A node that will not start the server has not failed the install. The
     * files are there and the server is usable, so it must not be marked broken.
     */
    public function test_a_start_that_fails_does_not_fail_the_install(): void
    {
        // node.fake is on in the test environment and turns an unreachable node
        // into an optimistic "it started", which is right for a demo instance
        // and useless here: with it left on this test passes without ever
        // exercising the failure it is named after.
        config(['node.fake' => false]);

        Http::fakeSequence()
            ->push("event: message\ndata: [gamemgr] install complete\n\n", 200)
            ->push(['error' => 'node refused'], 500);

        (new InstallServer($this->server->id))->handle();

        $fresh = $this->server->fresh();
        $this->assertNull($fresh->status, 'the install itself succeeded');
        $this->assertNotNull($fresh->installed_at);
        $this->assertNotNull(AuditLog::where('action', 'server.autostart_failed')->first());
    }
}
