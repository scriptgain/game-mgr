<x-layouts.app :title="$title">
    <x-page-header title="Alerts" icon="warning" subtitle="What the watchdog and the node poller found.">
        <x-slot:actions>
            @if ($open->isNotEmpty())
                <form method="POST" action="{{ route('admin.alerts.ack-all') }}">
                    @csrf<x-button type="submit" variant="secondary" size="sm" icon="check">Acknowledge All</x-button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-6">
        <x-mass-actions :action="route('admin.bulk', 'alerts')" label="alert">
            <x-slot:table>
                <x-card title="Open" :subtitle="$open->count().' '.Str::plural('alert', $open->count()).' waiting'" flush>
                    @if ($open->isEmpty())
                        <x-empty-state icon="check-circle" title="Nothing Outstanding"
                                       description="Every alert has been acknowledged. The fleet is quiet." />
                    @else
                        <x-table flush>
                            <thead>
                                <tr>
                                    <th class="w-10"><x-select-toggle all /></th>
                                    <th>Alert</th>
                                    <th>Where</th>
                                    <th>Raised</th>
                                    <th class="text-right vx-act-1">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($open as $alert)
                                    <tr>
                                        <td class="w-10"><x-select-toggle :value="$alert->id" :label="$alert->title" /></td>
                                        <td class="vx-cell-wrap">
                                            <span class="inline-flex items-center gap-2">
                                                <x-status-dot :tone="$alert->tone()" label="" pulse />
                                                <span class="font-medium text-slate-900">{{ $alert->title }}</span>
                                            </span>
                                            @if ($alert->detail)<span class="block text-sm text-slate-600">{{ $alert->detail }}</span>@endif
                                        </td>
                                        <td class="text-slate-500">
                                            @if ($alert->server)
                                                <a href="{{ route('admin.servers.show', $alert->server) }}" class="text-brand-700 hover:text-brand-800">{{ $alert->server->name }}</a>
                                            @elseif ($alert->node)
                                                <a href="{{ route('admin.nodes.show', $alert->node) }}" class="text-brand-700 hover:text-brand-800">{{ $alert->node->name }}</a>
                                            @else
                                                Panel
                                            @endif
                                        </td>
                                        <td class="text-slate-500 text-xs">{{ $alert->created_at->diffForHumans() }}</td>
                                        <td class="text-right vx-act-1">
                                            <x-icon-button icon="check" title="Acknowledge" @click.prevent="actOn('acknowledge', $event)" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table>
                    @endif
                </x-card>
            </x-slot:table>

            <x-mass-action action="acknowledge" icon="check" tone="brand">Acknowledge</x-mass-action>
            <x-mass-action action="delete" icon="trash" tone="danger"
                           confirm="Deleting an alert removes the record of it entirely. Acknowledging is usually what you want."
                           confirm-title="Delete These Alerts?">Delete</x-mass-action>
        </x-mass-actions>

        <x-card title="Acknowledged" subtitle="The last 25, for context." flush>
            @if ($recent->isEmpty())
                <p class="px-5 py-4 text-sm text-slate-500">Nothing acknowledged yet.</p>
            @else
                <x-table flush>
                    <thead><tr><th>Alert</th><th>Where</th><th>Raised</th><th>Acknowledged</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $alert)
                            <tr>
                                <td class="vx-cell-wrap">
                                    <span class="font-medium text-slate-900">{{ $alert->title }}</span>
                                </td>
                                <td class="text-slate-500">{{ $alert->server?->name ?? $alert->node?->name ?? 'Panel' }}</td>
                                <td class="text-slate-500 text-xs">{{ $alert->created_at->diffForHumans() }}</td>
                                <td class="text-slate-500 text-xs">{{ $alert->acknowledged_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif
        </x-card>
    </div>
</x-layouts.app>
