<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ServerClientResource;
use App\Models\Server;
use App\Services\NodeClient;
use Illuminate\Http\Request;

/**
 * What a customer's own tooling can do.
 *
 * Every answer here goes through ServerPolicy, the same class the web screens
 * and SFTP ask. The API must not become a third opinion about who may do what:
 * revoking somebody in the panel has to revoke them here in the same moment.
 */
class ServerController extends Controller
{
    public function index(Request $request)
    {
        $servers = $request->user()->accessibleServers()
            ->with(['node:id,name', 'allocation'])
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return ApiResource::list($servers, ServerClientResource::class);
    }

    public function show(Server $server)
    {
        $this->guard($server, 'settings.read');

        return (new ServerClientResource($server))->toArray(request());
    }

    public function resources(Server $server)
    {
        $this->guard($server, 'settings.read');

        $stats = NodeClient::for($server->node)->stats($server);

        return [
            'object' => 'stats',
            'attributes' => [
                'state' => $server->power_state,
                'cpu' => $stats['cpu'] ?? 0,
                'memory' => $stats['memory'] ?? 0,
                'disk' => $stats['disk'] ?? 0,
                'players' => $server->cached_players,
            ],
        ];
    }

    public function power(Request $request, Server $server)
    {
        $data = $request->validate([
            'signal' => ['required', 'in:start,stop,restart,kill'],
        ]);

        // Each signal is its own permission, because "may restart" and "may
        // kill" are genuinely different things to trust somebody with.
        $this->guard($server, 'control.'.($data['signal'] === 'kill' ? 'kill' : $data['signal']));

        if ($server->isSuspended()) {
            return response()->json(['message' => 'That server is suspended.'], 409);
        }

        $result = NodeClient::for($server->node)->power($server, $data['signal']);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'message' => 'The node did not accept that: '.($result['error'] ?? 'no reason given'),
            ], 502);
        }

        return response()->json(null, 204);
    }

    public function command(Request $request, Server $server)
    {
        $this->guard($server, 'control.console');

        $data = $request->validate(['command' => ['required', 'string', 'max:2000']]);

        if ($server->power_state !== 'running') {
            return response()->json(['message' => 'That server is not running.'], 409);
        }

        return NodeClient::for($server->node)->command($server, $data['command'])
            ? response()->json(null, 204)
            : response()->json(['message' => 'The node did not accept that command.'], 502);
    }

    private function guard(Server $server, string $permission): void
    {
        abort_unless(
            auth()->user()->can('check', [$server, $permission]),
            403,
            'Your access to this server does not include that.',
        );
    }
}
