{{-- Shared chrome for every per-server screen: the header with power state and
     connect address, then the tab strip. Every server view opens with this so
     the page identity never depends on which tab you are on. --}}
@props(['server'])
<x-page-header :title="$server->name" icon="server">
    <x-slot:subtitle>
        {{ $server->template?->game?->name }} &middot; {{ $server->template?->name }} &middot; {{ $server->node?->name }}
    </x-slot:subtitle>
    <x-slot:actions>
        <span class="hidden sm:inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-inset ring-slate-200">
            <x-icon name="network" class="w-4 h-4 text-slate-400" />
            <span class="font-mono text-xs text-slate-700">{{ $server->address() }}</span>
        </span>
        <x-status-dot :tone="$server->statusTone()" :label="$server->statusLabel()" :pulse="$server->power_state === 'running'"
                      class="rounded-lg bg-white px-3 py-2 ring-1 ring-inset ring-slate-200" />
    </x-slot:actions>
</x-page-header>

@if ($server->isSuspended())
    <div class="mb-6">
        <x-alert type="warn">
            This server is suspended. Files and backups are untouched, but nothing can be started or changed until an administrator lifts it.
        </x-alert>
    </div>
@elseif ($server->isInstalling())
    <div class="mb-6">
        <x-alert type="info">
            This server is installing. Controls come back on once the node reports it finished.
        </x-alert>
    </div>
@elseif ($server->status === 'install_failed')
    <div class="mb-6">
        <x-alert type="danger">
            The install failed. An administrator can retry it from the admin area, or check the node for space and permissions.
        </x-alert>
    </div>
@endif

<x-server-tabs :server="$server" />
