<?php

namespace App\Http\Controllers\Client;

use App\Models\Server;
use App\Services\NodeClient;
use Illuminate\Http\Request;

/**
 * The console. This is the screen people live on, so it carries the power
 * buttons, the live log, the resource gauges and the connect address.
 *
 * Output arrives over Server-Sent Events straight from the node daemon rather
 * than being polled through the panel, so the panel is not in the path of every
 * log line.
 */
class ConsoleController extends ServerController
{
    public function show(Server $server)
    {
        $this->guard($server, 'control.console');

        $server->load(['node.location', 'template.game', 'allocation', 'allocations']);

        return view('server.console', [
            'title' => $server->name,
            'server' => $server,
            'backlog' => NodeClient::for($server->node)->logs($server, 150),
            'streamUrl' => NodeClient::for($server->node)->streamUrl($server),
        ]);
    }

    public function power(Request $request, Server $server)
    {
        $action = $request->input('action');
        abort_unless(in_array($action, ['start', 'stop', 'restart', 'kill'], true), 422);

        $this->guard($server, match ($action) {
            'start' => 'control.start',
            'restart' => 'control.restart',
            default => 'control.stop',
        });

        if (! $server->isControllable()) {
            return back()->with('error', 'This server is '.strtolower($server->statusLabel()).' and cannot be controlled right now.');
        }

        $result = NodeClient::for($server->node)->power($server, $action);

        if (! ($result['ok'] ?? false)) {
            return back()->with('error', 'The node did not accept that: '.($result['error'] ?? 'no answer from the daemon').'.');
        }

        $server->update([
            'power_state' => $result['state'] ?? $server->power_state,
            'last_started_at' => in_array($action, ['start', 'restart'], true) ? now() : $server->last_started_at,
            // A person asked for this. The watchdog reads it so that stopping
            // your own server does not get undone a minute later by an
            // "unexpectedly offline" rule.
            'stopped_intentionally' => in_array($action, ['stop', 'kill'], true),
            'cached_at' => now(),
        ]);

        $this->log($server, 'power.'.$action, ucfirst($action).'ed the server');

        return back()->with('status', 'Sent '.$action.' to the server.');
    }

    public function command(Request $request, Server $server)
    {
        $this->guard($server, 'control.command');

        $data = $request->validate(['command' => ['required', 'string', 'max:1000']]);

        if ($server->power_state !== 'running') {
            return back()->with('error', 'The server is not running, so there is nowhere to send that.');
        }

        $sent = NodeClient::for($server->node)->command($server, $data['command']);

        if (! $sent) {
            return back()->with('error', 'The node did not accept that command.');
        }

        // The command itself is recorded, because "who ran /stop" is the first
        // question asked after an unexplained outage.
        $this->log($server, 'console.command', 'Ran console command', ['command' => $data['command']]);

        return back()->with('status', 'Command sent.');
    }

    /**
     * Polled by the console when the SSE stream is unavailable.
     *
     * With ?tail= it also carries the log backlog, because in that mode the
     * panel is the only path to the node the browser has. The browser appends
     * only the part it has not already got, so the tail can be re-sent freely.
     */
    public function stats(Request $request, Server $server)
    {
        $this->guard($server, 'control.console');

        $client = NodeClient::for($server->node);
        $payload = $client->stats($server);

        if ($tail = (int) $request->query('tail')) {
            $payload['lines'] = $client->logs($server, min(500, max(1, $tail)));
        }

        return response()->json($payload);
    }
}
