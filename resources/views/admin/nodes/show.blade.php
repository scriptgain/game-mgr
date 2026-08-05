<x-layouts.app :title="$title">
    <x-page-header :title="$node->name" icon="server" :subtitle="$node->description">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.nodes.check', $node) }}">
                @csrf<x-button type="submit" variant="secondary" size="sm" icon="refresh">Check Now</x-button>
            </form>
            <x-status-dot :tone="$node->statusTone()" :label="$node->statusLabel()" :pulse="$node->isOnline()"
                          class="rounded-lg bg-white px-3 py-2 ring-1 ring-inset ring-slate-200" />
        </x-slot:actions>
    </x-page-header>

    @include('admin.nodes._tabs', ['node' => $node])

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Capacity" subtitle="Allocated is what has been promised to servers, not what is in use right now.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-meter label="Memory" :value="$node->memoryAllocated()" :max="$node->memoryCapacity()">
                        {{ \App\Support\Format::mib($node->memoryAllocated()) }} / {{ \App\Support\Format::mib($node->memoryCapacity()) }}
                    </x-meter>
                    <x-meter label="Disk" :value="$node->diskAllocated()" :max="$node->diskCapacity()">
                        {{ \App\Support\Format::mib($node->diskAllocated()) }} / {{ \App\Support\Format::mib($node->diskCapacity()) }}
                    </x-meter>
                </div>
                <dl class="mt-5 grid gap-3 sm:grid-cols-3 text-sm">
                    <div><dt class="text-slate-500">Over-allocation</dt><dd class="text-slate-900 tabular">memory {{ $node->memory_overallocate }}%, disk {{ $node->disk_overallocate }}%</dd></div>
                    <div><dt class="text-slate-500">Free Ports</dt><dd class="text-slate-900 tabular">{{ $freePorts }} of {{ $node->allocations_count }}</dd></div>
                    <div><dt class="text-slate-500">Servers</dt><dd class="text-slate-900 tabular">{{ $node->servers_count }}</dd></div>
                </dl>
            </x-card>

            <x-card title="Servers On This Node" flush>
                @if ($servers->isEmpty())
                    <x-empty-state icon="server" title="Nothing Placed Here Yet"
                                   description="This node has capacity but no servers on it." />
                @else
                    <x-table flush>
                        <thead><tr><th>Server</th><th>Owner</th><th>Template</th><th>Memory</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($servers as $server)
                                <tr>
                                    <td><a href="{{ route('admin.servers.show', $server) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $server->name }}</a></td>
                                    <td class="text-slate-500">{{ $server->owner?->name }}</td>
                                    <td class="text-slate-500">{{ $server->template?->name }}</td>
                                    <td class="tabular text-slate-500">{{ \App\Support\Format::mib($server->memory) }}</td>
                                    <td><x-status-dot :tone="$server->statusTone()" :label="$server->statusLabel()" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Daemon">
                @if ($system)
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Version</dt><dd class="text-slate-900">{{ $system['version'] ?? 'unknown' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Uptime</dt><dd class="text-slate-900 tabular">{{ \App\Support\Format::duration($system['uptime_sec'] ?? 0) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Data Root</dt><dd class="font-mono text-xs text-slate-700 truncate">{{ $system['root'] ?? '' }}</dd></div>
                    </dl>
                    @if (! empty($system['drivers']))
                        <div class="mt-4 space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Runtimes Available</p>
                            @foreach ($system['drivers'] as $name => $driver)
                                <div class="flex items-start justify-between gap-3 text-sm">
                                    <x-runtime-badge :runtime="$name" />
                                    <span class="text-xs text-right {{ ($driver['available'] ?? false) ? 'text-emerald-600' : 'text-slate-400' }}">
                                        {{ $driver['detail'] ?? '' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if (! empty($system['forced_driver']))
                        <div class="mt-4">
                            <x-alert type="warn" title="Stub Driver Active">
                                This node reports synthetic data and is not running anything real. That is expected on a
                                development stack and must never be true in production.
                            </x-alert>
                        </div>
                    @endif
                @else
                    <x-alert type="danger" title="No Answer">
                        The daemon at {{ $node->daemonUrl() }} did not respond. Servers on this node cannot be controlled
                        until it comes back.
                    </x-alert>
                @endif
            </x-card>

            <x-card title="Reported By The Machine">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">OS</dt><dd class="text-slate-900 truncate">{{ $node->reported_os ?: 'not reported' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Kernel</dt><dd class="font-mono text-xs text-slate-700 truncate">{{ $node->reported_kernel ?: 'not reported' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">CPU Cores</dt><dd class="text-slate-900 tabular">{{ $node->reported_cpu_cores ?: 'not reported' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Physical Memory</dt><dd class="text-slate-900 tabular">{{ $node->reported_memory ? \App\Support\Format::mib($node->reported_memory) : 'not reported' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Docker</dt><dd class="text-slate-900">{{ $node->reported_docker ?: 'not reported' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Last Heartbeat</dt><dd class="text-slate-900">{{ $node->last_seen_at?->diffForHumans() ?? 'never' }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">Shown for reference only. Limits come from the configuration, never from what the node claims.</p>
            </x-card>
        </div>
    </div>
</x-layouts.app>
