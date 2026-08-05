<?php

namespace App\Http\Controllers\Client;

use App\Models\Server;
use App\Models\Subuser;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Sharing a server with somebody else, on a permission matrix rather than
 * all-or-nothing.
 */
class SubuserController extends ServerController
{
    public function index(Server $server)
    {
        $this->guard($server, 'user.read');

        return view('server.users', [
            'title' => $server->name.' Users',
            'server' => $server->load('node', 'owner'),
            'subusers' => $server->subusers()->with('user')->get(),
        ]);
    }

    public function create(Server $server)
    {
        $this->guard($server, 'user.create');

        return view('server.user-form', [
            'title' => 'Invite A User',
            'server' => $server->load('node'),
            'subuser' => new Subuser(['permissions' => Subuser::defaultPermissions()]),
        ]);
    }

    public function store(Request $request, Server $server)
    {
        $this->guard($server, 'user.create');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return back()->with('error', 'No account uses that address. They need to be registered before they can be invited.')->withInput();
        }
        if ($user->id === $server->owner_id) {
            return back()->with('error', 'That is the owner. They already have full access.')->withInput();
        }
        if ($server->subusers()->where('user_id', $user->id)->exists()) {
            return back()->with('error', 'That person already has access to this server.')->withInput();
        }

        Subuser::create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'permissions' => $this->clean($data['permissions'] ?? []),
        ]);

        $this->log($server, 'subuser.create', 'Gave '.$user->email.' access');

        return redirect()->route('server.users', $server)->with('status', $user->name.' now has access.');
    }

    public function edit(Server $server, Subuser $subuser)
    {
        $this->guard($server, 'user.read');
        abort_unless($subuser->server_id === $server->id, 404);

        return view('server.user-form', [
            'title' => 'Edit Access',
            'server' => $server->load('node'),
            'subuser' => $subuser->load('user'),
        ]);
    }

    public function update(Request $request, Server $server, Subuser $subuser)
    {
        $this->guard($server, 'user.update');
        abort_unless($subuser->server_id === $server->id, 404);

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $subuser->update(['permissions' => $this->clean($data['permissions'] ?? [])]);
        $this->log($server, 'subuser.update', 'Changed access for '.$subuser->user?->email);

        return redirect()->route('server.users', $server)->with('status', 'Access updated.');
    }

    public function destroy(Server $server, Subuser $subuser)
    {
        $this->guard($server, 'user.delete');
        abort_unless($subuser->server_id === $server->id, 404);

        $email = $subuser->user?->email;
        $subuser->delete();
        $this->log($server, 'subuser.delete', 'Removed access for '.$email);

        return back()->with('status', 'Access removed.');
    }

    /** Only real permission keys survive, whatever the form posted. */
    private function clean(array $permissions): array
    {
        return array_values(array_intersect($permissions, Subuser::allPermissions()));
    }
}
