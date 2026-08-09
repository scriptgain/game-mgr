<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Acting as another account, for support.
 *
 * An admin can already see every server, but seeing a server is not the same as
 * seeing what the customer sees: which tabs their permissions actually leave
 * them, what their dashboard lists, which buttons are missing. Most support
 * questions are about that difference, and guessing at it from an admin session
 * is how "it works for me" happens.
 *
 * Three rules hold this together.
 *
 * The session remembers who started it. `impersonator_id` is the way back, and
 * while it is set the session belongs to two people: the customer being acted
 * as, and the admin answerable for it.
 *
 * Stopping is NOT an admin route. While impersonating a client you are not an
 * admin, so a stop route behind the admin gate would strand you in the account
 * you just stepped into with no way out but clearing cookies. This is the trap
 * worth remembering: the way out has to be reachable by the person you became.
 *
 * And an admin cannot become another admin. Nothing is gained, and it makes the
 * audit trail ambiguous about who did what.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, User $user)
    {
        $admin = $request->user();

        if ($admin->is($user)) {
            return back()->with('error', 'You are already yourself.');
        }
        if ($user->isAdmin()) {
            return back()->with('error', 'Administrators cannot act as other administrators. Nothing would be gained, and the audit trail would stop saying who did what.');
        }
        if ($user->suspended) {
            return back()->with('error', 'That account is suspended. Lift the suspension first if you need to see what they see.');
        }

        AuditLog::record('user.impersonate.start',
            $admin->name.' started acting as '.$user->name.'.', $user);

        Auth::login($user);

        // Written after the swap, deliberately. Auth::login regenerates the
        // session id, and while that preserves the data, writing the only way
        // back AFTER the thing that touches the session leaves no room for that
        // to stop being true.
        $request->session()->put('impersonator_id', $admin->id);

        return redirect()->route('dashboard')
            ->with('status', 'You are now acting as '.$user->name.'. Everything you do is recorded against your own account.');
    }

    public function stop(Request $request)
    {
        $adminId = $request->session()->pull('impersonator_id');
        if (! $adminId) {
            return redirect()->route('dashboard');
        }

        $admin = User::find($adminId);
        if (! $admin) {
            // The admin account was deleted mid-session. Log the customer out
            // rather than leave somebody sitting in an account they were only
            // ever borrowing.
            Auth::logout();

            return redirect()->route('login')
                ->with('error', 'The account that started this session no longer exists, so it has been ended.');
        }

        $wasActingAs = $request->user();
        Auth::login($admin);

        AuditLog::record('user.impersonate.stop',
            $admin->name.' stopped acting as '.($wasActingAs?->name ?? 'a customer').'.', $wasActingAs);

        return redirect()->route('admin.users.index')
            ->with('status', 'You are back to your own account.');
    }
}
