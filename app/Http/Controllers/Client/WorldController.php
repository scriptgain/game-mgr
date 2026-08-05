<?php

namespace App\Http\Controllers\Client;

use App\Models\Server;
use App\Models\World;

/**
 * Worlds and saves. Swapping the active one is a first-class action rather than
 * a folder rename you do by hand and hope you got right.
 */
class WorldController extends ServerController
{
    public function index(Server $server)
    {
        $this->guard($server, 'world.read');

        return view('server.worlds', [
            'title' => $server->name.' Worlds',
            'server' => $server->load('node', 'template.game'),
            'worlds' => $server->worlds()->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function activate(Server $server, World $world)
    {
        $this->guard($server, 'world.switch');
        abort_unless($world->server_id === $server->id, 404);

        if ($server->power_state === 'running') {
            return back()->with('error', 'Stop the server first. Swapping a world underneath a running server corrupts it.');
        }

        $server->worlds()->update(['is_active' => false]);
        $world->update(['is_active' => true]);

        $this->log($server, 'world.switch', 'Switched the active world to '.$world->name);

        return back()->with('status', $world->name.' is now the active world.');
    }

    public function destroy(Server $server, World $world)
    {
        $this->guard($server, 'world.delete');
        abort_unless($world->server_id === $server->id, 404);

        if ($world->is_active) {
            return back()->with('error', 'That is the active world. Switch to another one first.');
        }

        $name = $world->name;
        $world->delete();
        $this->log($server, 'world.delete', 'Deleted world '.$name);

        return back()->with('status', $name.' deleted.');
    }
}
