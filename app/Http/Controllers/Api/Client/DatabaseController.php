<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Resources\ApiResource;
use App\Http\Resources\ServerDatabaseResource;
use App\Models\Server;
use Illuminate\Http\Request;

/**
 * Database for one server, as its owner sees them.
 *
 * Guarded by database.read, the same permission the web screen uses. ServerPolicy
 * stays the only authority: the API must not become a second opinion.
 */
class DatabaseController extends ServerApiController
{
    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'database.read');

        return $this->paginate($request, $server->databases(), ServerDatabaseResource::class);
    }
}
