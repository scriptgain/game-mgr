<?php

namespace App\Http\Controllers\Client;

use App\Models\Allocation;
use App\Models\Server;
use Illuminate\Http\Request;

/**
 * Ports. A server has one primary allocation players connect to, and may hold
 * extra ones for query, RCON or a second game mode.
 */
class NetworkController extends ServerController
{
    public function index(Server $server)
    {
        $this->guard($server, 'allocation.read');

        $server->load('node', 'allocation', 'template.ports');

        // The canonical port this game should be on, and the port it actually
        // got. When they differ the owner has to be told, because the number
        // they hand to their players is the second one.
        $canonical = $server->template?->canonicalGamePort();
        $actual = $server->allocations->firstWhere('role', 'game')?->port
            ?? $server->allocation?->port;

        return view('server.network', [
            'title' => $server->name.' Network',
            'server' => $server,
            'allocations' => $server->allocations()->orderBy('port')->get(),
            'canonicalPort' => $canonical,
            'portShift' => $canonical && $actual ? (int) $actual - (int) $canonical : 0,
        ]);
    }

    public function store(Server $server)
    {
        $this->guard($server, 'allocation.create');

        $held = $server->allocations()->count();
        if ($server->allocation_limit > 0 && $held >= $server->allocation_limit) {
            return back()->with('error', 'This server is at its limit of '.$server->allocation_limit.' allocations.');
        }

        $free = $server->node->allocations()->whereNull('server_id')->orderBy('port')->first();
        if (! $free) {
            return back()->with('error', 'The node has no free ports left. An administrator needs to add more.');
        }

        // Marked "extra", not left blank. The daemon opens a firewall hole for
        // every port a server holds, and it should be able to tell the port a
        // game actually speaks on from a spare somebody claimed by hand.
        $free->update(['server_id' => $server->id, 'role' => 'extra']);
        $this->log($server, 'allocation.create', 'Added allocation '.$free->address());

        return back()->with('status', 'Allocation added: '.$free->address().'.');
    }

    public function makePrimary(Server $server, Allocation $allocation)
    {
        $this->guard($server, 'allocation.update');
        abort_unless($allocation->server_id === $server->id, 404);

        $server->update(['allocation_id' => $allocation->id]);
        $this->log($server, 'allocation.primary', 'Made '.$allocation->address().' the primary address');

        return back()->with('status', 'Primary address changed. Restart the server for it to take effect.');
    }

    public function destroy(Server $server, Allocation $allocation)
    {
        $this->guard($server, 'allocation.delete');
        abort_unless($allocation->server_id === $server->id, 404);

        if ($server->allocation_id === $allocation->id) {
            return back()->with('error', 'That is the primary address. Make another one primary first.');
        }

        $allocation->release();
        $this->log($server, 'allocation.delete', 'Released allocation '.$allocation->address());

        return back()->with('status', 'Allocation released.');
    }
}
