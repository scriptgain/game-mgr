<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index()
    {
        return view('account.index', [
            'title' => 'My Account',
            'user' => auth()->user(),
            'recent' => AuditLog::where('user_id', auth()->id())->latest('id')->limit(15)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Half of an SFTP login, so it has to identify exactly one account.
            // Dots are allowed because a username is derived from an email local
            // part and those routinely contain one, but not as the first
            // character: the SFTP login splits on the last dot, and a leading
            // one reads as an empty name.
            'username' => [
                'required', 'string', 'min:3', 'max:48',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/',
                \Illuminate\Validation\Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'timezone' => ['required', 'string', 'max:64'],
        ], [
            'username.regex' => 'Use letters, numbers, dots, dashes and underscores, starting with a letter or number.',
            'username.unique' => 'Somebody already has that username.',
        ]);

        $renamed = $user->username !== $data['username'];

        $user->update($data);
        AuditLog::record('account.update', 'Updated account details');

        return back()->with('status', $renamed
            ? 'Account updated. Your SFTP login is now '.$user->username.'.something, so anything with the old one saved needs changing.'
            : 'Account updated.');
    }
}
