<?php

namespace App\Http\Controllers\Client;

use App\Models\Server;
use App\Models\ServerMetric;
use Illuminate\Http\Request;

/**
 * Historical metrics. Pterodactyl throws its live stats away the moment the
 * websocket closes, so "was it laggy last Tuesday" has no answer there. Here
 * every sample is kept for as long as the retention setting says.
 */
class MetricController extends ServerController
{
    private const RANGES = [
        '6h' => ['label' => 'Last 6 Hours', 'hours' => 6],
        '24h' => ['label' => 'Last 24 Hours', 'hours' => 24],
        '7d' => ['label' => 'Last 7 Days', 'hours' => 168],
        '30d' => ['label' => 'Last 30 Days', 'hours' => 720],
    ];

    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'control.console');

        $range = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : '24h';

        return view('server.metrics', [
            'title' => $server->name.' Metrics',
            'server' => $server->load('node'),
            'range' => $range,
            'ranges' => self::RANGES,
            'summary' => $this->summary($server, self::RANGES[$range]['hours']),
        ]);
    }

    /** JSON the chart renderer in public/js reads. */
    public function series(Request $request, Server $server)
    {
        $this->guard($server, 'control.console');

        $range = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : '24h';
        $hours = self::RANGES[$range]['hours'];

        // Long ranges get bucketed rather than returned raw: 30 days of minute
        // samples is 43,000 points, and no chart is improved by 43,000 points.
        $bucket = match (true) {
            $hours <= 6 => '%Y-%m-%d %H:%i:00',
            $hours <= 24 => '%Y-%m-%d %H:00:00',
            $hours <= 168 => '%Y-%m-%d %H:00:00',
            default => '%Y-%m-%d 00:00:00',
        };

        $rows = ServerMetric::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', now()->subHours($hours))
            ->selectRaw("DATE_FORMAT(sampled_at, ?) as bucket, AVG(cpu) as cpu, AVG(memory) as memory, MAX(disk) as disk, AVG(players) as players, AVG(tick_rate) as tick_rate", [$bucket])
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        return response()->json([
            'labels' => $rows->pluck('bucket'),
            'cpu' => $rows->map(fn ($r) => round((float) $r->cpu, 2)),
            'memory' => $rows->map(fn ($r) => (int) $r->memory),
            'disk' => $rows->map(fn ($r) => (int) $r->disk),
            'players' => $rows->map(fn ($r) => round((float) $r->players, 1)),
            'tick_rate' => $rows->map(fn ($r) => round((float) $r->tick_rate, 2)),
            'limits' => ['memory' => $server->memory, 'disk' => $server->disk, 'cpu' => $server->cpu],
        ]);
    }

    private function summary(Server $server, int $hours): array
    {
        $row = ServerMetric::where('server_id', $server->id)
            ->where('sampled_at', '>=', now()->subHours($hours))
            ->selectRaw('AVG(cpu) as avg_cpu, MAX(cpu) as max_cpu, AVG(memory) as avg_mem, MAX(memory) as max_mem, MAX(players) as peak_players, AVG(players) as avg_players, MIN(tick_rate) as worst_tick')
            ->first();

        return [
            'avg_cpu' => round((float) ($row->avg_cpu ?? 0), 1),
            'max_cpu' => round((float) ($row->max_cpu ?? 0), 1),
            'avg_mem' => (int) ($row->avg_mem ?? 0),
            'max_mem' => (int) ($row->max_mem ?? 0),
            'peak_players' => (int) ($row->peak_players ?? 0),
            'avg_players' => round((float) ($row->avg_players ?? 0), 1),
            'worst_tick' => round((float) ($row->worst_tick ?? 0), 2),
        ];
    }
}
