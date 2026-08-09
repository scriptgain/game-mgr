<x-layouts.app :title="$title">
    <x-page-header title="Users" icon="users" subtitle="Admins see everything. Clients only see servers they own or were invited to.">
        <x-slot:actions>
            <x-button href="{{ route('admin.users.create') }}" icon="plus" size="sm">New User</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card flush>
        <x-mass-actions :action="route('admin.bulk', 'users')" label="user">
            <x-slot:table>
                <x-table flush>
                <thead><tr><th class="w-10"><x-select-toggle all /></th><th>Name</th><th>Email</th><th>Role</th><th>Servers</th><th>Last Login</th><th class="text-right vx-act-3">Actions</th></tr></thead>
                <tbody>
                @foreach ($users as $user)
                <tr>
                <td class="w-10"><x-select-toggle :value="$user->id" :label="$user->name" /></td>
                <td>
                <span class="font-medium text-slate-900">{{ $user->name }}</span>
                @if ($user->suspended)<x-badge color="danger" class="ml-1.5">Suspended</x-badge>@endif
                </td>
                <td class="text-slate-500">{{ $user->email }}</td>
                <td>
                @if ($user->isRootAdmin())
                <x-badge color="info">Root Admin</x-badge>
                @elseif ($user->isAdmin())
                <x-badge color="info">Admin</x-badge>
                @else
                <x-badge color="neutral">Client</x-badge>
                @endif
                </td>
                <td class="tabular">{{ $user->servers_count }}</td>
                <td class="text-slate-500 text-xs">{{ $user->last_login_at?->diffForHumans() ?? 'never' }}</td>
                <td class="text-right vx-act-3">
                <div class="inline-flex items-center gap-1">
                <x-icon-button href="{{ route('admin.users.edit', $user) }}" icon="edit" title="Edit User" />
                {{-- Only for accounts it would tell you something about. An
                     admin already sees everything, so acting as one shows
                     nothing new and muddies who did what in the audit log. --}}
                @unless ($user->isAdmin() || $user->id === auth()->id() || $user->suspended)
                <form method="POST" action="{{ route('admin.users.act-as', $user) }}" class="inline-flex">
                @csrf
                <x-icon-button type="submit" icon="eye" title="Act As {{ $user->name }}" />
                </form>
                @endunless
                @unless ($user->isRootAdmin() || $user->id === auth()->id())
                <x-delete-button
                name="delete-user-{{ $user->id }}"
                :action="route('admin.users.destroy', $user)"
                title="Delete {{ $user->name }}?"
                message="Only possible while they own no servers. Any server access they were granted goes with them."
                label="Delete User" />
                @endunless
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="suspend" icon="ban" confirm="They keep their servers but cannot sign in." confirm-title="Suspend These Users?">Suspend</x-mass-action>
            <x-mass-action action="unsuspend" icon="check">Unsuspend</x-mass-action>
            <x-mass-action action="delete" icon="trash" tone="danger" confirm="Anyone who still owns servers is skipped, and so is the root admin." confirm-title="Delete These Users?">Delete</x-mass-action>
        </x-mass-actions>
    </x-card>
</x-layouts.app>
