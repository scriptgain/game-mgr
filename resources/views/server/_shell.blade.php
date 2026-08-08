{{-- Shared chrome for every per-server screen: the header with power state and
     connect address, then the tab strip. Every server view opens with this so
     the page identity never depends on which tab you are on. --}}
@props(['server'])
@php
    // Which Minecraft build this server is set to, when it is one. Shown in the
    // header so an operator reads it off any tab without opening a form.
    $minecraft = $server->minecraft();
@endphp
<x-page-header :title="$server->name" icon="server">
    <x-slot:subtitle>
        {{ $server->template?->game?->name }} &middot; {{ $server->template?->name }} &middot; {{ $server->node?->name }}
        @if ($minecraft)
            &middot; <span class="font-medium text-slate-600">{{ \Illuminate\Support\Str::headline(mb_strtolower($minecraft['type'])) }}
                {{ $minecraft['version'] }}@if ($minecraft['build']) build {{ $minecraft['build'] }}@endif</span>
        @endif
    </x-slot:subtitle>
    <x-slot:actions>
        {{-- The name first when there is one, with the direct address under it.
             Never the name alone: the direct address is the one that works when
             everything else does not. --}}
        <span class="hidden sm:inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-inset ring-slate-200">
            <x-icon name="network" class="w-4 h-4 text-slate-400" />
            <span class="min-w-0">
                @if ($server->connectAddress())
                    <span class="block font-mono text-xs text-slate-900 truncate">{{ $server->connectAddress() }}</span>
                    <span class="block font-mono text-[11px] text-slate-500 truncate">{{ $server->address() }}</span>
                @else
                    <span class="font-mono text-xs text-slate-700">{{ $server->address() }}</span>
                @endif
            </span>
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
