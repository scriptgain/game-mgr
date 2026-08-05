<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Server;

/**
 * Shared base for every per-server client screen.
 *
 * Two things every subclass needs and must not reimplement: a permission check
 * that goes through ServerPolicy, and a way to record what the user did against
 * the server's own activity log. Controllers that invent their own rule are how
 * a permission model quietly stops meaning anything.
 */
abstract class ServerController extends Controller
{
    /**
     * Authorise a specific permission on a server, or 403. Every action calls
     * this, including the ones an owner would obviously be allowed to do:
     * subusers exist, and "obviously allowed" is exactly where the gap opens.
     */
    protected function guard(Server $server, string $permission): void
    {
        abort_unless(
            auth()->user()->can('check', [$server, $permission]),
            403,
            'Your access to this server does not include that.'
        );
    }

    /** Record an entry on the server's Activity tab. */
    protected function log(Server $server, string $action, string $description, array $properties = []): void
    {
        AuditLog::record($action, $description, $server, $server->id, $properties);
    }
}
