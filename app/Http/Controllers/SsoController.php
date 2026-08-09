<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Consumes a single sign-on link issued over the API.
 *
 * The signature proves the panel issued it and the expiry bounds it, but
 * neither stops the same URL being used twice, and this URL is a credential
 * that ends up in browser history and referrer headers. So the nonce is burned
 * on first use: a link works once and then never again.
 */
class SsoController extends Controller
{
    public function consume(Request $request, User $user)
    {
        // Laravel's signed middleware has already checked the signature and the
        // expiry by the time this runs.
        $nonce = (string) $request->query('nonce');
        if ($nonce === '') {
            abort(403, 'That sign-in link is not valid.');
        }

        $key = 'sso:used:'.hash('sha256', $nonce);
        if (! Cache::add($key, true, now()->addHour())) {
            abort(403, 'That sign-in link has already been used. Ask for another.');
        }

        if ($user->suspended) {
            abort(403, 'That account is suspended.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        // Deliberately not an impersonation: nobody is acting on their behalf,
        // so there is no banner and no way back to somebody else.
        $request->session()->forget('impersonator_id');

        AuditLog::record('user.sso.used', $user->name.' signed in through a single sign-on link', $user);

        return redirect()->route('dashboard');
    }
}
