<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Subuser;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The permission model is the one place where a quiet mistake becomes somebody
 * else's server, so it gets tested rather than eyeballed.
 */
class ServerPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    private User $owner;

    private User $subuser;

    private User $stranger;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@test.local', 'password' => 'secret1234', 'role' => 'client']);
        $this->subuser = User::create(['name' => 'Sub', 'email' => 'sub@test.local', 'password' => 'secret1234', 'role' => 'client']);
        $this->stranger = User::create(['name' => 'Stranger', 'email' => 'stranger@test.local', 'password' => 'secret1234', 'role' => 'client']);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret1234', 'role' => 'admin']);

        $location = Location::create(['short' => 'test', 'name' => 'Test']);
        $node = Node::create([
            'name' => 'test-node', 'location_id' => $location->id, 'fqdn' => '127.0.0.1',
            'memory' => 8192, 'disk' => 51200, 'cpu' => 400, 'runtimes' => ['docker'],
        ]);
        $game = Game::create(['name' => 'Test Game']);
        $template = Template::create(['game_id' => $game->id, 'name' => 'Test Template', 'runtime' => 'docker']);

        $this->server = Server::create([
            'name' => 'Test Server', 'owner_id' => $this->owner->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'docker', 'memory' => 1024, 'disk' => 5120, 'cpu' => 100,
        ]);

        $allocation = Allocation::create(['node_id' => $node->id, 'ip' => '127.0.0.1', 'port' => 25565, 'server_id' => $this->server->id]);
        $this->server->update(['allocation_id' => $allocation->id]);

        Subuser::create([
            'server_id' => $this->server->id,
            'user_id' => $this->subuser->id,
            'permissions' => ['control.console', 'file.read'],
        ]);
    }

    public function test_owner_reaches_every_tab(): void
    {
        $this->actingAs($this->owner)
            ->get(route('server.console', $this->server))
            ->assertOk();

        $this->actingAs($this->owner)
            ->get(route('server.network', $this->server))
            ->assertOk();
    }

    public function test_subuser_only_reaches_granted_tabs(): void
    {
        $this->actingAs($this->subuser)
            ->get(route('server.console', $this->server))
            ->assertOk();

        $this->actingAs($this->subuser)
            ->get(route('server.files', $this->server))
            ->assertOk();

        // Not granted: must be refused, not merely hidden from the menu.
        $this->actingAs($this->subuser)
            ->get(route('server.network', $this->server))
            ->assertForbidden();

        $this->actingAs($this->subuser)
            ->get(route('server.users', $this->server))
            ->assertForbidden();
    }

    public function test_stranger_reaches_nothing(): void
    {
        $this->actingAs($this->stranger)
            ->get(route('server.console', $this->server))
            ->assertForbidden();
    }

    public function test_admin_reaches_everything(): void
    {
        $this->actingAs($this->admin)
            ->get(route('server.console', $this->server))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.servers.index'))
            ->assertOk();
    }

    public function test_client_cannot_reach_the_admin_area(): void
    {
        $this->actingAs($this->owner)
            ->get(route('admin.servers.index'))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->get(route('admin.nodes.index'))
            ->assertForbidden();
    }

    /**
     * A suspended server is read-only for everyone but an admin. Without this
     * an owner could keep restarting a server that was suspended for a reason.
     */
    public function test_suspension_blocks_writes_but_not_reads(): void
    {
        $this->server->update(['status' => 'suspended']);

        $this->actingAs($this->owner)
            ->get(route('server.files', $this->server))
            ->assertOk();

        $this->actingAs($this->owner)
            ->post(route('server.power', $this->server), ['action' => 'start'])
            ->assertForbidden();
    }
}
