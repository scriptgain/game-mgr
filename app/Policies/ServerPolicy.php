<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\Subuser;
use App\Models\User;

/**
 * One place that answers "can this person do that to this server".
 *
 * Three tiers, checked in order:
 *   1. Admins can do anything. Support has to be able to see what a customer
 *      sees without an impersonation dance.
 *   2. The owner can do anything to their own server except the admin-only
 *      operations (suspend, transfer, change limits).
 *   3. A subuser can do exactly what their permission list says, and nothing on
 *      a suspended server.
 *
 * Every client controller calls check() with a permission string rather than
 * inventing its own rule, so there is a single place to audit.
 */
class ServerPolicy
{
    public function view(User $user, Server $server): bool
    {
        return $this->reaches($user, $server);
    }

    /** Only the owner and admins can destroy or rename outright. */
    public function manage(User $user, Server $server): bool
    {
        return $user->isAdmin() || $server->owner_id === $user->id;
    }

    /** Suspend, transfer, change limits, reassign the owner. */
    public function administer(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * The workhorse. Returns true when the user holds a specific permission on
     * this server.
     */
    public function check(User $user, Server $server, string $permission): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // A suspended server is read-only for everyone but an admin: no power
        // actions, no file writes, no backups. Letting an owner keep poking a
        // suspended server defeats the point of suspending it.
        if ($server->isSuspended() && ! str_ends_with($permission, '.read')) {
            return false;
        }

        if ($server->owner_id === $user->id) {
            return true;
        }

        $subuser = Subuser::where('server_id', $server->id)
            ->where('user_id', $user->id)
            ->first();

        return $subuser?->can($permission) ?? false;
    }

    private function reaches(User $user, Server $server): bool
    {
        return $user->isAdmin()
            || $server->owner_id === $user->id
            || Subuser::where('server_id', $server->id)->where('user_id', $user->id)->exists();
    }
}
