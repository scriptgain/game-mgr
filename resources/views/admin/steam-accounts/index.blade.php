<x-layouts.app :title="$title">
    <x-page-header title="Steam Accounts" icon="key"
                   subtitle="Accounts that own paid games, for templates anonymous login cannot install. Clients never see these.">
        <x-slot:actions><x-button href="{{ route('admin.steam-accounts.create') }}" icon="plus" size="sm">New Account</x-button></x-slot:actions>
    </x-page-header>

    <x-card flush>
        @if ($accounts->isEmpty())
            <x-empty-state icon="key" title="No Steam Accounts"
                           description="Every template installs anonymously until one exists. Games like ARK: Survival Evolved, Squad and Deadlock need an account that owns them.">
                <x-slot:action><x-button href="{{ route('admin.steam-accounts.create') }}" icon="plus">New Account</x-button></x-slot:action>
            </x-empty-state>
        @else
            <x-table flush>
                <thead><tr><th>Account</th><th>Steam Login</th><th>Steam Guard</th><th>Servers</th><th class="text-right vx-act-2">Actions</th></tr></thead>
                <tbody>
                @foreach ($accounts as $account)
                    <tr>
                        <td class="font-medium text-slate-900">{{ $account->label }}</td>
                        <td class="font-mono text-xs text-slate-500">{{ $account->username }}</td>
                        <td>
                            @if (filled($account->shared_secret))
                                <x-status-dot tone="emerald" label="Automatic" />
                            @else
                                <x-status-dot tone="slate" label="None Stored" />
                            @endif
                        </td>
                        <td class="tabular text-slate-500">{{ $account->servers_count }}</td>
                        <td class="text-right vx-act-2">
                            <div class="inline-flex items-center gap-1">
                                <x-icon-button href="{{ route('admin.steam-accounts.edit', $account) }}" icon="edit" title="Edit Steam Account" />
                                <x-delete-button
                                    name="delete-steam-account-{{ $account->id }}"
                                    :action="route('admin.steam-accounts.destroy', $account)"
                                    title="Delete {{ $account->label }}?"
                                    message="Only possible while no server is bound to it. Servers already installed keep their files."
                                    label="Delete Steam Account" />
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>
</x-layouts.app>
