<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\ApiToken;
use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ports, over the API.
 *
 * This was the worst gap: nothing could see which ports a node had free, so
 * anything managing capacity had to be a human on a screen.
 */
class AllocationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Node $node;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'setup_complete', 'value' => '1']);
        Cache::put('licence.status', [
            'state' => 'valid', 'ok' => true, 'licence' => ['edition' => 'plus'],
            'message' => 'test', 'checked_at' => now()->toIso8601String(),
        ], now()->addHour());

        $admin = User::create([
            'name' => 'Allen', 'email' => 'admin@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);
        $plain = 'gm_'.Str::random(48);
        ApiToken::create([
            'user_id' => $admin->id, 'name' => 'T',
            'token' => hash('sha256', $plain), 'scope' => 'application',
        ]);
        $this->token = $plain;

        $location = Location::create(['short' => 'lax', 'name' => 'Los Angeles']);
        $this->node = Node::create([
            'name' => 'n1', 'location_id' => $location->id, 'scheme' => 'http',
            'fqdn' => '127.0.0.1', 'daemon_port' => 8942,
        ]);
    }

    private function api(string $method, string $uri, array $body = [])
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)->json($method, $uri, $body);
    }

    private function base(): string
    {
        return '/api/application/nodes/'.$this->node->id.'/allocations';
    }

    public function test_a_range_of_ports_can_be_added(): void
    {
        $this->api('POST', $this->base(), [
            'ip' => '10.0.0.1', 'port_start' => 27015, 'port_end' => 27019,
        ])
            ->assertCreated()
            ->assertJsonPath('meta.created', 5)
            ->assertJsonPath('data.0.object', 'allocation');

        $this->assertSame(5, Allocation::where('node_id', $this->node->id)->count());
    }

    /** Asking twice adds the missing ports rather than failing on the first duplicate. */
    public function test_adding_an_overlapping_range_is_not_an_error(): void
    {
        $this->api('POST', $this->base(), ['ip' => '10.0.0.1', 'port_start' => 27015, 'port_end' => 27017]);

        $this->api('POST', $this->base(), ['ip' => '10.0.0.1', 'port_start' => 27016, 'port_end' => 27020])
            ->assertCreated()
            ->assertJsonPath('meta.created', 3)
            ->assertJsonPath('meta.existing', 2);

        $this->assertSame(6, Allocation::where('node_id', $this->node->id)->count());
    }

    /** The ceiling exists because somebody asked for the whole port range once. */
    public function test_an_enormous_range_is_refused(): void
    {
        $this->api('POST', $this->base(), ['ip' => '10.0.0.1', 'port_start' => 1024, 'port_end' => 65535])
            ->assertStatus(422);

        $this->assertSame(0, Allocation::count());
    }

    public function test_free_ports_can_be_asked_for_separately(): void
    {
        $this->api('POST', $this->base(), ['ip' => '10.0.0.1', 'port_start' => 27015, 'port_end' => 27018]);
        Allocation::where('port', 27015)->update(['server_id' => $this->server()->id]);

        $free = $this->api('GET', $this->base().'?free=1')->assertOk()->json('data');
        $this->assertCount(3, $free);

        $assigned = $this->api('GET', $this->base().'?assigned=1')->assertOk()->json('data');
        $this->assertCount(1, $assigned);
        $this->assertSame(27015, $assigned[0]['attributes']['port']);
    }

    public function test_a_free_port_can_be_removed(): void
    {
        $this->api('POST', $this->base(), ['ip' => '10.0.0.1', 'port_start' => 27015, 'port_end' => 27015]);
        $allocation = Allocation::first();

        $this->api('DELETE', $this->base().'/'.$allocation->id)->assertNoContent();

        $this->assertNull(Allocation::find($allocation->id));
    }

    /**
     * Deleting a port a server is on would take the row out from under a
     * running game, which then answers on a port the panel no longer believes
     * it owns.
     */
    public function test_a_port_a_server_is_using_cannot_be_removed(): void
    {
        $this->api('POST', $this->base(), ['ip' => '10.0.0.1', 'port_start' => 27015, 'port_end' => 27015]);
        $allocation = Allocation::first();
        $allocation->update(['server_id' => $this->server()->id]);

        $this->api('DELETE', $this->base().'/'.$allocation->id)
            ->assertStatus(409)
            ->assertJsonPath('server_id', $allocation->server_id);

        $this->assertNotNull(Allocation::find($allocation->id));
    }

    /** An id alone must not reach a port on a different machine. */
    public function test_an_allocation_on_another_node_is_not_reachable_through_this_one(): void
    {
        $other = Node::create([
            'name' => 'n2', 'location_id' => $this->node->location_id,
            'scheme' => 'http', 'fqdn' => '127.0.0.2', 'daemon_port' => 8942,
        ]);
        $foreign = Allocation::create(['node_id' => $other->id, 'ip' => '10.0.0.9', 'port' => 27015]);

        $this->api('GET', $this->base().'/'.$foreign->id)->assertNotFound();
        $this->api('DELETE', $this->base().'/'.$foreign->id)->assertNotFound();

        $this->assertNotNull(Allocation::find($foreign->id));
    }

    private function server(): Server
    {
        $owner = User::create([
            'name' => 'Owner', 'email' => 'o'.Str::random(4).'@test.local',
            'password' => 'secret1234', 'role' => 'client',
        ]);
        $game = Game::create(['name' => 'Minecraft', 'slug' => 'mc'.Str::random(4)]);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'Paper', 'runtime' => 'docker', 'startup' => 'run',
        ]);

        return Server::create([
            'name' => 'S', 'owner_id' => $owner->id, 'node_id' => $this->node->id,
            'template_id' => $template->id, 'runtime' => 'docker', 'startup' => 'run',
            'memory' => 512, 'disk' => 1024, 'cpu' => 50, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
        ]);
    }
}
