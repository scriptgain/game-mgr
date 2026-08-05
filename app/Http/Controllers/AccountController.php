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
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $user->update($data);
        AuditLog::record('account.update', 'Updated account details');

        return back()->with('status', 'Account updated.');
    }
}
