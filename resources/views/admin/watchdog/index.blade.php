<x-layouts.app :title="$title">
    <x-page-header title="Watchdog Rules" icon="shield"
                   subtitle="Pterodactyl restarts a crashed container and stops caring. These rules go considerably further.">
        <x-slot:actions>
            <x-button href="{{ route('admin.watchdog.create') }}" icon="plus" size="sm">New Rule</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card flush>
        @if ($rules->isEmpty())
            <x-empty-state icon="shield" title="No Rules"
                           description="Start with a restart-on-crash rule across every server. It is the one everybody wants.">
                <x-slot:action><x-button href="{{ route('admin.watchdog.create') }}" icon="plus">New Rule</x-button></x-slot:action>
            </x-empty-state>
        @else
            <x-mass-actions :action="route('admin.bulk', 'watchdog')" label="rule">
            <x-slot:table>
                <x-table flush>
                <thead><tr><th class="w-10"><x-select-toggle all /></th><th>Rule</th><th>Applies To</th><th>Trigger</th><th>Action</th><th>Last Fired</th><th class="text-right vx-act-2">Actions</th></tr></thead>
                <tbody>
                @foreach ($rules as $rule)
                <tr>
                <td class="w-10"><x-select-toggle :value="$rule->id" :label="$rule->name" /></td>
                <td>
                <span class="font-medium text-slate-900">{{ $rule->name }}</span>
                @unless ($rule->is_active)<x-badge color="neutral" class="ml-1.5">Paused</x-badge>@endunless
                @if ($rule->pattern)<span class="block font-mono text-xs text-slate-400 truncate">{{ $rule->pattern }}</span>@endif
                </td>
                <td class="text-slate-500">{{ $rule->scopeText() }}</td>
                <td class="text-slate-500">{{ $rule->triggerLabel() }}</td>
                <td>
                <x-badge :color="$rule->action === 'alert' ? 'neutral' : 'warn'">{{ $rule->actionLabel() }}</x-badge>
                </td>
                <td class="text-slate-500 text-xs">{{ $rule->last_fired_at?->diffForHumans() ?? 'never' }}</td>
                <td class="text-right vx-act-2">
                <div class="inline-flex items-center gap-1">
                <x-icon-button href="{{ route('admin.watchdog.edit', $rule) }}" icon="edit" title="Edit Rule" />
                <x-delete-button
                name="delete-rule-{{ $rule->id }}"
                :action="route('admin.watchdog.destroy', $rule)"
                title="Delete {{ $rule->name }}?"
                message="Whatever this rule was keeping on top of stops happening."
                label="Delete Rule" />
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="enable" icon="check">Enable</x-mass-action>
            <x-mass-action action="disable" icon="x-circle">Pause</x-mass-action>
            <x-mass-action action="delete" icon="trash" tone="danger" confirm="Whatever they were keeping on top of stops happening." confirm-title="Delete These Rules?">Delete</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
