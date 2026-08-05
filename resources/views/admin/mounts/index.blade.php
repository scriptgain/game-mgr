<x-layouts.app :title="$title">
    <x-page-header title="Mounts" icon="folder"
                   subtitle="Expose a host path inside a server, allowlisted by node and template.">
        <x-slot:actions><x-button href="{{ route('admin.mounts.create') }}" icon="plus" size="sm">New Mount</x-button></x-slot:actions>
    </x-page-header>

    <x-card flush>
        @if ($mounts->isEmpty())
            <x-empty-state icon="folder" title="No Mounts"
                           description="Useful for a shared map pool or a common asset directory across many servers.">
                <x-slot:action><x-button href="{{ route('admin.mounts.create') }}" icon="plus">New Mount</x-button></x-slot:action>
            </x-empty-state>
        @else
            <x-mass-actions :action="route('admin.bulk', 'mounts')" label="mount">
            <x-slot:table>
                <x-table flush>
                <thead><tr><th class="w-10"><x-select-toggle all /></th><th>Mount</th><th>Source</th><th>Target</th><th>Access</th><th>Allowed On</th><th class="text-right vx-act-2">Actions</th></tr></thead>
                <tbody>
                @foreach ($mounts as $mount)
                <tr>
                <td class="w-10"><x-select-toggle :value="$mount->id" :label="$mount->name" /></td>
                <td class="font-medium text-slate-900">{{ $mount->name }}</td>
                <td class="font-mono text-xs text-slate-500">{{ $mount->source }}</td>
                <td class="font-mono text-xs text-slate-500">{{ $mount->target }}</td>
                <td>
                @if ($mount->read_only)<x-badge color="neutral">Read Only</x-badge>
                @else<x-badge color="warn">Writable</x-badge>@endif
                </td>
                <td class="text-slate-500 tabular">{{ $mount->nodes_count }} nodes, {{ $mount->templates_count }} templates</td>
                <td class="text-right vx-act-2">
                <div class="inline-flex items-center gap-1">
                <x-icon-button href="{{ route('admin.mounts.edit', $mount) }}" icon="edit" title="Edit Mount" />
                <x-delete-button
                name="delete-mount-{{ $mount->id }}"
                :action="route('admin.mounts.destroy', $mount)"
                title="Delete {{ $mount->name }}?"
                message="Servers using it lose the path on their next restart."
                label="Delete Mount" />
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="delete" icon="trash" tone="danger" confirm="Servers using them lose the path on their next restart." confirm-title="Delete These Mounts?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
