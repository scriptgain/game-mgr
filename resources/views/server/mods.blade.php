<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    @if ($catalogue['note'])
        <div class="mb-6">
            <x-alert :type="$catalogue['tone']" :title="$catalogue['title']">
                {{ $catalogue['note'] }}
            </x-alert>
        </div>
    @endif

    @if ($updatable->isNotEmpty())
        <div class="mb-6">
            <x-alert type="info" title="{{ $updatable->count() }} {{ Str::plural('Mod', $updatable->count()) }} Can Be Updated">
                {{ $updatable->pluck('name')->join(', ', ' and ') }}. Update them, then restart the server to load the new versions.
            </x-alert>
        </div>
    @endif

    <x-card title="Installed Mods" icon="puzzle"
            subtitle="{{ $target->supported()
                ? 'Filtered to '.$target->filterSummary().'.'
                : 'Sourced from '.(collect($sources)->map(fn ($s) => \App\Models\Mod::SOURCES[$s] ?? $s)->join(', ', ' and ') ?: 'no configured source').'.' }}"
            flush>
        <x-slot:actions>
            @can('check', [$server, 'mod.update'])
                @if ($catalogue['ok'] && $mods->isNotEmpty())
                    {{-- A form, not a link: this writes what the panel believes
                         the newest version is, and a link meant a prefetch could
                         fire a handful of outbound API calls. --}}
                    <form method="POST" action="{{ route('server.mods.refresh', $server) }}">
                        @csrf
                        <x-button type="submit" variant="secondary" size="sm" icon="sync">Check For Updates</x-button>
                    </form>
                @endif
            @endcan
            @can('check', [$server, 'mod.install'])
                <x-button href="{{ route('server.mods.browse', $server) }}" size="sm" icon="search">Browse Mods</x-button>
            @endcan
        </x-slot:actions>

        @if ($mods->isEmpty())
            <x-empty-state icon="puzzle" title="No Mods Installed"
                           description="Search the catalogues this server can use and install with one click, instead of hunting for a jar and dragging it into the file manager.">
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
                <th>Enabled</th>
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
                @if ($mod->path)
                <span class="block text-xs text-slate-400 truncate">{{ $mod->path }}</span>
                @endif
                </td>
                <td>
                    <x-badge color="neutral">{{ $mod->sourceLabel() }}</x-badge>
                    {{-- Said out loud, because the alternative is implying a
                         check that never happened. SpigotMC publishes no
                         checksums, so a file from there is exactly as
                         trustworthy as the connection that carried it. --}}
                    @unless ($mod->verified)
                        <span class="mt-1 block text-xs text-amber-700" title="This source publishes no checksum, so the download could not be verified against one.">Unverified download</span>
                    @endunless
                </td>
                <td class="tabular text-slate-500">
                {{ $mod->version }}
                @if ($mod->hasUpdate())
                <span class="block text-xs text-brand-600">{{ $mod->latest_version }} available</span>
                @endif
                </td>
                <td>
                @can('check', [$server, 'mod.update'])
                <form method="POST" action="{{ route('server.mods.toggle', [$server, $mod]) }}" data-autosubmit>
                @csrf
                <x-check-switch name="enabled" :checked="$mod->enabled">
                <span class="sr-only">{{ $mod->enabled ? 'Disable '.$mod->name : 'Enable '.$mod->name }}</span>
                </x-check-switch>
                </form>
                @else
                <x-badge :color="$mod->enabled ? 'success' : 'neutral'" dot>{{ $mod->enabled ? 'Enabled' : 'Disabled' }}</x-badge>
                @endcan
                </td>
                <td class="text-right vx-act-3">
                <div class="inline-flex items-center gap-1">
                @can('check', [$server, 'mod.update'])
                @if ($mod->hasUpdate())
                <form method="POST" action="{{ route('server.mods.update', [$server, $mod]) }}">
                @csrf<x-icon-button type="submit" icon="arrow-up" variant="brand" title="Update To {{ $mod->latest_version }}" />
                </form>
                @endif
                @endcan
                @can('check', [$server, 'mod.delete'])
                <x-delete-button
                name="remove-mod-{{ $mod->id }}"
                :action="route('server.mods.destroy', [$server, $mod])"
                title="Remove {{ $mod->name }}?"
                message="The jar is deleted from the server. Any world data it created stays behind and may cause errors on next boot. To turn it off without losing anything, use the Enabled switch instead."
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
            <x-mass-action action="delete" icon="trash" tone="danger" confirm="The jars are deleted from the server. World data they created stays behind and may cause errors on next boot." confirm-title="Delete These Mods?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
