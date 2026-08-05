<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <div x-data="gameConsole({
            streamUrl: @js($streamUrl),
            backlog: @js($backlog),
            memory: {{ (int) $server->memory }},
            cpuLimit: {{ (int) $server->cpu }},
            state: @js($server->power_state)
         })" class="grid gap-6 lg:grid-cols-4">

        <div class="lg:col-span-3 space-y-4">
            <x-card flush>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-slate-100">
                    <div class="flex items-center gap-2 text-sm">
                        <x-icon name="terminal" class="w-4 h-4 text-slate-400" />
                        <span class="font-medium text-slate-900">Console</span>
                        <span class="inline-flex items-center gap-1.5 text-xs"
                              :class="connected ? 'text-emerald-600' : 'text-slate-400'">
                            <span class="w-1.5 h-1.5 rounded-full" :class="connected ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                            <span x-text="connected ? 'Live' : 'Reconnecting'"></span>
                        </span>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-slate-500 cursor-pointer">
                        <input type="checkbox" x-model="autoScroll" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Follow Output
                    </label>
                </div>

                <div x-ref="output" @scroll="onScroll()"
                     class="console-pane vx-scroll rounded-none ring-0 h-[26rem] overflow-y-auto px-4 py-3 space-y-0.5">
                    <template x-for="(line, i) in lines" :key="i">
                        <div class="whitespace-pre-wrap break-words"
                             :class="{
                                'text-rose-300': line.includes('ERROR') || line.includes('/SEVERE'),
                                'text-amber-300': line.includes('WARN'),
                                'text-brand-300': line.startsWith('[gamemgr]'),
                             }"
                             x-text="line"></div>
                    </template>
                    <div x-show="!lines.length" class="text-slate-500">Waiting for output from the node.</div>
                </div>

                @can('check', [$server, 'control.command'])
                    <form method="POST" action="{{ route('server.command', $server) }}"
                          @submit="remember()"
                          class="flex items-center gap-2 px-4 py-3 border-t border-slate-100">
                        @csrf
                        <span class="text-slate-400 font-mono text-sm select-none">&gt;</span>
                        <input type="text" name="command" x-model="command" autocomplete="off"
                               @keydown.arrow-up.prevent="recall(1)" @keydown.arrow-down.prevent="recall(-1)"
                               placeholder="Type a command and press Enter"
                               :disabled="stats.state !== 'running'"
                               class="flex-1 min-w-0 rounded-lg border-0 bg-slate-50 px-3 py-2 text-sm font-mono text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500 disabled:opacity-60">
                        <x-button type="submit" size="sm" ::disabled="stats.state !== 'running'">Send</x-button>
                    </form>
                @endcan
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card title="Power">
                <div class="grid grid-cols-2 gap-2">
                    @can('check', [$server, 'control.start'])
                        <form method="POST" action="{{ route('server.power', $server) }}">
                            @csrf<input type="hidden" name="action" value="start">
                            <x-button type="submit" icon="play" class="w-full" :disabled="! $server->isControllable()">Start</x-button>
                        </form>
                    @endcan
                    @can('check', [$server, 'control.restart'])
                        <form method="POST" action="{{ route('server.power', $server) }}">
                            @csrf<input type="hidden" name="action" value="restart">
                            <x-button type="submit" variant="secondary" icon="refresh" class="w-full" :disabled="! $server->isControllable()">Restart</x-button>
                        </form>
                    @endcan
                    @can('check', [$server, 'control.stop'])
                        <form method="POST" action="{{ route('server.power', $server) }}">
                            @csrf<input type="hidden" name="action" value="stop">
                            <x-button type="submit" variant="secondary" icon="stop" class="w-full" :disabled="! $server->isControllable()">Stop</x-button>
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
                            <button type="button" class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-rose-700 bg-white ring-1 ring-inset ring-rose-200 hover:bg-rose-50 hover:ring-rose-400 transition">
                                <x-icon name="bolt-slash" class="w-4 h-4" /> Kill
                            </button>
                        </x-confirm-action>
                    @endcan
                </div>
            </x-card>

            <x-card title="Resources">
                <div class="space-y-4">
                    <div>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="font-medium text-slate-700">CPU</span>
                            <span class="tabular text-slate-500"><span x-text="Math.round(stats.cpu * 10) / 10"></span>% of {{ $server->cpu }}%</span>
                        </div>
                        <div class="mt-1.5 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-brand-500 transition-all" :style="`width: ${cpuPercent()}%`"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="font-medium text-slate-700">Memory</span>
                            <span class="tabular text-slate-500" x-text="formatMib(stats.memory_mib) + ' / ' + formatMib({{ (int) $server->memory }})"></span>
                        </div>
                        <div class="mt-1.5 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full transition-all"
                                 :class="memoryPercent() >= 90 ? 'bg-rose-500' : (memoryPercent() >= 75 ? 'bg-amber-500' : 'bg-brand-500')"
                                 :style="`width: ${memoryPercent()}%`"></div>
                        </div>
                    </div>
                    <x-meter label="Disk" :value="$server->cached_disk" :max="$server->disk">
                        {{ number_format($server->cached_disk) }} / {{ number_format($server->disk) }} MiB
                    </x-meter>
                    <div class="flex items-center justify-between pt-1 text-sm">
                        <span class="font-medium text-slate-700">Players</span>
                        <span class="tabular text-slate-900 font-medium"><span x-text="stats.players"></span> / <span x-text="stats.max_players || '0'"></span></span>
                    </div>
                </div>
            </x-card>

            <x-card title="Connect">
                <x-copy-field :value="$server->address()" label="Address" />
                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Runtime</span>
                        <x-runtime-badge :runtime="$server->runtime" />
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Node</span>
                        <span class="text-slate-900 truncate">{{ $server->node?->name }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-500">Location</span>
                        <span class="text-slate-900 truncate">{{ $server->node?->location?->flag }} {{ $server->node?->location?->name }}</span>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.app>
