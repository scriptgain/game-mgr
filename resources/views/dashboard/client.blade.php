<x-layouts.app :title="$title">
    <x-page-header title="My Servers" icon="server"
                   subtitle="{{ $servers->count() }} {{ Str::plural('server', $servers->count()) }} you can reach." />

    @if ($servers->isEmpty())
        <x-card>
            <x-empty-state icon="server" title="Nothing Here Yet"
                           description="You do not own a server and nobody has shared one with you. An administrator can set one up." />
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($servers as $server)
                <a href="{{ route('server.console', $server) }}"
                   class="group block bg-white rounded-xl ring-1 ring-slate-200 shadow-sm p-5 border border-transparent hover:border-brand-300 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-slate-900 truncate group-hover:text-brand-700 transition">{{ $server->name }}</h3>
                            <p class="text-xs text-slate-500 truncate">{{ $server->template?->game?->name }} &middot; {{ $server->template?->name }}</p>
                        </div>
                        <x-status-dot :tone="$server->statusTone()" :label="$server->statusLabel()" :pulse="$server->power_state === 'running'" />
                    </div>

                    <div class="mt-4 flex items-center gap-2 flex-wrap">
                        <x-runtime-badge :runtime="$server->runtime" />
                        @if (in_array($server->id, $sharedIds, true))
                            <x-badge color="neutral"><x-icon name="users" class="w-3.5 h-3.5" /> Shared With You</x-badge>
                        @endif
                    </div>

                    <dl class="mt-4 space-y-3">
                        <div class="flex items-center justify-between text-sm">
                            <dt class="text-slate-500">Address</dt>
                            <dd class="font-mono text-xs text-slate-700 truncate max-w-[60%]">{{ $server->address() }}</dd>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <dt class="text-slate-500">Players</dt>
                            <dd class="tabular text-slate-900 font-medium">{{ $server->cached_players }} / {{ $server->cached_max_players ?: 'n/a' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 space-y-2.5">
                        <x-meter label="Memory" :value="$server->cached_memory" :max="$server->memory">
                            {{ number_format($server->cached_memory) }} / {{ number_format($server->memory) }} MiB
                        </x-meter>
                        <x-meter label="Disk" :value="$server->cached_disk" :max="$server->disk">
                            {{ $server->diskPercent() }}%
                        </x-meter>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
