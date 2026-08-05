<x-layouts.app :title="$title">
    <x-page-header title="Notification Channels" icon="bell" subtitle="Where alerts go when something needs a human.">
        <x-slot:actions><x-button href="{{ route('admin.channels.create') }}" icon="plus" size="sm">New Channel</x-button></x-slot:actions>
    </x-page-header>

    <x-card flush>
        @if ($channels->isEmpty())
            <x-empty-state icon="bell" title="No Channels"
                           description="A watchdog rule with nowhere to shout is only half a rule.">
                <x-slot:action><x-button href="{{ route('admin.channels.create') }}" icon="plus">New Channel</x-button></x-slot:action>
            </x-empty-state>
        @else
            <x-mass-actions :action="route('admin.bulk', 'channels')" label="channel">
            <x-slot:table>
                <x-table flush>
                <thead><tr><th class="w-10"><x-select-toggle all /></th><th>Channel</th><th>Type</th><th>Target</th><th>Events</th><th>Last Used</th><th class="text-right vx-act-3">Actions</th></tr></thead>
                <tbody>
                @foreach ($channels as $channel)
                <tr>
                <td class="w-10"><x-select-toggle :value="$channel->id" :label="$channel->name" /></td>
                <td>
                <span class="font-medium text-slate-900">{{ $channel->name }}</span>
                @unless ($channel->is_active)<x-badge color="neutral" class="ml-1.5">Off</x-badge>@endunless
                </td>
                <td><x-badge color="neutral">{{ $channel->typeLabel() }}</x-badge></td>
                <td class="font-mono text-xs text-slate-500">{{ $channel->maskedTarget() }}</td>
                <td class="tabular text-slate-500">{{ count($channel->events ?? []) }}</td>
                <td class="text-slate-500 text-xs">{{ $channel->last_used_at?->diffForHumans() ?? 'never' }}</td>
                <td class="text-right vx-act-3">
                <div class="inline-flex items-center gap-1">
                <form method="POST" action="{{ route('admin.channels.test', $channel) }}">
                @csrf<x-icon-button type="submit" icon="bell" title="Send A Test Message" />
                </form>
                <x-icon-button href="{{ route('admin.channels.edit', $channel) }}" icon="edit" title="Edit Channel" />
                <x-delete-button
                name="delete-channel-{{ $channel->id }}"
                :action="route('admin.channels.destroy', $channel)"
                title="Delete {{ $channel->name }}?"
                message="Any watchdog rule pointing at it stops notifying anybody."
                label="Delete Channel" />
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="enable" icon="check">Enable</x-mass-action>
            <x-mass-action action="disable" icon="x-circle">Disable</x-mass-action>
            <x-mass-action action="delete" icon="trash" tone="danger" confirm="Any watchdog rule pointing at them stops notifying anybody." confirm-title="Delete These Channels?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
