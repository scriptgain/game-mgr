<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\Template;
use App\Models\User;
use App\Services\Dns\DnsException;
use App\Services\Dns\NameAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The label is the only part of a connection name a customer influences, so it
 * is the only part that can collide, be rude, or shadow something the operator
 * needs for itself.
 */
class NameAllocatorTest extends TestCase
{
    use RefreshDatabase;

    private NameAllocator $allocator;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'domains.enabled' => true,
            'domains.provider' => 'null',
            'domains.zone' => 'play.example.com',
        ]);

        $this->allocator = new NameAllocator;
    }

    public function test_a_server_name_becomes_a_usable_dns_label(): void
    {
        $this->assertSame('alpha', $this->allocator->slug('Alpha'));
        $this->assertSame('allens-palworld', $this->allocator->slug("Allen's Palworld!"));
        $this->assertSame('smp', $this->allocator->slug('  --SMP--  '));
        $this->assertSame('a-b', $this->allocator->slug('a   b'));

        // Capped, and never left ending on a hyphen by the cut.
        $long = $this->allocator->slug(str_repeat('verylongname', 5));
        $this->assertLessThanOrEqual(24, mb_strlen($long));
        $this->assertSame(trim($long, '-'), $long);

        // A name with nothing usable in it still has to produce something.
        $this->assertSame('server', $this->allocator->slug('!!! ???'));
    }

    public function test_a_taken_label_is_suffixed_rather_than_reused(): void
    {
        $node = $this->node('lax1');

        $this->assertSame('alpha', $this->allocator->allocate('Alpha', $node));

        $this->server($node, 'Alpha');
        $this->assertSame('alpha-2', $this->allocator->allocate('Alpha', $node));

        $this->server($node, 'Alpha');
        $this->assertSame('alpha-3', $this->allocator->allocate('Alpha', $node));
    }

    public function test_the_same_label_is_free_again_on_another_node(): void
    {
        $lax = $this->node('lax1');
        $fra = $this->node('fra1', 'fra-docker-01');

        $this->server($lax, 'Alpha');

        // alpha.lax1 and alpha.fra1 are different names and neither shadows the
        // other, so scoping uniqueness any wider would refuse a name for no
        // reason.
        $this->assertSame('alpha', $this->allocator->allocate('Alpha', $fra));
    }

    public function test_reserved_labels_are_refused(): void
    {
        $node = $this->node('lax1');

        foreach (['www', 'panel', 'api', 'status'] as $reserved) {
            $this->assertTrue($this->allocator->reserved($reserved), $reserved.' should be reserved');
            // Server creation is never allowed to fail over this, so the label
            // is moved out of the way rather than rejected.
            $this->assertSame($reserved.'-2', $this->allocator->allocate($reserved, $node));
        }
    }

    public function test_a_node_label_is_reserved_so_a_server_cannot_shadow_a_node(): void
    {
        $node = $this->node('lax1');

        $this->assertTrue($this->allocator->reserved('lax1'));
        $this->assertSame('lax1-2', $this->allocator->allocate('lax1', $node));
    }

    public function test_a_label_typed_by_a_human_is_rejected_out_loud(): void
    {
        $this->node('lax1');

        $this->expectException(DnsException::class);
        $this->allocator->assertAllowed('panel');
    }

    public function test_the_full_name_needs_a_zone_and_a_node_label(): void
    {
        $labelled = $this->node('lax1');
        $unlabelled = $this->node(null, 'fra-docker-01');

        $this->assertSame('alpha.lax1.play.example.com', $this->allocator->connectName('alpha', $labelled));
        $this->assertSame('*.lax1.play.example.com', $this->allocator->wildcardName($labelled));

        $this->assertNull($this->allocator->connectName('alpha', $unlabelled));
        $this->assertNull($this->allocator->wildcardName($unlabelled));

        config(['domains.zone' => '']);
        $this->assertNull($this->allocator->connectName('alpha', $labelled));
    }

    // ------------------------------------------------------------- fixtures

    private function node(?string $label, string $name = 'lax-docker-01'): Node
    {
        $location = Location::firstOrCreate(['short' => 'lax'], ['name' => 'Los Angeles']);

        return Node::create([
            'name' => $name,
            'location_id' => $location->id,
            'scheme' => 'http',
            'fqdn' => '45.63.49.152',
            'daemon_port' => 8942,
            'dns_label' => $label,
        ]);
    }

    private function server(Node $node, string $name): Server
    {
        $owner = User::firstOrCreate(
            ['email' => 'owner@example.com'],
            ['name' => 'Owner', 'password' => 'x', 'role' => 'user'],
        );

        $game = Game::firstOrCreate(['name' => 'Palworld'], ['description' => 'Test game']);

        $template = Template::firstOrCreate(
            ['name' => 'Palworld'],
            ['game_id' => $game->id, 'runtime' => 'docker', 'startup' => './server'],
        );

        return Server::create([
            'name' => $name,
            'owner_id' => $owner->id,
            'node_id' => $node->id,
            'template_id' => $template->id,
            'runtime' => 'docker',
            'startup' => './server',
            'memory' => 2048,
            'disk' => 10240,
            'cpu' => 100,
        ]);
    }
}
