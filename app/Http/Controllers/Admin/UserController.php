<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', [
            'title' => 'Users',
            'users' => User::withCount('servers')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.users.form', ['title' => 'New User', 'user' => new User(['role' => 'client', 'timezone' => config('app.timezone')])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:admin,client'],
            'timezone' => ['required', 'string', 'max:64'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($data);
        $user->forceFill(['password_changed_at' => now()])->save();

        AuditLog::record('user.create', 'Created user "'.$user->name.'"', $user);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['title' => 'Edit '.$user->name, 'user' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:admin,client'],
            'timezone' => ['required', 'string', 'max:64'],
            'suspended' => ['nullable', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        // The root admin cannot be demoted or suspended, by anyone including
        // itself. This is the only thing standing between a mis-click and an
        // install with no way back in.
        if ($user->isRootAdmin()) {
            $data['role'] = 'admin';
            $data['suspended'] = false;
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $user->forceFill(['password_changed_at' => now()])->save();
        }

        $data['suspended'] = (bool) ($data['suspended'] ?? false);
        $user->update($data);

        AuditLog::record('user.update', 'Updated user "'.$user->name.'"', $user);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->isRootAdmin()) {
            return back()->with('error', 'The root admin cannot be deleted.');
        }
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete the account you are signed in as.');
        }
        if ($user->servers()->exists()) {
            return back()->with('error', 'That user still owns servers. Reassign or delete them first.');
        }

        $name = $user->name;
        $user->delete();
        AuditLog::record('user.delete', 'Deleted user "'.$name.'"');

        return back()->with('status', 'User deleted.');
    }
}
