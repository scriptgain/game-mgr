<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Over-allocation is the panel promising more than the machine has.
 *
 * cpu_overallocate was stored and validated from the first import and read by
 * nothing, so the control on the node form changed a database row and had no
 * effect on anything. These pin all three so the next person can tell the
 * difference between a setting and a decoration.
 */
class NodeOverallocationTest extends TestCase
{
    use RefreshDatabase;

    private function node(array $overrides = []): Node
    {
        $location = Location::create(['short' => 'lax', 'name' => 'Los Angeles']);

        return Node::create(array_merge([
            'name' => 'n1',
            'location_id' => $location->id,
            'scheme' => 'http',
            'fqdn' => '127.0.0.1',
            'daemon_port' => 8942,
            'memory' => 1000,
            'disk' => 1000,
            'cpu' => 400,
        ], $overrides));
    }

    private function serverOn(Node $node, int $memory, int $disk, int $cpu): Server
    {
        $owner = User::create(['name' => 'O', 'email' => 'o'.$node->servers()->count().'@x.test', 'password' => 'x']);
        $game = \App\Models\Game::create(['name' => 'G', 'slug' => 'g'.uniqid()]);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'T'.uniqid(), 'runtime' => 'docker', 'startup' => 'x',
        ]);

        return Server::create([
            'name' => 'S', 'owner_id' => $owner->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'docker', 'startup' => 'x',
            'memory' => $memory, 'disk' => $disk, 'cpu' => $cpu, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
        ]);
    }

    public function test_capacity_is_the_machine_plus_the_percentage(): void
    {
        $node = $this->node(['memory_overallocate' => 50, 'disk_overallocate' => 10, 'cpu_overallocate' => 100]);

        $this->assertSame(1500, $node->memoryCapacity());
        $this->assertSame(1100, $node->diskCapacity());
        $this->assertSame(800, $node->cpuCapacity(), 'cpu_overallocate has to actually do something');
    }

    public function test_zero_over_allocation_means_exactly_what_the_machine_has(): void
    {
        $node = $this->node();

        $this->assertSame(1000, $node->memoryCapacity());
        $this->assertSame(400, $node->cpuCapacity());
    }

    public function test_a_server_that_fits_only_because_of_over_allocation_is_allowed(): void
    {
        $node = $this->node(['memory_overallocate' => 100]);
        $this->serverOn($node, 900, 100, 100);

        // 900 + 200 is past the machine's 1000 and inside the 2000 promised.
        $this->assertTrue($node->fresh()->hasRoomFor(200, 100));
    }

    public function test_cpu_can_refuse_a_placement(): void
    {
        $node = $this->node(['cpu_overallocate' => 0]);
        $this->serverOn($node, 100, 100, 300);

        $fresh = $node->fresh();
        $this->assertFalse($fresh->hasRoomFor(100, 100, 200), '300 + 200 is past a 400 budget');
        $this->assertTrue($fresh->hasRoomFor(100, 100, 100), '300 + 100 fits exactly');
    }

    public function test_a_node_that_does_not_track_cpu_never_refuses_on_it(): void
    {
        // cpu = 0 means "not tracked". Reading that as "no capacity" would
        // refuse every placement on every node that never set it.
        $node = $this->node(['cpu' => 0]);

        $this->assertTrue($node->hasRoomFor(100, 100, 800));
    }

    public function test_callers_that_do_not_mention_cpu_are_unaffected(): void
    {
        $node = $this->node();
        $this->serverOn($node, 100, 100, 400);

        $this->assertTrue($node->fresh()->hasRoomFor(100, 100), 'cpu is only checked when asked about');
    }

    public function test_pressure_is_reported_against_the_promised_figure(): void
    {
        $node = $this->node(['cpu_overallocate' => 100]);
        $this->serverOn($node, 100, 100, 400);

        // 400 of a promised 800, not of the machine's 400.
        $this->assertSame(50.0, $node->fresh()->cpuPressure());
    }
}
