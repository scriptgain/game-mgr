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
            <x-card title="Capacity" icon="chart" subtitle="Allocated is what has been promised to servers, not what is in use right now.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-meter label="Memory" :value="$node->memoryAllocated()" :max="$node->memoryCapacity()">
                        {{ \App\Support\Format::mib($node->memoryAllocated()) }} / {{ \App\Support\Format::mib($node->memoryCapacity()) }}
                    </x-meter>
                    <x-meter label="Disk" :value="$node->diskAllocated()" :max="$node->diskCapacity()">
                        {{ \App\Support\Format::mib($node->diskAllocated()) }} / {{ \App\Support\Format::mib($node->diskCapacity()) }}
                    </x-meter>
                    @if ($node->cpu > 0)
                        {{-- 100 percent is one core, so this reads in cores. A node
                             left at cpu 0 means "not tracked" and shows nothing
                             rather than a meter against a budget nobody set. --}}
                        <x-meter label="CPU" :value="$node->cpuAllocated()" :max="$node->cpuCapacity()">
                            {{ rtrim(rtrim(number_format($node->cpuAllocated() / 100, 1), '0'), '.') }} /
                            {{ rtrim(rtrim(number_format($node->cpuCapacity() / 100, 1), '0'), '.') }} cores
                        </x-meter>
                    @endif
                </div>
                <dl class="mt-5 grid gap-3 sm:grid-cols-3 text-sm">
                    <div><dt class="text-slate-500">Over-allocation</dt><dd class="text-slate-900 tabular">memory {{ $node->memory_overallocate }}%, disk {{ $node->disk_overallocate }}%, cpu {{ $node->cpu_overallocate }}%</dd></div>
                    <div><dt class="text-slate-500">Free Ports</dt><dd class="text-slate-900 tabular">{{ $freePorts }} of {{ $node->allocations_count }}</dd></div>
                    <div><dt class="text-slate-500">Servers</dt><dd class="text-slate-900 tabular">{{ $node->servers_count }}</dd></div>
                </dl>
            </x-card>

            {{-- One record per node, and whether it is really there. The claim
                 this row makes is "the provider confirmed it", not "we sent it",
                 which is why it carries a checked-at time and the exact error. --}}
            <x-card title="Wildcard Name" icon="globe"
                    subtitle="One record answers for every server on this node, so nothing is created when a server is.">
                <x-slot:actions>
                    @if ($node->dns_label && \App\Services\Dns\DnsConfig::active())
                        <form method="POST" action="{{ route('admin.nodes.wildcard', $node) }}">
                            @csrf<x-button type="submit" variant="secondary" size="sm" icon="sync">Recreate</x-button>
                        </form>
                    @endif
                </x-slot:actions>

                @if (! \App\Services\Dns\DnsConfig::active())
                    <x-alert type="info" title="Connection Names Are Off">
                        Servers on this node show their direct address only, which is exactly how the panel behaves
                        without this feature. Turn names on in
                        <a href="{{ route('settings.domains.edit') }}" class="font-medium underline">Settings, Domains</a>.
                    </x-alert>
                @elseif (! $node->dns_label)
                    <x-alert type="warn" title="This Node Has No Label">
                        A name is built as server.label.zone, so this node needs a label such as lax1 before anything on
                        it can have one. Set it in
                        <a href="{{ route('admin.nodes.edit', $node) }}" class="font-medium underline">Configuration</a>.
                    </x-alert>
                @else
                    <div class="space-y-4">
                        <x-copy-field label="Record" :value="$node->wildcardName().'  A  '.($node->dnsTargetIp() ?: 'no address known')" />

                        <dl class="grid gap-3 sm:grid-cols-3 text-sm">
                            <div class="min-w-0">
                                <dt class="text-slate-500">Record State</dt>
                                <dd class="mt-0.5"><x-status-dot :tone="$node->wildcardTone()" :label="$node->wildcardStatusLabel()" /></dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-slate-500">Last Checked</dt>
                                <dd class="mt-0.5 text-slate-900">{{ $node->wildcard_checked_at?->diffForHumans() ?? 'never' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-slate-500">Proxying</dt>
                                <dd class="mt-0.5 text-slate-900">Never. Grey cloud only.</dd>
                            </div>
                        </dl>

                        @if ($node->wildcard_error)
                            <x-alert type="danger" title="The Record Is Not Confirmed">
                                {{ $node->wildcard_error }}
                                <span class="mt-1 block">Servers here are still reachable on their direct address. The hourly
                                sync will try again, or press Recreate.</span>
                            </x-alert>
                        @endif

                        <p class="text-xs text-slate-500">
                            Game traffic is raw UDP and TCP and cannot pass through a CDN proxy, so this record is always
                            written unproxied and is reported as wrong if it is found proxied.
                        </p>
                    </div>
                @endif
            </x-card>

            <x-card title="Servers On This Node" icon="server" flush>
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
            <x-card title="Daemon" icon="bolt">
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

            <x-card title="Reported By The Machine" icon="cpu">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">OS</dt><dd class="text-slate-900 truncate">{{ $node->reported_os ?: 'not reported' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Kernel</dt><dd class="font-mono text-xs text-slate-700 truncate">{{ $node->reported_kernel ?: 'not reported' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">CPU Cores</dt><dd class="text-slate-900 tabular">{{ $node->reported_cpu_cores ?: 'not reported' }}</dd></div>
                    {{-- Bytes, not MiB. Everything the daemon reports about the
                         machine itself is in bytes, while every limit the panel
                         stores is in MiB. Formatting one with the other's helper
                         is how Physical Memory came to read 793,120 GiB. --}}
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Physical Memory</dt><dd class="text-slate-900 tabular">{{ $node->reported_memory ? \App\Support\Format::bytes($node->reported_memory) : 'not reported' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Physical Disk</dt><dd class="text-slate-900 tabular">{{ $node->reported_disk ? \App\Support\Format::bytes($node->reported_disk) : 'not reported' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Docker</dt><dd class="text-slate-900">{{ $node->reported_docker ?: 'not reported' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Last Heartbeat</dt><dd class="text-slate-900">{{ $node->last_seen_at?->diffForHumans() ?? 'never' }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">Shown for reference only. Limits come from the configuration, never from what the node claims.</p>
            </x-card>
        </div>
    </div>
</x-layouts.app>
