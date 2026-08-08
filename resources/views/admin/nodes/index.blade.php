<x-layouts.app :title="$title">
    <x-page-header title="Nodes" icon="server"
                   subtitle="One panel, nodes anywhere. Every machine here can be a VPS, a dedicated box, or a spare PC at home.">
        <x-slot:actions>
            <x-button href="{{ route('admin.nodes.create') }}" icon="plus" size="sm">Add Node</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($nodes->isEmpty())
        <x-card>
            <x-empty-state icon="server" title="No Nodes Yet"
                           description="Add a node, run the one-line install on the machine, and it appears here with its real capacity.">
                <x-slot:action><x-button href="{{ route('admin.nodes.create') }}" icon="plus">Add Node</x-button></x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($nodes as $node)
                <div class="bg-white rounded-xl ring-1 ring-slate-200 shadow-sm p-5 border border-transparent hover:border-brand-300 transition">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('admin.nodes.show', $node) }}" class="font-semibold text-slate-900 hover:text-brand-700 transition truncate block">{{ $node->name }}</a>
                            <p class="text-xs text-slate-500 truncate">
                                {{ $node->location?->flag }} {{ $node->location?->name }}
                                @if ($node->fqdn) &middot; <span class="font-mono">{{ $node->fqdn }}:{{ $node->daemon_port }}</span> @endif
                            </p>
                        </div>
                        <x-status-dot :tone="$node->statusTone()" :label="$node->statusLabel()" :pulse="$node->isOnline()" />
                    </div>

                    <div class="mt-3 flex items-center gap-1.5 flex-wrap">
                        @foreach ($node->runtimes ?? [] as $runtime)
                            <x-runtime-badge :runtime="$runtime" />
                        @endforeach
                        @if ($node->connection_mode === 'reverse')
                            <x-badge color="warn"><x-icon name="link" class="w-3.5 h-3.5" /> Reverse</x-badge>
                        @endif
                        @unless ($node->public)
                            <x-badge color="neutral">Private</x-badge>
                        @endunless
                    </div>

                    <div class="mt-4 space-y-3">
                        <x-meter label="Memory Allocated" :value="$node->memoryAllocated()" :max="$node->memoryCapacity()">
                            {{ \App\Support\Format::mib($node->memoryAllocated()) }} / {{ \App\Support\Format::mib($node->memoryCapacity()) }}
                        </x-meter>
                        <x-meter label="Disk Allocated" :value="$node->diskAllocated()" :max="$node->diskCapacity()">
                            {{ \App\Support\Format::mib($node->diskAllocated()) }} / {{ \App\Support\Format::mib($node->diskCapacity()) }}
                        </x-meter>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3 text-sm">
                        <span class="text-slate-500">{{ $node->servers_count }} {{ Str::plural('server', $node->servers_count) }} &middot; {{ $node->allocations_count }} ports</span>
                        <div class="flex items-center gap-1">
                            <x-icon-button href="{{ route('admin.nodes.show', $node) }}" icon="eye" title="Open Node" />
                            <x-icon-button href="{{ route('admin.nodes.allocations', $node) }}" icon="network" title="Allocations" />
                            <x-icon-button href="{{ route('admin.nodes.enroll', $node) }}" icon="key" title="Enroll Command" />
                            <x-icon-button href="{{ route('admin.nodes.edit', $node) }}" icon="edit" title="Edit Node" />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
