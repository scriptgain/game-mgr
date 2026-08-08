<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerMetric;
use Illuminate\Support\Facades\DB;

/**
 * Two dashboards behind one route. An admin gets the fleet: node health,
 * capacity headroom, servers by state, open alerts. A client gets their own
 * servers and nothing else, because a client has no fleet to see.
 */
class DashboardController extends Controller
{
    public function __invoke()
    {
        return auth()->user()->isAdmin() ? $this->admin() : $this->client();
    }

    private function admin()
    {
        $servers = Server::with(['node.location', 'template.game', 'owner', 'allocation'])->get();
        $nodes = Node::with('location')->withCount('servers')->get();

        // Player count across the fleet over the last 24 hours, bucketed hourly.
        // One query rather than one per server: this is the sort of page that
        // quietly becomes a hundred queries if you let it.
        $playerSeries = ServerMetric::query()
            ->where('sampled_at', '>=', now()->subDay())
            ->selectRaw('DATE_FORMAT(sampled_at, "%Y-%m-%d %H:00:00") as bucket, SUM(players) as players')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('players', 'bucket');

        return view('dashboard.admin', [
            'title' => 'Dashboard',
            'servers' => $servers,
            'nodes' => $nodes,
            'alerts' => Alert::with(['server', 'node'])->whereNull('acknowledged_at')->latest('id')->limit(6)->get(),
            // A short summary, not a paginated list. Paging a dashboard card to
            // five pages of history buries the four things above it, and the
            // audit log already exists for reading the whole thing properly.
            'activity' => AuditLog::with('user')->latest('id')->limit(6)->get(),
            'playerSeries' => $playerSeries,
            'counts' => [
                'total' => $servers->count(),
                'running' => $servers->where('status', null)->where('power_state', 'running')->count(),
                'offline' => $servers->where('status', null)->where('power_state', '!=', 'running')->count(),
                'attention' => $servers->whereNotNull('status')->count(),
                'players' => (int) $servers->sum('cached_players'),
                'nodesOnline' => $nodes->filter->isOnline()->count(),
                'nodesTotal' => $nodes->count(),
            ],
            'runtimes' => $servers->groupBy('runtime')->map->count(),
        ]);
    }

    private function client()
    {
        $user = auth()->user();

        $servers = $user->accessibleServers()
            ->with(['node.location', 'template.game', 'allocation'])
            ->orderBy('name')
            ->get();

        // Which of these are shared rather than owned, so the list can say so.
        $sharedIds = DB::table('subusers')->where('user_id', $user->id)->pluck('server_id')->all();

        return view('dashboard.client', [
            'title' => 'My Servers',
            'servers' => $servers,
            'sharedIds' => $sharedIds,
        ]);
    }
}
