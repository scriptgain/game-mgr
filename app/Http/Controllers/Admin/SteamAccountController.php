<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SteamAccount;
use App\Support\SteamGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Steam accounts that own paid games.
 *
 * Anonymous login covers most dedicated servers and none of the ones people
 * ask for: ARK: Survival Evolved, Squad, Insurgency and Deadlock all want an
 * account that owns the game. Registered once here, bound to as many servers as
 * need it, and never shown to a client.
 */
class SteamAccountController extends Controller
{
    public function index()
    {
        return view('admin.steam-accounts.index', [
            'title' => 'Steam Accounts',
            'accounts' => SteamAccount::withCount('servers')->orderBy('label')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.steam-accounts.form', [
            'title' => 'New Steam Account',
            'account' => new SteamAccount,
        ]);
    }

    public function store(Request $request)
    {
        SteamAccount::create($this->validated($request));

        return redirect()->route('admin.steam-accounts.index')->with('status', 'Steam account added.');
    }

    public function edit(SteamAccount $steamAccount)
    {
        return view('admin.steam-accounts.form', [
            'title' => 'Edit '.$steamAccount->label,
            'account' => $steamAccount,
        ]);
    }

    public function update(Request $request, SteamAccount $steamAccount)
    {
        $data = $this->validated($request, $steamAccount);

        // Blank means "leave it alone", not "erase it". Both secrets are
        // write-only in the form, so the only way to see one is to replace it.
        foreach (['password', 'shared_secret'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        // A changed username or password invalidates every sentry: the file
        // steamcmd wrote is per account, and the next login on those nodes will
        // be challenged again. Saying so up front beats an install that fails
        // at 3am on a node that worked yesterday.
        if (array_key_exists('password', $data) || $data['username'] !== $steamAccount->username) {
            $data['authorized_nodes'] = [];
        }

        $steamAccount->update($data);

        return redirect()->route('admin.steam-accounts.index')->with('status', 'Steam account updated.');
    }

    public function destroy(SteamAccount $steamAccount)
    {
        if ($steamAccount->servers()->exists()) {
            return back()->with('error', 'Servers are still installing with that account. Rebind them first.');
        }

        $steamAccount->delete();

        return back()->with('status', 'Steam account removed.');
    }

    private function validated(Request $request, ?SteamAccount $account = null): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:64', Rule::unique('steam_accounts', 'username')->ignore($account)],
            'password' => [$account ? 'nullable' : 'required', 'string', 'max:255'],

            // Validated rather than merely accepted, because a secret in the
            // wrong encoding produces a code of exactly the right shape that is
            // always wrong, and Steam answers a wrong code with a rate limit
            // that looks identical to a bad password.
            'shared_secret' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (filled($value) && ! SteamGuard::valid($value)) {
                    $fail('That does not look like a shared secret. It is the Base64 string from a mobile authenticator export, not the Base32 one an authenticator app shows.');
                }
            }],
        ]);
    }
}
