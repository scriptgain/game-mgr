<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Personal API credentials.
 *
 * Two scopes, matching Pterodactyl so existing tooling ports across: a client
 * token reaches only the servers its owner can already see, an application
 * token drives the whole admin API and is admin only.
 */
class ApiTokenController extends Controller
{
    public function index()
    {
        return view('account.api', [
            'title' => 'API Credentials',
            'tokens' => ApiToken::where('user_id', auth()->id())->latest('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'scope' => ['required', 'in:client,application'],
            'expires_days' => ['nullable', 'integer', 'between:1,3650'],
        ]);

        // Only an admin may mint a token that reaches the admin API. Without
        // this a client could grant themselves fleet-wide access with a form
        // field, which is the whole reason the scope exists.
        if ($data['scope'] === 'application' && ! auth()->user()->isAdmin()) {
            abort(403);
        }

        // The plaintext is shown once and never stored: only its hash lands in
        // the database, so a database leak does not hand over live tokens.
        $plain = 'gm_'.Str::random(48);

        ApiToken::create([
            'user_id' => auth()->id(),
            'name' => $data['name'],
            'token' => hash('sha256', $plain),
            'scope' => $data['scope'],
            'expires_at' => $data['expires_days'] ? now()->addDays((int) $data['expires_days']) : null,
        ]);

        AuditLog::record('token.create', 'Created API token "'.$data['name'].'"');

        return back()->with('status', 'Token created. Copy it now, it is not shown again.')
            ->with('plain_token', $plain);
    }

    public function destroy(ApiToken $apiToken)
    {
        abort_unless($apiToken->user_id === auth()->id(), 403);

        $name = $apiToken->name;
        $apiToken->delete();
        AuditLog::record('token.delete', 'Revoked API token "'.$name.'"');

        return back()->with('status', 'Token revoked.');
    }
}
