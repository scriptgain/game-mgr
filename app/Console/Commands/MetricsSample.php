<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\ServerMetric;
use App\Services\NodeClient;
use Illuminate\Console\Command;

/**
 * Take one resource sample per server and store it.
 *
 * This command is the whole reason GameMGR can answer "was it laggy last
 * Tuesday". Pterodactyl streams the same numbers to a browser and then throws
 * them away, so the question has no answer there at all.
 */
class MetricsSample extends Command
{
    protected $signature = 'metrics:sample';

    protected $description = 'Sample every running server into the metric history';

    public function handle(): int
    {
        $servers = Server::with('node', 'allocation', 'template')->whereNull('status')->get();
        $taken = 0;

        foreach ($servers as $server) {
            if (! $server->node?->isOnline()) {
                continue;
            }

            $stats = NodeClient::for($server->node)->stats($server);
            if (($stats['state'] ?? 'offline') !== 'running') {
                continue;
            }

            ServerMetric::create([
                'server_id' => $server->id,
                'sampled_at' => now(),
                'cpu' => (float) ($stats['cpu'] ?? 0),
                'memory' => (int) ($stats['memory_mib'] ?? 0),
                'disk' => (int) ($stats['disk_mib'] ?? 0),
                'net_rx' => (int) ($stats['net_rx_bytes'] ?? 0),
                'net_tx' => (int) ($stats['net_tx_bytes'] ?? 0),
                'players' => (int) ($stats['players'] ?? 0),
                'tick_rate' => $stats['tick_rate'] ?? null,
            ]);

            $server->forceFill([
                'power_state' => 'running',
                'cached_cpu' => (float) ($stats['cpu'] ?? 0),
                'cached_memory' => (int) ($stats['memory_mib'] ?? 0),
                'cached_disk' => (int) ($stats['disk_mib'] ?? 0),
                'cached_players' => (int) ($stats['players'] ?? 0),
                'cached_max_players' => (int) ($stats['max_players'] ?? 0),
                'cached_at' => now(),
            ])->save();

            $taken++;
        }

        $this->info($taken.' of '.$servers->count().' servers sampled.');

        return self::SUCCESS;
    }
}
