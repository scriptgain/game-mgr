<?php

namespace App\Http\Controllers\Client;

use App\Models\DatabaseHost;
use App\Models\Server;
use App\Models\ServerDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DatabaseController extends ServerController
{
    public function index(Server $server)
    {
        $this->guard($server, 'database.read');

        return view('server.databases', [
            'title' => $server->name.' Databases',
            'server' => $server->load('node'),
            'databases' => $server->databases()->with('host')->get(),
            'hosts' => DatabaseHost::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Server $server)
    {
        $this->guard($server, 'database.create');

        $data = $request->validate([
            'database_host_id' => ['required', 'exists:database_hosts,id'],
            'name' => ['required', 'string', 'max:32', 'regex:/^[a-zA-Z0-9_]+$/'],
            'remote' => ['required', 'string', 'max:64'],
        ]);

        if ($server->database_limit > 0 && $server->databases()->count() >= $server->database_limit) {
            return back()->with('error', 'This server is at its limit of '.$server->database_limit.' databases.');
        }

        $host = DatabaseHost::findOrFail($data['database_host_id']);
        if ($host->isFull()) {
            return back()->with('error', 'That database host is full. Ask an administrator for another.');
        }

        // Prefixed with the server id so two servers can both have a database
        // called "main" without colliding on a shared host.
        $dbName = 's'.$server->id.'_'.$data['name'];

        ServerDatabase::create([
            'server_id' => $server->id,
            'database_host_id' => $host->id,
            'database' => $dbName,
            'username' => 'u'.$server->id.'_'.Str::lower(Str::random(7)),
            'password' => Str::random(24),
            'remote' => $data['remote'],
        ]);

        $this->log($server, 'database.create', 'Created database '.$dbName);

        return back()->with('status', 'Database created.');
    }

    public function destroy(Server $server, ServerDatabase $database)
    {
        $this->guard($server, 'database.delete');
        abort_unless($database->server_id === $server->id, 404);

        $name = $database->database;
        $database->delete();
        $this->log($server, 'database.delete', 'Deleted database '.$name);

        return back()->with('status', 'Database deleted.');
    }
}
