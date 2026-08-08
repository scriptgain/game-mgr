<x-layouts.app :title="$title">
    <x-page-header title="Servers" icon="server" :subtitle="$servers->count().' '.Str::plural('server', $servers->count()).' across the fleet.'">
        <x-slot:actions>
            <x-button href="{{ route('admin.servers.create') }}" icon="plus" size="sm">New Server</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card flush>
        <div class="px-5 py-3 border-b border-slate-100">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <x-input name="q" value="{{ $filters['search'] }}" placeholder="Search by name" class="w-56" />
                <x-select name="node" class="w-48">
                    <option value="">Every Node</option>
                    @foreach ($nodes as $node)
                        <option value="{{ $node->id }}" @selected($filters['node'] == $node->id)>{{ $node->name }}</option>
                    @endforeach
                </x-select>
                <x-select name="runtime" class="w-44">
                    <option value="">Every Runtime</option>
                    @foreach (\App\Models\Template::RUNTIMES as $value => $label)
                        <option value="{{ $value }}" @selected($filters['runtime'] === $value)>{{ $label }}</option>
                    @endforeach
                </x-select>
                <x-select name="state" class="w-44">
                    <option value="">Any State</option>
                    <option value="running" @selected($filters['state'] === 'running')>Running</option>
                    <option value="offline" @selected($filters['state'] === 'offline')>Offline</option>
                    <option value="attention" @selected($filters['state'] === 'attention')>Needs Attention</option>
                </x-select>
                <x-button type="submit" variant="secondary" size="sm" icon="search">Filter</x-button>
            </form>
        </div>

        @if ($servers->isEmpty())
            <x-empty-state icon="server" title="No Servers Match"
                           description="Change the filters, or create a server." />
        @else
            <x-mass-actions :action="route('admin.bulk', 'servers')" label="server">
            <x-slot:table>
                <x-table flush>
                <thead>
                <tr>
                <th class="w-10"><x-select-toggle all /></th><th>Server</th><th>Owner</th><th>Node</th><th>Address</th><th>Status</th><th class="text-right vx-act-2">Actions</th></tr>
                </thead>
                <tbody>
                @foreach ($servers as $server)
                <tr>
                <td class="w-10"><x-select-toggle :value="$server->id" :label="$server->name" /></td>
                <td>
                <a href="{{ route('admin.servers.show', $server) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $server->name }}</a>
                <span class="block text-xs text-slate-400 truncate">{{ $server->template?->game?->name }} &middot; {{ $server->template?->name }}</span>
                </td>
                <td class="text-slate-500">{{ $server->owner?->name }}</td>
                <td class="vx-cell-wrap">
                <span class="text-slate-700">{{ $server->node?->name }}</span>
                <x-runtime-badge :runtime="$server->runtime" class="ml-1 align-middle" />
                </td>
                {{-- Both addresses, one line each. A connection name is long, so
                     each line truncates with an ellipsis of its own rather than
                     being clipped mid-character or wrapping the row to three
                     lines. The full values are on the server page. --}}
                <td class="font-mono text-xs text-slate-500">
                @if ($server->connectAddress())
                <span class="block truncate text-slate-700">{{ $server->connectAddress() }}</span>
                @endif
                <span class="block truncate">{{ $server->address() }}</span>
                <span class="block font-sans text-slate-400">{{ \App\Support\Format::mib($server->memory) }} RAM</span>
                </td>
                <td><x-status-dot :tone="$server->statusTone()" :label="$server->statusLabel()" /></td>
                <td class="text-right vx-act-2">
                <div class="inline-flex items-center gap-1">
                <x-icon-button href="{{ route('server.console', $server) }}" icon="terminal" title="Open Console" />
                <x-icon-button href="{{ route('admin.servers.show', $server) }}" icon="settings" title="Manage Server" />
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="suspend" icon="ban" confirm="Suspended servers stop and their owners lose every control except reading. Files and backups are untouched." confirm-title="Suspend These Servers?">Suspend</x-mass-action>
            <x-mass-action action="unsuspend" icon="check">Unsuspend</x-mass-action>
            <x-mass-action action="reinstall" icon="refresh" confirm="The install script runs again on each one. Game files are replaced; data directories are kept." confirm-title="Reinstall These Servers?">Reinstall</x-mass-action>
            <x-mass-action action="delete" icon="trash" tone="danger" confirm="Each server record, its backups and its databases are removed, and its ports are freed. There is no undo." confirm-title="Delete These Servers?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
