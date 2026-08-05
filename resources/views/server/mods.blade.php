<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    @if ($updatable->isNotEmpty())
        <div class="mb-6">
            <x-alert type="info" title="{{ $updatable->count() }} {{ Str::plural('mod', $updatable->count()) }} can be updated">
                {{ $updatable->pluck('name')->join(', ', ' and ') }}. Update them, then restart the server to load the new versions.
            </x-alert>
        </div>
    @endif

    <x-card title="Installed Mods"
            subtitle="Sourced from {{ collect($sources)->map(fn ($s) => \App\Models\Mod::SOURCES[$s] ?? $s)->join(', ', ' and ') ?: 'no configured source' }}."
            flush>
        <x-slot:actions>
            @can('check', [$server, 'mod.install'])
                <x-button href="{{ route('server.mods.browse', $server) }}" size="sm" icon="search">Browse Mods</x-button>
            @endcan
        </x-slot:actions>

        @if ($mods->isEmpty())
            <x-empty-state icon="puzzle" title="No Mods Installed"
                           description="Search the catalogue and install with one click, instead of hunting for a jar and dragging it into the file manager.">
                <x-slot:action>
                    @can('check', [$server, 'mod.install'])
                        <x-button href="{{ route('server.mods.browse', $server) }}" icon="search">Browse Mods</x-button>
                    @endcan
                </x-slot:action>
            </x-empty-state>
        @else
            <x-mass-actions :action="route('server.bulk', [$server, 'mods'])" label="mod">
            <x-slot:table>
                <x-table flush>
                <thead>
                <tr><th class="w-10"><x-select-toggle all /></th>
                <th>Mod</th>
                <th>Source</th>
                <th>Version</th>
                <th>Status</th>
                <th class="text-right vx-act-3">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($mods as $mod)
                <tr>
                <td class="w-10"><x-select-toggle :value="$mod->id" :label="$mod->name" /></td>
                <td>
                <span class="font-medium text-slate-900">{{ $mod->name }}</span>
                <span class="block text-xs text-slate-400 truncate">{{ $mod->author ? 'by '.$mod->author : '' }}</span>
                </td>
                <td><x-badge color="neutral">{{ $mod->sourceLabel() }}</x-badge></td>
                <td class="tabular text-slate-500">
                {{ $mod->version }}
                @if ($mod->hasUpdate())
                <span class="block text-xs text-brand-600">{{ $mod->latest_version }} available</span>
                @endif
                </td>
                <td>
                @if (! $mod->enabled)
                <x-badge color="neutral" dot>Disabled</x-badge>
                @elseif ($mod->hasUpdate())
                <x-badge color="warn" dot>Update Ready</x-badge>
                @else
                <x-badge color="success" dot>Current</x-badge>
                @endif
                </td>
                <td class="text-right vx-act-3">
                <div class="inline-flex items-center gap-1">
                @can('check', [$server, 'mod.update'])
                @if ($mod->hasUpdate())
                <form method="POST" action="{{ route('server.mods.update', [$server, $mod]) }}">
                @csrf<x-icon-button type="submit" icon="arrow-up" variant="brand" title="Update To {{ $mod->latest_version }}" />
                </form>
                @endif
                <form method="POST" action="{{ route('server.mods.toggle', [$server, $mod]) }}">
                @csrf<x-icon-button type="submit" :icon="$mod->enabled ? 'x-circle' : 'check-circle'"
                :title="$mod->enabled ? 'Disable Mod' : 'Enable Mod'" />
                </form>
                @endcan
                @can('check', [$server, 'mod.delete'])
                <x-delete-button
                name="remove-mod-{{ $mod->id }}"
                :action="route('server.mods.destroy', [$server, $mod])"
                title="Remove {{ $mod->name }}?"
                message="The mod file is deleted from the server. Any world data it created stays behind and may cause errors on next boot."
                confirm="Remove"
                label="Remove Mod" />
                @endcan
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="enable" icon="check">Enable</x-mass-action>
            <x-mass-action action="disable" icon="x-circle">Disable</x-mass-action>
            <x-mass-action action="delete" icon="trash" tone="danger" confirm="The mod files are deleted from the server. World data they created stays behind and may cause errors on next boot." confirm-title="Delete These Mods?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
