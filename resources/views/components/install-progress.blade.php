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

    // The node stops waiting after ten minutes. Aged out here as well, so a box
    // that can no longer be answered stops being offered: taking a code that
    // has nowhere to go is worse than showing none.
    $awaitingGuard = $installing
        && $server->guard_prompt_at
        && $server->guard_prompt_at->gt(now()->subMinutes(10));
@endphp

@if ($installing || $failed)
    <x-card :title="$failed ? 'Install Failed' : 'Installing'" icon="download"
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

        <div class="space-y-4"
             @if ($installing)
                 data-install-watch="3"
                 data-progress-url="{{ route('admin.servers.install-progress', $server) }}"
             @endif>
            @if ($awaitingGuard)
                <div class="rounded-lg bg-amber-50 p-4 ring-1 ring-inset ring-amber-200">
                    <div class="flex gap-3">
                        <x-icon name="shield" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                        <div class="min-w-0 flex-1 space-y-3">
                            <div>
                                <p class="text-sm font-medium text-amber-900">Steam Is Asking For A Guard Code</p>
                                <p class="mt-1 text-sm text-amber-800">
                                    Open the Steam mobile app and type the five characters it shows. The install is
                                    paused until you do, and stops waiting ten minutes after
                                    {{ $server->guard_prompt_at->format('H:i') }}. You only have to do this once on
                                    this node: Steam trusts the machine afterwards.
                                </p>
                            </div>

                            {{-- Its own form. A submit inside another form is orphaned, and
                                 this card sits on pages that already carry one. --}}
                            <form method="POST" action="{{ route('server.guard-code', $server) }}"
                                  class="flex flex-wrap items-start gap-2">
                                @csrf
                                <div>
                                    <label for="guard-code" class="sr-only">Steam Guard Code</label>
                                    <input type="text" name="code" id="guard-code" maxlength="5" size="8"
                                           autocomplete="one-time-code" autocapitalize="characters"
                                           autocorrect="off" spellcheck="false" required
                                           placeholder="XXXXX"
                                           class="w-28 rounded-lg border-0 px-3 py-2 text-center font-mono text-base uppercase tracking-widest text-slate-900 ring-1 ring-inset ring-amber-300 focus:ring-2 focus:ring-inset focus:ring-amber-500" />
                                </div>
                                <x-button type="submit" size="sm">Send Code</x-button>
                            </form>

                            @error('code')
                                <p class="text-sm font-medium text-rose-700">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex items-baseline justify-between gap-3">
                <span data-install-phase class="text-sm font-medium {{ $failed ? 'text-rose-700' : 'text-slate-700' }}">
                    {{ $phase ?? 'Unknown' }}
                </span>
                <span data-install-percent class="tabular text-sm text-slate-500">
                    @if ($pct !== null)
                        {{ $pct }}%
                    @elseif ($installing)
                        In Progress
                    @endif
                </span>
            </div>

            @if ($pct !== null)
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div data-install-bar
                         class="h-full rounded-full transition-all duration-500 {{ $failed ? 'bg-rose-500' : 'bg-brand-500' }}"
                         style="width: {{ max(0, min(100, $pct)) }}%"></div>
                </div>
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
