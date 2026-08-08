@props(['server'])
{{-- What an install is doing, while it is doing it.

     A server used to sit at "installing" with nothing to look at, because
     nothing was installing: the panel never asked the node to fetch anything.
     Now that it does, an operator watching an 8 GB SteamCMD download gets a
     percentage, a phase, and the tail of the real output.

     The percentage is nullable on purpose. SteamCMD reports a real number;
     a Docker image pull and a LinuxGSM fetch do not, and a bar that is
     guessing is worse than a phase with no bar. --}}
@php
    $installing = $server->status === 'installing';
    $failed = $server->status === 'install_failed';
    $pct = $server->install_progress;
    $phase = $server->install_phase ?: ($installing ? 'Waiting For The Node' : null);
    $log = trim((string) $server->install_log);
@endphp

@if ($installing || $failed)
    <x-card :title="$failed ? 'Install Failed' : 'Installing'"
            :subtitle="$failed
                ? 'The server has no game files yet, so it cannot start. The output below is what the node reported.'
                : 'The node is fetching this game. You can leave this page; it keeps going.'">
        <x-slot:actions>
            @if ($installing)
                <span class="inline-flex items-center gap-2 text-xs text-slate-500">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-400 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-brand-500"></span>
                    </span>
                    Live
                </span>
            @endif
        </x-slot:actions>

        <div class="space-y-4">
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-sm font-medium {{ $failed ? 'text-rose-700' : 'text-slate-700' }}">
                    {{ $phase ?? 'Unknown' }}
                </span>
                <span class="tabular text-sm text-slate-500">
                    @if ($pct !== null)
                        {{ $pct }}%
                    @elseif ($installing)
                        In Progress
                    @endif
                </span>
            </div>

            @if ($pct !== null)
                <x-meter :value="$pct" :max="100" :tone="$failed ? 'rose' : null" />
            @else
                {{-- No number to show, so show motion rather than a bar stuck
                     at zero, which reads as broken. --}}
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full w-1/3 rounded-full {{ $failed ? 'bg-rose-500' : 'bg-brand-500 animate-pulse' }}"></div>
                </div>
            @endif

            @if ($server->install_started_at)
                <p class="text-xs text-slate-500">
                    Started {{ $server->install_started_at->diffForHumans() }}.
                    @if ($installing)
                        Large games can take a while: Palworld is roughly 8 GB and some Source servers are far more.
                    @endif
                </p>
            @endif

            @if ($log !== '')
                <x-code-pane :label="$failed ? 'What The Node Reported' : 'Install Output'" :code="$log" />
            @else
                <p class="text-sm text-slate-500">No output from the node yet.</p>
            @endif

            @if ($failed)
                <x-alert type="warn">
                    Nothing is retried automatically, because retrying a multi gigabyte download
                    on its own turns one failure into several. Fix what the output points at, then
                    reinstall from Settings.
                </x-alert>
            @endif
        </div>
    </x-card>
@endif
