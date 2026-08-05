<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * First-run setup.
 *
 * GameMGR is free, so there is no licence step and no key to paste: create the
 * first admin and you are in. Access is governed entirely by the EnsureSetup
 * middleware, and these routes are deliberately not behind auth so step one can
 * run as a guest.
 */
class SetupController extends Controller
{
    public function index()
    {
        if (User::where('role', 'admin')->doesntExist()) {
            return view('setup.admin');
        }

        Setting::put('setup_complete', '1');

        return redirect()->route('dashboard');
    }

    public function storeAdmin(Request $request)
    {
        // Guard: this route must never become a way to mint a second admin.
        if (User::where('role', 'admin')->exists()) {
            return redirect()->route('setup.index');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            // The model casts password as 'hashed', so the plain value is
            // hashed on save. Hashing here as well would double-hash it.
            'password' => $data['password'],
            'role' => 'admin',
        ]);
        $user->forceFill(['root_admin' => true, 'password_changed_at' => now()])->save();

        Auth::login($user);
        Setting::put('setup_complete', '1');

        return redirect()->route('dashboard')
            ->with('status', 'Welcome to GameMGR. Add your first node to start hosting.');
    }
}
