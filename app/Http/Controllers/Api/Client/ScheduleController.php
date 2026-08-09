<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Resources\ApiResource;
use App\Http\Resources\ScheduleResource;
use App\Models\Server;
use Illuminate\Http\Request;

/**
 * Schedule for one server, as its owner sees them.
 *
 * Guarded by schedule.read, the same permission the web screen uses. ServerPolicy
 * stays the only authority: the API must not become a second opinion.
 */
class ScheduleController extends ServerApiController
{
    public function index(Request $request, Server $server)
    {
        $this->guard($server, 'schedule.read');

        return $this->paginate($request, $server->schedules(), ScheduleResource::class);
    }
}
