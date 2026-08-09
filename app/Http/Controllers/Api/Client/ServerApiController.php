<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Api\ApiController;
use App\Models\Server;

/**
 * Base for every client endpoint that acts on one server.
 *
 * The guard lives here so no endpoint can forget it. ServerPolicy is the only
 * authority on who may do what: the web screens ask it, SFTP asks it, and this
 * asks it. Three implementations of the same question would eventually give
 * three different answers, and the one that disagreed would be somebody else's
 * server.
 */
abstract class ServerApiController extends ApiController
{
    protected function guard(Server $server, string $permission): void
    {
        abort_unless(
            auth()->user()->can('check', [$server, $permission]),
            403,
            'Your access to this server does not include that.',
        );
    }

    /**
     * Refuse anything that changes a suspended server.
     *
     * ServerPolicy already blocks non-read permissions on a suspended server,
     * so this is belt and braces with a better message: "that server is
     * suspended" is actionable and "your access does not include that" is not.
     */
    protected function refuseIfSuspended(Server $server): void
    {
        abort_if($server->isSuspended(), 409, 'That server is suspended.');
    }
}
