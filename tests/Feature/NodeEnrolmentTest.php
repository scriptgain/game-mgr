<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Node;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * What a node reports about itself has to survive enrolment.
 *
 * Found on the first real install rather than in the dev stack: the daemon
 * probed all three runtimes, said so in its logs, sent the list, and the panel
 * threw it away and kept the model's ['docker'] default. Node::supports() gates
 * server creation on that array, so a box with working SteamCMD and LinuxGSM
 * would silently offer nothing but the Docker templates.
 */
class NodeEnrolmentTest extends TestCase
{
    use RefreshDatabase;

    private function node(array $overrides = []): Node
    {
        $location = Location::create(['short' => 'lax', 'name' => 'Los Angeles']);

        $node = Node::create(array_merge([
            'name' => 'gamemgr001',
            'location_id' => $location->id,
            'scheme' => 'http',
            'fqdn' => '127.0.0.1',
            'daemon_port' => 8942,
        ], $overrides));

        // Not fillable, and deliberately so: the token is minted by the panel,
        // never posted in. forceFill is what the controller does too.
        $node->forceFill([
            'enrol_token' => Str::random(48),
            'enrol_token_expires_at' => now()->addHour(),
        ])->save();

        return $node;
    }

    public function test_the_runtimes_a_node_reports_are_what_the_panel_stores(): void
    {
        $node = $this->node();
        $this->assertSame(['docker'], $node->runtimes, 'the model still defaults to docker');

        $this->postJson('/api/node/enrol', [
            'token' => $node->enrol_token,
            'runtimes' => ['docker', 'steamcmd', 'linuxgsm'],
        ])->assertOk();

        $node->refresh();

        $this->assertEqualsCanonicalizing(['docker', 'steamcmd', 'linuxgsm'], $node->runtimes);
        $this->assertTrue($node->supports('steamcmd'));
        $this->assertTrue($node->supports('linuxgsm'));
    }

    public function test_a_daemon_cannot_invent_a_runtime_the_panel_has_no_driver_for(): void
    {
        $node = $this->node();

        $this->postJson('/api/node/enrol', [
            'token' => $node->enrol_token,
            'runtimes' => ['docker', 'kubernetes', 'wine'],
        ])->assertOk();

        $this->assertSame(['docker'], $node->refresh()->runtimes);
    }

    public function test_an_agent_that_reports_no_runtimes_leaves_the_operators_choice_alone(): void
    {
        $node = $this->node(['runtimes' => ['steamcmd']]);

        $this->postJson('/api/node/enrol', ['token' => $node->enrol_token])->assertOk();

        $this->assertSame(['steamcmd'], $node->refresh()->runtimes);
    }

    public function test_a_spent_token_cannot_be_replayed(): void
    {
        $node = $this->node();
        $token = $node->enrol_token;

        $this->postJson('/api/node/enrol', ['token' => $token, 'runtimes' => ['docker']])->assertOk();
        $this->postJson('/api/node/enrol', ['token' => $token, 'runtimes' => ['docker']])->assertStatus(401);
    }
}
