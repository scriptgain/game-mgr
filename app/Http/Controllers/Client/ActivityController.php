<?php

namespace App\Http\Controllers\Client;

use App\Models\Server;

class ActivityController extends ServerController
{
    public function index(Server $server)
    {
        $this->guard($server, 'activity.read');

        return view('server.activity', [
            'title' => $server->name.' Activity',
            'server' => $server->load('node'),
            'entries' => $server->activity()->with('user')->paginate(40),
        ]);
    }
}
