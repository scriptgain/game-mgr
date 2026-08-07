<x-layouts.app :title="$title">
    <x-page-header title="Fleet Overview" icon="dashboard"
                   subtitle="Every node and every server, in one place.">
        <x-slot:actions>
            <x-button href="{{ route('admin.nodes.create') }}" variant="secondary" icon="server" size="sm">Add Node</x-button>
            <x-button href="{{ route('admin.servers.create') }}" icon="plus" size="sm">New Server</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Servers" :value="$counts['total']" icon="server"
                :trend="$counts['running'].' running'" trend-color="success" />
        <x-stat label="Players Online" :value="$counts['players']" icon="user-group" />
        <x-stat label="Nodes Online" :value="$counts['nodesOnline'].' / '.$counts['nodesTotal']" icon="cloud"
                :trend="$counts['nodesOnline'] === $counts['nodesTotal'] ? 'All healthy' : 'Check nodes'"
                :trend-color="$counts['nodesOnline'] === $counts['nodesTotal'] ? 'success' : 'danger'" />
        <x-stat label="Need Attention" :value="$counts['attention']" icon="warning"
                :trend="$counts['attention'] ? 'Installing, failed or suspended' : 'Nothing outstanding'"
                :trend-color="$counts['attention'] ? 'danger' : 'success'" />
    </div>

    @if ($alerts->isNotEmpty())
        <div class="mt-6">
            <x-card title="Open Alerts" subtitle="Nothing here is being acted on until somebody acknowledges it." flush>
                <x-slot:actions>
                    <form method="POST" action="{{ route('admin.alerts.ack-all') }}">
                        @csrf
                        <x-button type="submit" variant="secondary" size="sm" icon="check">Acknowledge All</x-button>
                    </form>
                </x-slot:actions>
                <x-mass-actions :action="route('admin.bulk', 'alerts')" label="alert">
                    <x-slot:table>
                        <x-table flush>
                            <thead>
                                <tr>
                                    <th class="w-10"><x-select-toggle all /></th>
                                    <th>Alert</th>
                                    <th>Raised</th>
                                    <th class="text-right vx-act-1">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($alerts as $alert)
                                    <tr>
                                        <td class="w-10"><x-select-toggle :value="$alert->id" :label="$alert->title" /></td>
                                        <td class="vx-cell-wrap">
                                            <span class="inline-flex items-center gap-2">
                                                <x-status-dot :tone="$alert->tone()" label="" pulse />
                                                <span class="font-medium text-slate-900">{{ $alert->title }}</span>
                                            </span>
                                            <span class="block text-xs text-slate-500">{{ $alert->detail }}</span>
                                        </td>
                                        <td class="text-slate-500 text-xs">{{ $alert->created_at->diffForHumans() }}</td>
                                        <td class="text-right vx-act-1">
                                            <x-icon-button icon="check" title="Acknowledge"
                                                           @click.prevent="actOn('acknowledge', $event)" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table>
                    </x-slot:table>

                    <x-mass-action action="acknowledge" icon="check" tone="brand">Acknowledge</x-mass-action>
                    <x-mass-action action="delete" icon="trash" tone="danger"
                                   confirm="Deleting an alert removes the record of it entirely. Acknowledging is usually what you want."
                                   confirm-title="Delete These Alerts?">Delete</x-mass-action>
                </x-mass-actions>
            </x-card>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Nodes" subtitle="Capacity is memory promised to servers against what the node can allocate." flush>
                <x-slot:actions>
                    <x-button href="{{ route('admin.nodes.index') }}" variant="ghost" size="sm">See All</x-button>
                </x-slot:actions>
                <x-table flush>
                    <thead>
                        <tr>
                            <th>Node</th>
                            <th>Location</th>
                            <th>Runtimes</th>
                            <th>Servers</th>
                            <th class="vx-cell-wrap">Memory</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($nodes as $node)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.nodes.show', $node) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $node->name }}</a>
                                </td>
                                <td class="text-slate-500">{{ $node->location?->flag }} {{ $node->location?->name }}</td>
                                <td class="vx-cell-wrap">
                                    <span class="flex flex-wrap items-center gap-1">
                                        @foreach ($node->runtimes ?? [] as $runtime)
                                            <x-runtime-badge :runtime="$runtime" compact />
                                        @endforeach
                                    </span>
                                </td>
                                <td class="tabular">{{ $node->servers_count }}</td>
                                <td class="vx-cell-wrap">
                                    <x-meter :value="$node->memoryAllocated()" :max="$node->memoryCapacity()">
                                        {{ $node->memoryPressure() }}%
                                    </x-meter>
                                </td>
                                <td><x-status-dot :tone="$node->statusTone()" :label="$node->statusLabel()" :pulse="$node->isOnline()" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            </x-card>

            <x-card title="Servers" subtitle="Sorted by the ones most likely to need you." flush>
                <x-slot:actions>
                    <x-button href="{{ route('admin.servers.index') }}" variant="ghost" size="sm">See All</x-button>
                </x-slot:actions>
                @if ($servers->isEmpty())
                    <x-empty-state icon="server" title="No Servers Yet"
                                   description="Add a node, then create your first server on it.">
                        <x-slot:action>
                            <x-button href="{{ route('admin.servers.create') }}" icon="plus">New Server</x-button>
                        </x-slot:action>
                    </x-empty-state>
                @else
                    <x-table flush>
                        <thead>
                            <tr>
                                <th>Server</th>
                                <th>Owner</th>
                                <th>Node</th>
                                <th>Players</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($servers->sortBy(fn ($s) => [$s->status === null, $s->power_state === 'running'])->take(10) as $server)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.servers.show', $server) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $server->name }}</a>
                                        <span class="block text-xs text-slate-400">{{ $server->template?->game?->name }} &middot; {{ $server->template?->name }}</span>
                                    </td>
                                    <td class="text-slate-500">{{ $server->owner?->name }}</td>
                                    <td class="text-slate-500">{{ $server->node?->name }}</td>
                                    <td class="tabular">{{ $server->cached_players }}</td>
                                    <td><x-status-dot :tone="$server->statusTone()" :label="$server->statusLabel()" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Runtime Mix" subtitle="Docker is not the only way to run a game server.">
                <div class="space-y-4">
                    @forelse ($runtimes as $runtime => $count)
                        <div class="flex items-center justify-between gap-3">
                            <x-runtime-badge :runtime="$runtime" />
                            <span class="tabular text-sm font-medium text-slate-900">{{ $count }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No servers yet.</p>
                    @endforelse
                </div>
            </x-card>

            <x-card title="Recent Activity" flush>
                <x-slot:actions>
                    <x-button href="{{ route('settings.audit.index') }}" variant="ghost" size="sm">Audit Log</x-button>
                </x-slot:actions>
                <ul class="divide-y divide-slate-100">
                    @forelse ($activity as $entry)
                        <li class="px-5 py-3 flex items-start gap-3">
                            <span class="mt-1.5"><x-status-dot :tone="$entry->tone()" label="" /></span>
                            <div class="min-w-0">
                                <p class="text-sm text-slate-800">{{ $entry->description }}</p>
                                <p class="text-xs text-slate-400">{{ $entry->user?->name ?? 'System' }} &middot; {{ $entry->created_at->diffForHumans() }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-sm text-slate-500">Nothing recorded yet.</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</x-layouts.app>
