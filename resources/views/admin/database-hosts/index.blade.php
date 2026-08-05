<x-layouts.app :title="$title">
    <x-page-header title="Database Hosts" icon="database"
                   subtitle="Where per-server game databases get carved out of. Clients never see these credentials.">
        <x-slot:actions><x-button href="{{ route('admin.database-hosts.create') }}" icon="plus" size="sm">New Host</x-button></x-slot:actions>
    </x-page-header>

    <x-card flush>
        @if ($hosts->isEmpty())
            <x-empty-state icon="database" title="No Database Hosts"
                           description="Without one, the Databases tab has nothing to hand out.">
                <x-slot:action><x-button href="{{ route('admin.database-hosts.create') }}" icon="plus">New Host</x-button></x-slot:action>
            </x-empty-state>
        @else
            <x-mass-actions :action="route('admin.bulk', 'database-hosts')" label="host">
            <x-slot:table>
                <x-table flush>
                <thead><tr><th class="w-10"><x-select-toggle all /></th><th>Host</th><th>Address</th><th>Linked Node</th><th>Databases</th><th class="text-right vx-act-2">Actions</th></tr></thead>
                <tbody>
                @foreach ($hosts as $host)
                <tr>
                <td class="w-10"><x-select-toggle :value="$host->id" :label="$host->name" /></td>
                <td class="font-medium text-slate-900">{{ $host->name }}</td>
                <td class="font-mono text-xs text-slate-500">{{ $host->host }}:{{ $host->port }}</td>
                <td class="text-slate-500">{{ $host->node?->name ?? 'Any node' }}</td>
                <td class="tabular text-slate-500">{{ $host->databases_count }} / {{ $host->max_databases ?: 'unlimited' }}</td>
                <td class="text-right vx-act-2">
                <div class="inline-flex items-center gap-1">
                <x-icon-button href="{{ route('admin.database-hosts.edit', $host) }}" icon="edit" title="Edit Database Host" />
                <x-delete-button
                name="delete-host-{{ $host->id }}"
                :action="route('admin.database-hosts.destroy', $host)"
                title="Delete {{ $host->name }}?"
                message="Only possible while no server holds a database on it."
                label="Delete Database Host" />
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="delete" icon="trash" tone="danger" confirm="Any host that still holds databases is skipped." confirm-title="Delete These Database Hosts?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
