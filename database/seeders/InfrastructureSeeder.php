<?php

namespace Database\Seeders;

use App\Models\Allocation;
use App\Models\DatabaseHost;
use App\Models\Location;
use App\Models\Mount;
use App\Models\Node;
use App\Models\NodeMetric;
use App\Models\NotificationChannel;
use App\Models\RetentionPolicy;
use App\Models\WatchdogRule;
use App\Models\Webhook;
use Illuminate\Database\Seeder;

/**
 * Locations, nodes, allocations and the fleet-wide plumbing.
 *
 * Three nodes on purpose, one per runtime, plus one that is deliberately
 * offline and one behind NAT in reverse mode. A demo where everything is green
 * teaches you nothing about how the panel behaves when something breaks.
 */
class InfrastructureSeeder extends Seeder
{
    public function run(): void
    {
        $phoenix = Location::updateOrCreate(['short' => 'us-phx'], [
            'name' => 'Phoenix',
            'description' => 'Primary US region.',
            'flag' => '🇺🇸',
        ]);

        $frankfurt = Location::updateOrCreate(['short' => 'eu-fra'], [
            'name' => 'Frankfurt',
            'description' => 'European region, low latency to the UK and DACH.',
            'flag' => '🇩🇪',
        ]);

        $homelab = Location::updateOrCreate(['short' => 'homelab'], [
            'name' => 'Home Lab',
            'description' => 'Behind NAT on a domestic connection. Reverse mode only.',
            'flag' => '🏠',
        ]);

        $nodes = [
            [
                'name' => 'phx-docker-01',
                'location_id' => $phoenix->id,
                'description' => 'General purpose Docker node. Runs the containerised templates.',
                'connection_mode' => 'direct',
                'scheme' => 'http',
                'fqdn' => 'node',
                'daemon_port' => 8942,
                'memory' => 65536, 'memory_overallocate' => 20,
                'disk' => 1048576, 'disk_overallocate' => 10,
                'cpu' => 1600,
                'runtimes' => ['docker'],
                'public' => true,
                'enrolled_at' => now()->subDays(41),
                'last_seen_at' => now()->subSeconds(12),
                'reported_os' => 'Ubuntu 24.04.1 LTS',
                'reported_kernel' => '6.8.0-45-generic',
                'reported_arch' => 'x86_64',
                'reported_docker' => '27.3.1',
                'reported_agent_version' => '0.1.0',
                'reported_cpu_cores' => 16,
                'reported_memory' => 65536,
                'reported_disk' => 1048576,
                'ports' => [25565, 25600],
            ],
            [
                'name' => 'phx-steam-01',
                'location_id' => $phoenix->id,
                'description' => 'Bare metal. Runs SteamCMD templates natively, no container.',
                'connection_mode' => 'direct',
                'scheme' => 'http',
                'fqdn' => 'node',
                'daemon_port' => 8942,
                'memory' => 131072, 'memory_overallocate' => 0,
                'disk' => 3145728, 'disk_overallocate' => 0,
                'cpu' => 3200,
                'runtimes' => ['steamcmd', 'docker'],
                'public' => true,
                'enrolled_at' => now()->subDays(28),
                'last_seen_at' => now()->subSeconds(31),
                'reported_os' => 'Debian GNU/Linux 12 (bookworm)',
                'reported_kernel' => '6.1.0-25-amd64',
                'reported_arch' => 'x86_64',
                'reported_agent_version' => '0.1.0',
                'reported_cpu_cores' => 32,
                'reported_memory' => 131072,
                'reported_disk' => 3145728,
                'ports' => [27015, 28015],
            ],
            [
                'name' => 'fra-lgsm-01',
                'location_id' => $frankfurt->id,
                'description' => 'LinuxGSM node. Inherits the LinuxGSM catalogue of 130+ games.',
                'connection_mode' => 'direct',
                'scheme' => 'http',
                'fqdn' => 'node',
                'daemon_port' => 8942,
                'memory' => 98304, 'memory_overallocate' => 15,
                'disk' => 2097152, 'disk_overallocate' => 10,
                'cpu' => 2400,
                'runtimes' => ['linuxgsm', 'steamcmd'],
                'public' => true,
                'enrolled_at' => now()->subDays(15),
                'last_seen_at' => now()->subSeconds(48),
                'reported_os' => 'Ubuntu 22.04.5 LTS',
                'reported_kernel' => '5.15.0-122-generic',
                'reported_arch' => 'x86_64',
                'reported_agent_version' => '0.1.0',
                'reported_cpu_cores' => 24,
                'reported_memory' => 98304,
                'reported_disk' => 2097152,
                'ports' => [2456, 7777],
            ],
            [
                'name' => 'home-nuc-01',
                'location_id' => $homelab->id,
                'description' => 'A NUC on a domestic line with no port forwarding. Reverse mode is the only way this box is reachable at all, which is exactly the case Pterodactyl cannot cover.',
                'connection_mode' => 'reverse',
                'scheme' => 'http',
                'fqdn' => null,
                'daemon_port' => 8942,
                'memory' => 32768, 'memory_overallocate' => 25,
                'disk' => 953674, 'disk_overallocate' => 20,
                'cpu' => 800,
                'runtimes' => ['docker', 'linuxgsm'],
                'public' => false,
                'enrolled_at' => now()->subDays(6),
                'last_seen_at' => now()->subMinutes(9),
                'reported_os' => 'Debian GNU/Linux 12 (bookworm)',
                'reported_kernel' => '6.1.0-25-amd64',
                'reported_arch' => 'x86_64',
                'reported_agent_version' => '0.1.0',
                'reported_cpu_cores' => 8,
                'reported_memory' => 32768,
                'reported_disk' => 953674,
                'ports' => [25700],
            ],
            [
                'name' => 'fra-docker-02',
                'location_id' => $frankfurt->id,
                'description' => 'Draining ahead of a kernel upgrade. New placements are blocked.',
                'connection_mode' => 'direct',
                'scheme' => 'http',
                'fqdn' => 'node',
                'daemon_port' => 8942,
                'memory' => 49152, 'memory_overallocate' => 0,
                'disk' => 524288, 'disk_overallocate' => 0,
                'cpu' => 1200,
                'runtimes' => ['docker'],
                'public' => true,
                'maintenance_mode' => true,
                'enrolled_at' => now()->subDays(90),
                'last_seen_at' => now()->subSeconds(20),
                'reported_os' => 'Ubuntu 24.04.1 LTS',
                'reported_agent_version' => '0.1.0',
                'reported_cpu_cores' => 12,
                'reported_memory' => 49152,
                'reported_disk' => 524288,
                'ports' => [25800],
            ],
        ];

        foreach ($nodes as $data) {
            $ports = $data['ports'];
            unset($data['ports']);

            $node = Node::updateOrCreate(['name' => $data['name']], $data);
            $this->allocate($node, $ports);
            $this->sampleNode($node);
        }

        $this->plumbing();
    }

    /** 16 sequential ports from each base, which is how a real node is set up. */
    private function allocate(Node $node, array $bases): void
    {
        $ip = match ($node->location->short) {
            'eu-fra' => '167.235.14.'.(40 + $node->id),
            'homelab' => '10.0.10.'.(20 + $node->id),
            default => '45.77.126.'.(60 + $node->id),
        };

        foreach ($bases as $base) {
            for ($i = 0; $i < 16; $i++) {
                Allocation::firstOrCreate(
                    ['node_id' => $node->id, 'ip' => $ip, 'port' => $base + $i],
                    ['ip_alias' => $node->location->short.'.gamemgr.local'],
                );
            }
        }
    }

    /** Seven days of node history so the capacity charts are not empty. */
    private function sampleNode(Node $node): void
    {
        if ($node->metrics()->exists()) {
            return;
        }

        $rows = [];
        $seed = crc32($node->name);
        for ($i = 7 * 24; $i >= 0; $i--) {
            $at = now()->subHours($i);
            // A daily rhythm: quiet at 06:00, busy at 20:00, plus drift.
            $wave = (sin(($at->hour / 24) * 2 * M_PI - 1.6) + 1) / 2;
            $rows[] = [
                'node_id' => $node->id,
                'sampled_at' => $at,
                'cpu' => round(12 + $wave * 55 + ($seed % 7), 2),
                'memory' => (int) ($node->memory * (0.25 + $wave * 0.45)),
                'disk' => (int) ($node->disk * 0.31),
                'load' => round(1 + $wave * 6, 2),
                'server_count' => 0,
                'running_count' => 0,
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            NodeMetric::insert($chunk);
        }
    }

    /** Retention policies, mounts, database hosts, channels, watchdog, webhooks. */
    private function plumbing(): void
    {
        RetentionPolicy::updateOrCreate(['name' => 'Standard'], [
            'keep_last' => 3, 'keep_daily' => 7, 'keep_weekly' => 4, 'keep_monthly' => 6,
            'is_default' => true,
        ]);
        RetentionPolicy::updateOrCreate(['name' => 'Minimal'], [
            'keep_last' => 2, 'keep_daily' => 0, 'keep_weekly' => 0, 'keep_monthly' => 0,
        ]);
        RetentionPolicy::updateOrCreate(['name' => 'Long Archive'], [
            'keep_last' => 5, 'keep_daily' => 14, 'keep_weekly' => 8, 'keep_monthly' => 24,
        ]);

        $docker = Node::where('name', 'phx-docker-01')->first();

        $mount = Mount::updateOrCreate(['name' => 'Shared Map Pool'], [
            'description' => 'Read-only pool of curated maps, shared across every server on the node.',
            'source' => '/srv/gamemgr/maps',
            'target' => '/maps',
            'read_only' => true,
            'user_mountable' => true,
        ]);
        if ($docker) {
            $mount->nodes()->syncWithoutDetaching([$docker->id]);
        }

        DatabaseHost::updateOrCreate(['name' => 'phx-mysql-01'], [
            'host' => 'mariadb',
            'port' => 3306,
            'username' => 'root',
            'password' => 'gamemgr-dev-root',
            'linked_ip' => '45.77.126.61',
            'node_id' => $docker?->id,
            'max_databases' => 200,
        ]);

        $discord = NotificationChannel::updateOrCreate(['name' => 'Ops Discord'], [
            'type' => 'discord',
            'target' => 'https://discord.com/api/webhooks/000000000000000000/replace-me-with-a-real-webhook',
            'events' => ['server.crashed', 'node.offline', 'backup.failed', 'watchdog.fired', 'capacity.warning'],
            'is_active' => true,
        ]);

        NotificationChannel::updateOrCreate(['name' => 'On-Call Email'], [
            'type' => 'email',
            'target' => 'oncall@gamemgr.local',
            'events' => ['node.offline', 'watchdog.fired'],
            'is_active' => true,
        ]);

        WatchdogRule::updateOrCreate(['name' => 'Restart On Crash'], [
            'server_id' => null,
            'trigger' => 'crash',
            'threshold' => 0,
            'grace_seconds' => 30,
            'action' => 'restart',
            'channels' => [$discord->id],
            'is_active' => true,
        ]);

        WatchdogRule::updateOrCreate(['name' => 'World Corruption Watch'], [
            'server_id' => null,
            'trigger' => 'log_pattern',
            'pattern' => '(ChunkHolder|Chunk file at .* is missing|corrupt)',
            'grace_seconds' => 0,
            'action' => 'alert',
            'channels' => [$discord->id],
            'is_active' => true,
        ]);

        WatchdogRule::updateOrCreate(['name' => 'Idle Server Shutdown'], [
            'server_id' => null,
            'trigger' => 'players_zero',
            'threshold' => 0,
            'grace_seconds' => 7200,
            'action' => 'stop',
            'channels' => [],
            'is_active' => false,
        ]);

        Webhook::updateOrCreate(['name' => 'Billing Sync'], [
            'url' => 'https://example.invalid/hooks/gamemgr',
            'events' => ['server.installed', 'server.suspended', 'server.deleted'],
            'is_active' => false,
        ]);
    }
}
