<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <x-card title="Worlds And Saves" icon="map"
            subtitle="Switching the active world is a first-class action here, not a folder rename you do by hand."
            flush>
        @if ($worlds->isEmpty())
            <x-empty-state icon="map" title="No Worlds Found"
                           description="The node reports no world directories yet. One appears the first time the server generates or loads a save." />
        @else
            <x-mass-actions :action="route('server.bulk', [$server, 'worlds'])" label="world">
            <x-slot:table>
                <x-table flush>
                <thead>
                <tr><th class="w-10"><x-select-toggle all /></th>
                <th>World</th>
                <th>Path</th>
                <th>Size</th>
                <th>Last Played</th>
                <th class="text-right vx-act-2">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($worlds as $world)
                <tr>
                <td class="w-10"><x-select-toggle :value="$world->id" :label="$world->name" /></td>
                <td>
                <span class="font-medium text-slate-900">{{ $world->name }}</span>
                @if ($world->is_active)<x-badge color="success" class="ml-1.5" dot>Active</x-badge>@endif
                @if ($world->seed)<span class="block font-mono text-xs text-slate-400">seed {{ $world->seed }}</span>@endif
                </td>
                <td class="font-mono text-xs text-slate-500">{{ $world->path }}</td>
                <td class="tabular text-slate-500">{{ \App\Support\Format::bytes($world->bytes) }}</td>
                <td class="text-slate-500 text-xs">{{ $world->last_played_at?->diffForHumans() }}</td>
                <td class="text-right vx-act-2">
                <div class="inline-flex items-center gap-1">
                @can('check', [$server, 'world.switch'])
                @unless ($world->is_active)
                <x-confirm-action
                name="activate-world-{{ $world->id }}"
                :action="route('server.worlds.activate', [$server, $world])"
                tone="warn"
                title="Switch To {{ $world->name }}?"
                message="The server has to be stopped first. Swapping a world underneath a running server corrupts it."
                confirm="Make It Active">
                <x-icon-button icon="check-circle" title="Make This The Active World" />
                </x-confirm-action>
                @endunless
                @endcan
                @can('check', [$server, 'world.delete'])
                @unless ($world->is_active)
                <x-delete-button
                name="delete-world-{{ $world->id }}"
                :action="route('server.worlds.destroy', [$server, $world])"
                title="Delete {{ $world->name }}?"
                message="The whole world directory is removed from the node. If it is not in a backup, it is gone."
                confirm="Delete World"
                label="Delete World" />
                @endunless
                @endcan
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="delete" icon="trash" tone="danger" confirm="The whole world directories are removed from the node. If they are not in a backup, they are gone. The active world is skipped." confirm-title="Delete These Worlds?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
