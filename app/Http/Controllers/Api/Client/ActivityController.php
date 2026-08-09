<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Resources\ApiResource;
use App\Http\Resources\AuditLogResource;
use App\Models\Server;
use Illuminate\Http\Request;

/**
 * Activity for one server, as its owner sees them.
 *
 * Guarded by activity.read, the same permission the web screen uses. ServerPolicy
 * stays the only authority: the API must not become a second opinion.
 */
class ActivityController extends ServerApiController
{
    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'activity.read');

        return $this->paginate($request, $server->activity(), AuditLogResource::class);
    }
}
