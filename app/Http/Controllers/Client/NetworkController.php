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

        return view('server.network', [
            'title' => $server->name.' Network',
            'server' => $server->load('node', 'allocation'),
            'allocations' => $server->allocations()->orderBy('port')->get(),
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

        $free->update(['server_id' => $server->id]);
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

        $allocation->update(['server_id' => null]);
        $this->log($server, 'allocation.delete', 'Released allocation '.$allocation->address());

        return back()->with('status', 'Allocation released.');
    }
}
