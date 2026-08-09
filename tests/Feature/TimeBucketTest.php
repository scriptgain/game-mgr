<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Support\TimeBucket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Both the dashboard and the metrics endpoint group samples by hour in SQL, and
 * both used MySQL's DATE_FORMAT directly. config/database.php defaults to
 * sqlite, so a default install answered the dashboard with a 500 reading
 * "no such function: DATE_FORMAT".
 *
 * It survived because nothing in the suite ever opened either page. These do.
 */
class TimeBucketTest extends TestCase
{
    use RefreshDatabase;

    private function server(): Server
    {
        $location = Location::create(['short' => 'lax', 'name' => 'Los Angeles']);
        $node = Node::create([
            'name' => 'n1', 'location_id' => $location->id, 'scheme' => 'http',
            'fqdn' => '127.0.0.1', 'daemon_port' => 8942,
        ]);
        $owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'secret1234', 'role' => 'admin',
        ]);
        $game = \App\Models\Game::create(['name' => 'Minecraft', 'slug' => 'minecraft-'.Str::random(5)]);
        $template = Template::create([
            'game_id' => $game->id, 'name' => 'Paper', 'runtime' => 'docker', 'startup' => 'run',
        ]);

        return Server::create([
            'name' => 'Survival', 'owner_id' => $owner->id, 'node_id' => $node->id,
            'template_id' => $template->id, 'runtime' => 'docker', 'startup' => 'run',
            'memory' => 1024, 'disk' => 4096, 'cpu' => 100, 'swap' => 0, 'io' => 500,
            'database_limit' => 0, 'allocation_limit' => 0, 'backup_limit' => 0,
        ]);
    }

    private function samples(Server $server): void
    {
        foreach ([5, 65, 125] as $minutesAgo) {
            ServerMetric::create([
                'server_id' => $server->id,
                'sampled_at' => now()->subMinutes($minutesAgo),
                'cpu' => 10, 'memory' => 512, 'disk' => 1024, 'players' => 3, 'tick_rate' => 20,
            ]);
        }
    }

    public function test_the_dashboard_groups_metrics_on_this_database(): void
    {
        Setting::create(['key' => 'setup_complete', 'value' => '1']);
        $server = $this->server();
        $this->samples($server);

        $this->actingAs($server->owner)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_the_metrics_endpoint_groups_on_this_database(): void
    {
        Setting::create(['key' => 'setup_complete', 'value' => '1']);
        $server = $this->server();
        $this->samples($server);

        // Every bucket size, because they take different branches.
        foreach ([1, 24, 720] as $hours) {
            $this->actingAs($server->owner)
                ->getJson(route('server.metrics.series', [$server, 'hours' => $hours]))
                ->assertOk()
                ->assertJsonStructure(['labels', 'cpu', 'memory', 'players']);
        }
    }

    /** Every engine has to produce the same string, or a label changes shape with the database. */
    public function test_a_bucket_is_a_timestamp_string_whatever_the_engine(): void
    {
        $server = $this->server();
        $this->samples($server);

        $buckets = ServerMetric::query()
            ->selectRaw(TimeBucket::expression('sampled_at', TimeBucket::HOUR).' as bucket')
            ->pluck('bucket');

        $this->assertNotEmpty($buckets);
        foreach ($buckets as $bucket) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:00:00$/', (string) $bucket);
        }
    }

    public function test_the_column_name_is_never_taken_on_trust(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TimeBucket::expression('sampled_at) --', TimeBucket::HOUR);
    }
}
