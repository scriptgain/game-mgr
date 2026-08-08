<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <div x-data="gameConsole({
            streamUrl: @js($streamUrl),
            pollUrl: @js(route('server.stats', $server)),
            backlog: @js($backlog),
            memory: {{ (int) $server->memory }},
            cpuLimit: {{ (int) $server->cpu }},
            state: @js($server->power_state),
            status: @js($server->status)
         })" class="grid gap-6 lg:grid-cols-4">

        {{-- min-w-0 on both columns: a grid item defaults to min-width:auto, so
             the Connect card's input set a 341px floor for the whole grid and
             the page scrolled sideways at 320. --}}
        <div class="lg:col-span-3 space-y-4 min-w-0">
            {{-- Above the console: while a server is installing there is no game
                 output to read, and the install is the only thing happening. --}}
            <x-install-progress :server="$server" />
            <x-live-console :server="$server" />
        </div>

        <div class="space-y-4 min-w-0">
            <x-card title="Power" icon="power">
                <div class="grid grid-cols-2 gap-2">
                    @can('check', [$server, 'control.start'])
                        <form method="POST" action="{{ route('server.power', $server) }}">
                            @csrf<input type="hidden" name="action" value="start">
                            <x-button type="submit" icon="play" class="w-full" :disabled="! $server->canStart()">Start</x-button>
                        </form>
                    @endcan
                    @can('check', [$server, 'control.restart'])
                        {{-- A restart disconnects everyone currently playing, so it
                             asks first, the same way Kill does. --}}
                        <x-confirm-action
                            name="restart-server"
                            :action="route('server.power', $server)"
                            method="POST"
                            tone="warn"
                            title="Restart The Server?"
                            message="Everyone playing right now will be disconnected. The world is saved first, so nothing is lost, but players will have to rejoin."
                            confirm="Restart It"
                            :fields="['action' => 'restart']"
                            class="w-full">
                            <button type="button" @disabled(! $server->canRestart())
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-slate-700 bg-white ring-1 ring-inset ring-slate-200 hover:bg-slate-50 hover:ring-slate-400 transition disabled:opacity-50 disabled:pointer-events-none">
                                <x-icon name="refresh" class="w-4 h-4" /> Restart
                            </button>
                        </x-confirm-action>
                    @endcan
                    @can('check', [$server, 'control.stop'])
                        <form method="POST" action="{{ route('server.power', $server) }}">
                            @csrf<input type="hidden" name="action" value="stop">
                            <x-button type="submit" variant="secondary" icon="stop" class="w-full" :disabled="! $server->canStop()">Stop</x-button>
                        </form>
                        <x-confirm-action
                            name="kill-server"
                            :action="route('server.power', $server)"
                            method="POST"
                            tone="danger"
                            title="Kill The Server?"
                            message="Kill pulls the plug without letting the game save. Anything since the last autosave is lost. Use Stop unless the server has stopped responding entirely."
                            confirm="Kill It"
                            confirm-variant="danger"
                            :fields="['action' => 'kill']"
                            class="w-full">
                            <button type="button" @disabled(! $server->canKill())
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-rose-700 bg-white ring-1 ring-inset ring-rose-200 hover:bg-rose-50 hover:ring-rose-400 transition disabled:opacity-50 disabled:pointer-events-none">
                                <x-icon name="bolt-slash" class="w-4 h-4" /> Kill
                            </button>
                        </x-confirm-action>
                    @endcan
                </div>
            </x-card>

            {{-- Connect and Resources were two cards. They are one now: same
                 information, one header and one set of padding instead of two,
                 which is most of what made this column scroll. --}}
            <x-card title="Connect" icon="link">
                {{-- Two addresses, never one. The name is easier to hand out,
                     the direct address depends on nothing and always works, so
                     both are here and neither replaces the other. --}}
                @if ($server->connectAddress())
                    <div class="space-y-3">
                        <x-copy-field :value="$server->connectAddress()" label="Connect" />
                        <x-copy-field :value="$server->address()" label="Direct" />
                    </div>
                @else
                    <x-copy-field :value="$server->address()" label="Address" />
                @endif
                <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-2">
                        <p class="text-slate-500">Runtime</p>
                        <p class="mt-1 flex justify-center"><x-runtime-badge :runtime="$server->runtime" compact /></p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 min-w-0">
                        <p class="text-slate-500">Node</p>
                        <p class="mt-1 truncate font-medium text-slate-900">{{ $server->node?->name }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 min-w-0">
                        <p class="text-slate-500">Location</p>
                        <p class="mt-1 truncate font-medium text-slate-900">{{ $server->node?->location?->name }}</p>
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-2 border-t border-slate-100 pt-4">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-500">
                        <x-icon name="chart" class="w-3.5 h-3.5" />
                    </span>
                    <h3 class="text-[15px] font-semibold text-slate-900">Resources</h3>
                </div>

                <div class="mt-3 space-y-3">
                    <div>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="font-medium text-slate-700">CPU</span>
                            <span class="tabular text-xs text-slate-500"><span x-text="Math.round(stats.cpu * 10) / 10"></span>% of {{ $server->cpu }}%</span>
                        </div>
                        <div class="mt-1.5 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-brand-500 transition-all" :style="`width: ${cpuPercent()}%`"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="font-medium text-slate-700">Memory</span>
                            <span class="tabular text-xs text-slate-500" x-text="formatMib(stats.memory_mib) + ' / ' + formatMib({{ (int) $server->memory }})"></span>
                        </div>
                        <div class="mt-1.5 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full transition-all"
                                 :class="memoryPercent() >= 90 ? 'bg-rose-500' : (memoryPercent() >= 75 ? 'bg-amber-500' : 'bg-brand-500')"
                                 :style="`width: ${memoryPercent()}%`"></div>
                        </div>
                    </div>
                    <x-meter label="Disk" :value="$server->cached_disk" :max="$server->disk">
                        {{ \App\Support\Format::mibPair($server->cached_disk, $server->disk) }}
                    </x-meter>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-700">Players</span>
                        <span class="tabular font-medium text-slate-900"><span x-text="stats.players"></span> / <span x-text="stats.max_players || '0'"></span></span>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.app>
