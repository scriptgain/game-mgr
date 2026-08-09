<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Services\AllocationPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Ports are the one thing on this panel where being nearly right is the same as
 * being wrong. A Palworld server on 2456 is a healthy server nobody can reach,
 * and the only symptom the player gets is a connection timeout.
 *
 * So: the canonical port rule, the all-or-nothing reservation, the shared
 * address fallback, and the case where a template says nothing at all.
 */
class AllocationPlannerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        Setting::create(['key' => 'setup_complete', 'value' => '1']);

        // These tests are about port planning, not about editions, and several
        // of them deliberately use games the free tier does not cover. Running
        // them licensed keeps the edition gate from being the reason a planner
        // test fails, which would say nothing about the planner.
        Cache::put('licence.status', [
            'state' => 'valid', 'ok' => true, 'licence' => ['edition' => 'plus'],
            'message' => 'test', 'checked_at' => now()->toIso8601String(),
        ], now()->addHour());

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'secret1234', 'role' => 'admin',
        ]);
        $this->location = Location::create(['short' => 'test', 'name' => 'Test']);
    }

    // ---------------------------------------------------------------- setup

    private function node(string $name = 'node-a'): Node
    {
        return Node::create([
            'name' => $name, 'location_id' => $this->location->id, 'fqdn' => '127.0.0.1',
            'memory' => 65536, 'disk' => 512000, 'cpu' => 1600, 'runtimes' => ['docker', 'steamcmd'],
            'public' => true,
        ]);
    }

    /** Palworld's real set: 8211/udp game, 27015/udp query, 25575/tcp RCON. */
    private function palworld(): Template
    {
        $game = Game::firstOrCreate(['slug' => 'palworld'], ['name' => 'Palworld']);

        $template = Template::create([
            'game_id' => $game->id, 'name' => 'Palworld Dedicated', 'runtime' => 'steamcmd',
        ]);

        $template->ports()->createMany([
            ['role' => 'game', 'label' => 'Game Port', 'protocol' => 'udp', 'source' => 'fixed', 'port' => 8211, 'required' => true, 'sort' => 0],
            ['role' => 'query', 'label' => 'Query Port', 'protocol' => 'udp', 'source' => 'fixed', 'port' => 27015, 'required' => true, 'sort' => 1],
            ['role' => 'rcon', 'label' => 'RCON Port', 'protocol' => 'tcp', 'source' => 'fixed', 'port' => 25575, 'required' => true, 'sort' => 2],
        ]);

        $template->load('ports');
        $template->syncPortColumns();

        return $template->fresh('ports');
    }

    /** A template that never says what it listens on, as most imports will be. */
    private function silentTemplate(): Template
    {
        $game = Game::firstOrCreate(['slug' => 'mystery'], ['name' => 'Mystery Game']);

        return Template::create(['game_id' => $game->id, 'name' => 'Mystery', 'runtime' => 'docker']);
    }

    private function seedPool(Node $node, string $ip, array $ports): void
    {
        foreach ($ports as $port) {
            Allocation::create(['node_id' => $node->id, 'ip' => $ip, 'port' => $port]);
        }
    }

    private function createServer(Node $node, Template $template, string $name, array $extra = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.servers.store'), array_merge([
            'name' => $name,
            'owner_id' => $this->admin->id,
            'template_id' => $template->id,
            'node_id' => $node->id,
            'memory' => 2048, 'swap' => 0, 'disk' => 10240, 'io' => 500, 'cpu' => 200,
            'database_limit' => 1, 'allocation_limit' => 3, 'backup_limit' => 1,
        ], $extra));
    }

    /** ['role' => port] for every allocation the server holds. */
    private function portsOf(Server $server): array
    {
        $out = [];
        foreach ($server->allocations()->orderBy('port')->get() as $allocation) {
            foreach ($allocation->roles() as $role) {
                $out[$role] = (int) $allocation->port;
            }
        }
        ksort($out);

        return $out;
    }

    // ------------------------------------------------------ canonical ports

    /**
     * The rule this whole feature exists for. Nothing else is on the address,
     * so there is nothing to be in the way, so the game gets its real port.
     */
    public function test_a_dedicated_address_always_gets_the_canonical_ports(): void
    {
        $node = $this->node();
        $template = $this->palworld();

        // Deliberately seeded with none of Palworld's ports and one that sorts
        // first. The old allocator took the lowest free port and produced
        // exactly this bug: a Palworld server on Valheim's 2456.
        $this->seedPool($node, '10.0.0.5', [2456, 2457, 27016]);

        $this->createServer($node, $template, 'Pals')->assertRedirect();

        $server = Server::where('name', 'Pals')->firstOrFail();

        $this->assertSame(['game' => 8211, 'query' => 27015, 'rcon' => 25575], $this->portsOf($server));
        $this->assertSame(8211, (int) $server->allocation->port, 'the primary allocation is the game port');
        $this->assertSame('udp', $server->allocation->protocol);
    }

    /** Protocols come from the template, not from the port number. */
    public function test_each_reserved_port_records_its_own_protocol(): void
    {
        $node = $this->node();
        $template = $this->palworld();
        $this->seedPool($node, '10.0.0.5', [8211]);

        $this->createServer($node, $template, 'Pals');
        $server = Server::where('name', 'Pals')->firstOrFail();

        $protocols = $server->allocations()->pluck('protocol', 'port')->all();

        $this->assertSame('udp', $protocols[8211]);
        $this->assertSame('udp', $protocols[27015]);
        $this->assertSame('tcp', $protocols[25575], 'RCON is TCP even though the game port is UDP');
    }

    /**
     * A node with a spare address hands it over rather than stacking a second
     * server on top of the first, because that is what buying addresses is for.
     */
    public function test_a_second_server_prefers_an_empty_address_over_shifting(): void
    {
        $node = $this->node();
        $template = $this->palworld();
        $this->seedPool($node, '10.0.0.5', [8211]);
        $this->seedPool($node, '10.0.0.6', [8211]);

        $this->createServer($node, $template, 'First');
        $this->createServer($node, $template, 'Second');

        $first = Server::where('name', 'First')->firstOrFail();
        $second = Server::where('name', 'Second')->firstOrFail();

        $this->assertSame(8211, (int) $first->allocation->port);
        $this->assertSame(8211, (int) $second->allocation->port);
        $this->assertNotSame($first->allocation->ip, $second->allocation->ip);
    }

    // -------------------------------------------------- shared address rule

    /**
     * One address, two servers. The canonical port can only go to one of them,
     * so the whole set moves together and the operator is told by how much.
     */
    public function test_a_shared_address_shifts_the_whole_set_and_says_so(): void
    {
        $node = $this->node();
        $template = $this->palworld();
        $this->seedPool($node, '10.0.0.5', [8211]);

        $this->createServer($node, $template, 'First');
        $response = $this->createServer($node, $template, 'Second');

        $second = Server::where('name', 'Second')->firstOrFail();

        // Same shift on every port, so the layout the game documents survives.
        $this->assertSame(['game' => 8212, 'query' => 27016, 'rcon' => 25576], $this->portsOf($second));
        $this->assertSame('10.0.0.5', $second->allocation->ip);

        // Visible, not silent. A warning, not a success message.
        $response->assertSessionHas('warning');
        $this->assertStringContainsString('8211', session('warning'));
        $this->assertStringContainsString('8212', session('warning'));
    }

    /** The first server on an address still gets canonical ports, plainly. */
    public function test_the_first_server_on_a_shared_address_is_reported_as_canonical(): void
    {
        $node = $this->node();
        $template = $this->palworld();
        $this->seedPool($node, '10.0.0.5', [8211]);

        $this->createServer($node, $template, 'First')->assertSessionHas('status');
        $this->assertNull(session('warning'));
    }

    /** The client Network tab has to say it too, since that is where an owner looks. */
    public function test_the_network_tab_names_the_canonical_port_and_the_shift(): void
    {
        $node = $this->node();
        $template = $this->palworld();
        $this->seedPool($node, '10.0.0.5', [8211]);

        $this->createServer($node, $template, 'First');
        $this->createServer($node, $template, 'Second');

        $second = Server::where('name', 'Second')->firstOrFail();

        $this->actingAs($this->admin)->get(route('server.network', $second))
            ->assertOk()
            ->assertSee('Usual Port', false)
            ->assertSee('8212');

        $first = Server::where('name', 'First')->firstOrFail();
        $this->actingAs($this->admin)->get(route('server.network', $first))
            ->assertOk()
            ->assertSee('On The Real Port', false);
    }

    // ------------------------------------------------------ all or nothing

    /** A node with no addresses fails loudly rather than making a server nobody can reach. */
    public function test_a_set_that_cannot_be_placed_creates_nothing(): void
    {
        $node = $this->node();
        $template = $this->palworld();

        $this->createServer($node, $template, 'Nowhere')
            ->assertSessionHasErrors('allocation_id');

        $this->assertDatabaseMissing('servers', ['name' => 'Nowhere']);
        $this->assertSame(0, Allocation::whereNotNull('server_id')->count());
    }

    /**
     * The race. Between planning and reserving, somebody else takes one of the
     * ports. The reservation must abort whole: no server may end up holding its
     * game port and none of the ports that make it usable.
     */
    public function test_a_port_taken_mid_reservation_rolls_the_whole_set_back(): void
    {
        $node = $this->node();
        $template = $this->palworld();
        $this->seedPool($node, '10.0.0.5', [8211, 25575, 27015]);

        $planner = app(AllocationPlanner::class);
        $plan = $planner->plan($node, $template);

        $this->assertNotNull($plan);
        $this->assertSame(8211, $plan->gamePort());

        $victim = Server::create([
            'name' => 'Victim', 'owner_id' => $this->admin->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'steamcmd', 'memory' => 1024, 'disk' => 5120, 'cpu' => 100,
        ]);
        $thief = Server::create([
            'name' => 'Thief', 'owner_id' => $this->admin->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'steamcmd', 'memory' => 1024, 'disk' => 5120, 'cpu' => 100,
        ]);

        // The RCON port goes to somebody else after the plan was made.
        Allocation::where('node_id', $node->id)->where('port', 25575)
            ->update(['server_id' => $thief->id, 'role' => 'rcon']);

        try {
            $planner->reserve($victim, $plan);
            $this->fail('reserve() should have refused a set it could no longer take whole.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('25575', $e->getMessage());
        }

        // 8211 was claimed first and must have been given back.
        $this->assertSame(0, Allocation::where('server_id', $victim->id)->count(),
            'a failed reservation must leave the server holding nothing at all');
    }

    // ------------------------------------------------------- no port set

    /** Most imported templates say nothing about ports. They still work. */
    public function test_a_template_with_no_port_set_still_creates_a_server(): void
    {
        $node = $this->node();
        $template = $this->silentTemplate();
        $this->seedPool($node, '10.0.0.5', [27015, 27016]);

        $this->createServer($node, $template, 'Quiet')->assertRedirect();

        $server = Server::where('name', 'Quiet')->firstOrFail();

        $this->assertNotNull($server->allocation_id);
        $this->assertSame(27015, (int) $server->allocation->port);
        $this->assertSame(1, $server->allocations()->count(), 'one port, because the template asked for one');
    }

    /** And with no free ports either, which used to be allowed and still is. */
    public function test_a_template_with_no_port_set_and_no_free_ports_still_creates_a_server(): void
    {
        $node = $this->node();
        $template = $this->silentTemplate();

        $this->createServer($node, $template, 'Portless')->assertRedirect();

        $server = Server::where('name', 'Portless')->firstOrFail();
        $this->assertNull($server->allocation_id);
    }

    // -------------------------------------------------------- daemon shape

    /** The firewall side of the daemon is built against this exact shape. */
    public function test_the_daemon_payload_carries_every_port_with_its_protocol(): void
    {
        $node = $this->node();
        $template = $this->palworld();
        $this->seedPool($node, '10.0.0.5', [8211]);

        $this->createServer($node, $template, 'Pals');
        $server = Server::where('name', 'Pals')->with(['allocations', 'template'])->firstOrFail();

        $payload = $server->daemonPayload();

        $this->assertArrayHasKey('ports', $payload);
        $this->assertCount(3, $payload['ports']);

        // Game port first, and marked primary.
        $this->assertSame(8211, $payload['ports'][0]['port']);
        $this->assertTrue($payload['ports'][0]['primary']);
        $this->assertSame(['game'], $payload['ports'][0]['roles']);

        $byPort = collect($payload['ports'])->keyBy('port');
        $this->assertSame('tcp', $byPort[25575]['protocol']);
        $this->assertSame('udp', $byPort[27015]['protocol']);
        $this->assertSame(1, collect($payload['ports'])->where('primary', true)->count());

        // The old keys still mean what they meant.
        $this->assertSame(8211, $payload['port']);
        $this->assertSame('10.0.0.5', $payload['ip']);

        $this->assertSame('25575', $payload['environment']['SERVER_RCON_PORT']);
        $this->assertSame('27015', $payload['environment']['SERVER_QUERY_PORT']);
    }

    /**
     * Several roles on one number is the normal case, not an edge case: CS2
     * takes game, query and RCON all on 27015 and that is one allocation.
     */
    public function test_roles_that_share_a_port_collapse_into_one_allocation(): void
    {
        $node = $this->node();
        $game = Game::firstOrCreate(['slug' => 'cs2'], ['name' => 'Counter-Strike 2']);
        $template = Template::create(['game_id' => $game->id, 'name' => 'CS2', 'runtime' => 'steamcmd']);
        $template->ports()->createMany([
            ['role' => 'game', 'label' => 'Game Port', 'protocol' => 'udp', 'source' => 'fixed', 'port' => 27015, 'required' => true, 'sort' => 0],
            ['role' => 'query', 'label' => 'Query Port', 'protocol' => 'udp', 'source' => 'offset', 'port_offset' => 0, 'required' => true, 'sort' => 1],
            ['role' => 'rcon', 'label' => 'RCON Port', 'protocol' => 'tcp', 'source' => 'offset', 'port_offset' => 0, 'required' => true, 'sort' => 2],
        ]);
        $template->load('ports');
        $this->seedPool($node, '10.0.0.5', [27015]);

        $this->createServer($node, $template->fresh('ports'), 'Comp');
        $server = Server::where('name', 'Comp')->firstOrFail();

        $this->assertSame(1, $server->allocations()->count());
        $allocation = $server->allocations()->first();
        $this->assertSame(27015, (int) $allocation->port);
        $this->assertSame('both', $allocation->protocol, 'UDP game plus TCP RCON is a port open on both');
        $this->assertSame(['game', 'query', 'rcon'], $allocation->roles());
    }

    /** Deleting a server hands the ports back clean, roles included. */
    public function test_deleting_a_server_returns_every_port_to_the_pool(): void
    {
        $node = $this->node();
        $template = $this->palworld();
        $this->seedPool($node, '10.0.0.5', [8211]);

        $this->createServer($node, $template, 'Pals');
        $server = Server::where('name', 'Pals')->firstOrFail();

        $this->actingAs($this->admin)->delete(route('admin.servers.destroy', $server));

        $this->assertSame(0, Allocation::whereNotNull('server_id')->count());
        $this->assertSame(0, Allocation::whereNotNull('role')->count());
        $this->assertSame(3, Allocation::where('ip', '10.0.0.5')->count(), 'the ports stay in the node pool');
    }

    /** An address the planner needed but the pool never had is added to it. */
    public function test_missing_canonical_ports_are_added_to_the_node_pool(): void
    {
        $node = $this->node();
        $template = $this->palworld();
        $this->seedPool($node, '10.0.0.5', [8211]);

        $this->createServer($node, $template, 'Pals');

        $this->assertDatabaseHas('allocations', ['node_id' => $node->id, 'ip' => '10.0.0.5', 'port' => 25575]);
        $this->assertDatabaseHas('allocations', ['node_id' => $node->id, 'ip' => '10.0.0.5', 'port' => 27015]);
    }
}
