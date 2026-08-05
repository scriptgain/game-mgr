<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Models\WatchdogRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The watchdog must not fight the person using the panel.
 *
 * Pressing Stop leaves a server in exactly the state a crash does, so before
 * `stopped_intentionally` existed an active offline rule meant nobody could ever
 * turn their own server off: the next evaluation started it straight back up.
 */
class WatchdogTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'setup_complete', 'value' => '1']);
        Http::fake([
            '*' => Http::response(['ok' => true, 'state' => 'stopping'], 200),
        ]);

        $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@test.local', 'password' => 'secret1234', 'role' => 'client']);

        $location = Location::create(['short' => 'test', 'name' => 'Test']);
        $node = Node::create([
            'name' => 'test-node', 'location_id' => $location->id, 'fqdn' => '127.0.0.1',
            'memory' => 8192, 'disk' => 51200, 'cpu' => 400, 'runtimes' => ['docker'],
        ]);
        $game = Game::create(['name' => 'Test Game']);
        $template = Template::create(['game_id' => $game->id, 'name' => 'Test Template', 'runtime' => 'docker']);

        $this->server = Server::create([
            'name' => 'Watched', 'owner_id' => $this->owner->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'docker',
            'memory' => 1024, 'disk' => 5120, 'cpu' => 100, 'auto_restart' => true,
        ]);

        WatchdogRule::create([
            'name' => 'Restart when offline', 'trigger' => 'offline',
            'action' => 'restart', 'is_active' => true, 'grace_seconds' => 60,
        ]);
    }

    /** The whole live-state pipeline was inert while these were not fillable. */
    public function test_power_state_writes_actually_persist(): void
    {
        $this->server->update([
            'power_state' => 'running',
            'last_started_at' => now(),
            'cached_players' => 7,
            'cached_at' => now(),
        ]);

        $fresh = $this->server->fresh();

        $this->assertSame('running', $fresh->power_state);
        $this->assertNotNull($fresh->last_started_at);
        $this->assertSame(7, $fresh->cached_players);
    }

    public function test_stopping_a_server_through_the_panel_records_the_intent(): void
    {
        $this->actingAs($this->owner)
            ->post(route('server.power', $this->server), ['action' => 'stop'])
            ->assertRedirect();

        $this->assertTrue($this->server->fresh()->stopped_intentionally);
    }

    /** The bug: an offline rule used to undo the owner's own Stop. */
    public function test_the_watchdog_leaves_a_deliberately_stopped_server_alone(): void
    {
        $this->server->update([
            'power_state' => 'offline',
            'last_started_at' => now()->subHour(),
            'stopped_intentionally' => true,
        ]);

        $this->artisan('watchdog:evaluate')->expectsOutputToContain('0 watchdog rules fired.');

        $this->assertDatabaseCount('alerts', 0);
    }

    /** But a genuine crash still gets picked up. */
    public function test_the_watchdog_still_restarts_a_server_that_fell_over(): void
    {
        $this->server->update([
            'power_state' => 'offline',
            'last_started_at' => now()->subHour(),
            'stopped_intentionally' => false,
        ]);

        $this->artisan('watchdog:evaluate')->expectsOutputToContain('1 watchdog rule fired.');

        $this->assertDatabaseHas('alerts', ['server_id' => $this->server->id]);
    }

    /** Starting again clears the marker, so a later crash is still caught. */
    public function test_starting_clears_the_intent(): void
    {
        $this->server->update(['stopped_intentionally' => true]);

        $this->actingAs($this->owner)
            ->post(route('server.power', $this->server), ['action' => 'start'])
            ->assertRedirect();

        $this->assertFalse($this->server->fresh()->stopped_intentionally);
    }

    /** A watchdog-issued stop must not trip the watchdog's own offline rule. */
    public function test_a_watchdog_stop_does_not_trip_the_offline_rule(): void
    {
        WatchdogRule::query()->update(['action' => 'stop', 'trigger' => 'players_zero', 'grace_seconds' => 60]);
        $this->server->update([
            'power_state' => 'running',
            'last_started_at' => now()->subHour(),
            'cached_players' => 0,
            'cached_at' => now()->subMinutes(5),
        ]);

        $this->artisan('watchdog:evaluate');

        $this->assertTrue($this->server->fresh()->stopped_intentionally);
    }
}
