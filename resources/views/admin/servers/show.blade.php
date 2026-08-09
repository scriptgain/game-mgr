@php
    use App\Support\Format;

    // The page is a command deck, not a scroll. Above the tabs sits only what an
    // operator wants without asking: what state it is in, where players connect,
    // the power controls, and four live vitals. Everything reference shaped is a
    // tab, because a fact you read once a month should not push the console off
    // the screen every day.
    $detailTabs = [
        ['id' => 'console', 'label' => 'Console', 'icon' => 'terminal'],
        ['id' => 'overview', 'label' => 'Overview', 'icon' => 'info'],
        ['id' => 'limits', 'label' => 'Limits', 'icon' => 'cpu'],
        ['id' => 'startup', 'label' => 'Startup', 'icon' => 'bolt', 'count' => $server->variables->count() ?: null],
        ['id' => 'access', 'label' => 'Access', 'icon' => 'users', 'count' => $server->subusers->count() ?: null],
        ['id' => 'backups', 'label' => 'Backups', 'icon' => 'archive', 'count' => $server->backups->count() ?: null],
    ];

    $powerAction = route('server.power', $server);
    $canControl = $server->isControllable();

    // The first frame comes from the model, the frames after it from the node.
    // Both are stated here so a disabled button is never disabled for one reason
    // server side and a different one client side.
    $liveStart = '! controllable() || stats.state === \'running\' || stats.state === \'starting\'';
    $liveStop = '! controllable() || (stats.state !== \'running\' && stats.state !== \'starting\')';
    $liveRestart = '! controllable() || stats.state !== \'running\'';
    $liveKill = '! controllable() || stats.state === \'offline\'';
@endphp

<x-layouts.app :title="$title">
    @include('admin.servers._created')

    {{-- One Alpine scope for the whole page: the header dot, the power buttons,
         the vitals and the console all read the same live state, so they can
         never disagree about whether the server is running. The component is
         the one in public/js/gamemgr.js that the client console uses. --}}
    <div class="space-y-6 min-w-0"
         x-data="gameConsole({
            streamUrl: @js($streamUrl),
            pollUrl: @js(route('server.stats', $server)),
            backlog: @js($backlog),
            memory: {{ (int) $server->memory }},
            disk: {{ (int) $server->disk }},
            diskUsed: {{ (int) $server->cached_disk }},
            cpuLimit: {{ (int) $server->cpu }},
            state: @js($server->power_state),
            status: @js($server->status)
         })">

        {{-- ------------------------------------------------------ command deck --}}
        <x-card flush>
            <div class="px-5 sm:px-6 py-5 flex flex-wrap items-start justify-between gap-x-4 gap-y-4">
                <div class="flex items-start gap-4 min-w-0">
                    <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-brand-50 text-brand-600 ring-1 ring-brand-200 shrink-0">
                        <x-icon name="server" class="w-6 h-6" />
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-slate-900 [overflow-wrap:anywhere]">{{ $server->name }}</h1>
                            {{-- Live status pill. Server rendered first so it is
                                 right before Alpine boots, then kept current by
                                 the same feed the console reads. --}}
                            <span class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                  :class="{
                                    'bg-emerald-50 text-emerald-700 ring-emerald-200': stateTone() === 'emerald',
                                    'bg-amber-50 text-amber-700 ring-amber-200': stateTone() === 'amber',
                                    'bg-rose-50 text-rose-700 ring-rose-200': stateTone() === 'rose',
                                    'bg-slate-100 text-slate-600 ring-slate-200': stateTone() === 'slate',
                                  }">
                                <span class="relative flex h-2 w-2">
                                    <span x-show="stateTone() !== 'slate'"
                                          class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-70"
                                          :class="{
                                            'bg-emerald-500': stateTone() === 'emerald',
                                            'bg-amber-500': stateTone() === 'amber',
                                            'bg-rose-500': stateTone() === 'rose',
                                          }"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full"
                                          :class="{
                                            'bg-emerald-500': stateTone() === 'emerald',
                                            'bg-amber-500': stateTone() === 'amber',
                                            'bg-rose-500': stateTone() === 'rose',
                                            'bg-slate-400': stateTone() === 'slate',
                                          }"></span>
                                </span>
                                <span x-text="stateLabel()">{{ $server->statusLabel() }}</span>
                            </span>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-2 text-sm text-slate-500">
                            <span class="[overflow-wrap:anywhere]">
                                {{ $server->template?->game?->name ?? 'No Game' }}
                                &middot; {{ $server->template?->name ?? 'No Template' }}
                            </span>
                            {{-- Only earns its place while the row is one line.
                                 Wrapped onto a phone it is a pipe hanging off
                                 the end of a sentence. --}}
                            <span class="hidden text-slate-300 sm:inline" aria-hidden="true">|</span>
                            <span class="inline-flex items-center gap-1.5 [overflow-wrap:anywhere]">
                                <x-icon name="server" class="w-3.5 h-3.5 text-slate-400" />
                                {{ $server->node?->name ?? 'No Node' }}
                            </span>
                            @if ($server->node?->location)
                                <span class="inline-flex items-center gap-1.5 [overflow-wrap:anywhere]">
                                    <x-icon name="globe" class="w-3.5 h-3.5 text-slate-400" />
                                    {{ $server->node->location->flag }} {{ $server->node->location->name }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <x-runtime-badge :runtime="$server->runtime" />
                            @if ($server->auto_restart)<x-badge color="neutral"><x-icon name="refresh" class="w-3.5 h-3.5" /> Auto Restart</x-badge>@endif
                            @if ($server->auto_update)<x-badge color="neutral"><x-icon name="download" class="w-3.5 h-3.5" /> Auto Update</x-badge>@endif
                            <span class="font-mono text-xs text-slate-400" data-tip="Short id. This is what client URLs use.">{{ $server->uuid_short }}</span>
                        </div>
                    </div>
                </div>

                {{-- Everyday actions read as words. The three that change what a
                     server IS, rather than what it is doing, are icons behind a
                     divider: reachable in one click, never the loudest thing on
                     the page, and every one of them still a modal confirm. --}}
                {{-- No shrink-0 here. The card clips to its rounded corners, so
                     a row that refuses to shrink does not overflow visibly, it
                     disappears: at 360 the delete control was cut in half by
                     the card edge rather than wrapping onto its own line. --}}
                <div class="flex flex-wrap items-center gap-2 min-w-0">
                    <x-button href="{{ route('server.console', $server) }}" variant="secondary" size="sm" icon="terminal">Client View</x-button>
                    <x-button href="{{ route('admin.servers.edit', $server) }}" size="sm" icon="edit">Edit</x-button>

                    <span class="mx-1 hidden h-6 w-px bg-slate-200 sm:inline-block" aria-hidden="true"></span>

                    @if ($server->isSuspended())
                        <form method="POST" action="{{ route('admin.servers.unsuspend', $server) }}">
                            @csrf<x-button type="submit" variant="secondary" size="sm" icon="check">Unsuspend</x-button>
                        </form>
                    @else
                        <x-confirm-action
                            name="suspend-server"
                            :action="route('admin.servers.suspend', $server)"
                            tone="warn"
                            title="Suspend {{ $server->name }}?"
                            message="The server stops and the owner loses every control except reading. Files, backups and databases are untouched."
                            confirm="Suspend"
                            confirm-variant="danger">
                            <x-icon-button icon="ban" title="Suspend Server" />
                        </x-confirm-action>
                    @endif

                    <x-confirm-action
                        name="reinstall-server-admin"
                        :action="route('admin.servers.reinstall', $server)"
                        :fields="['wipe' => 0]"
                        tone="warn"
                        title="Reinstall {{ $server->name }}?"
                        message="The install script runs again over this server. Game files are replaced. Worlds, configs and anything else in the data directory are kept."
                        confirm="Reinstall">
                        <x-icon-button icon="refresh" title="Reinstall Server" />
                    </x-confirm-action>

                    {{-- A second, separate button rather than a tickbox on the
                         first. A checkbox inside a confirm dialog is read by
                         nobody, and this one empties somebody's world. --}}
                    <x-confirm-action
                        name="reinstall-server-wipe"
                        :action="route('admin.servers.reinstall', $server)"
                        :fields="['wipe' => 1]"
                        tone="danger"
                        confirm-variant="danger"
                        title="Wipe And Reinstall {{ $server->name }}?"
                        message="Everything in the data directory goes: worlds, configs, plugins, saves. The node holds the old contents until the reinstall succeeds and puts them back if it fails, but once it succeeds they are gone."
                        confirm="Wipe And Reinstall">
                        <x-icon-button icon="fire" variant="danger" title="Wipe And Reinstall" />
                    </x-confirm-action>

                    <x-confirm-action
                        name="delete-server"
                        :action="route('admin.servers.destroy', $server)"
                        method="DELETE"
                        tone="danger"
                        title="Delete {{ $server->name }}?"
                        message="The server record, its backups and its databases are removed and its ports are freed. There is no undo."
                        confirm="Delete Server"
                        confirm-variant="danger">
                        <x-icon-button icon="trash" title="Delete Server" variant="danger" />
                    </x-confirm-action>
                </div>
            </div>

            {{-- Where players connect, and what an operator does about it. One
                 strip, two jobs, both with an icon so neither reads as a row of
                 labels. --}}
            <div class="border-t border-slate-100 bg-slate-50/60 px-5 sm:px-6 py-4">
                <div class="flex flex-wrap items-end gap-x-8 gap-y-4">
                    <div class="min-w-0 flex-1 basis-72 lg:max-w-lg">
                        <p class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-slate-700">
                            <x-icon name="network" class="w-4 h-4 text-slate-400" /> Connect Address
                        </p>
                        @if ($server->connectAddress())
                            <div class="space-y-2">
                                <x-copy-field :value="$server->connectAddress()" />
                                <x-copy-field :value="$server->address()" />
                            </div>
                        @else
                            <x-copy-field :value="$server->address()" />
                        @endif
                    </div>

                    <div class="min-w-0">
                        <p class="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-slate-700">
                            <x-icon name="power" class="w-4 h-4 text-slate-400" /> Power
                        </p>
                        <div class="flex flex-wrap items-center gap-1.5 rounded-xl bg-white p-1.5 ring-1 ring-slate-200">
                            <x-confirm-action
                                name="start-server-admin"
                                :action="$powerAction"
                                method="POST"
                                title="Start {{ $server->name }}?"
                                message="The server will boot and begin accepting players. A large world can take a minute or two to load."
                                confirm="Start It"
                                :fields="['action' => 'start']">
                                <x-button size="sm" icon="play"
                                          :disabled="! $server->canStart()"
                                          ::disabled="{{ $liveStart }}">Start</x-button>
                            </x-confirm-action>

                            {{-- Every power action goes through a modal confirm,
                                 Start included: a control that sometimes asks and
                                 sometimes does not is one people stop reading. A
                                 disabled
                                 trigger swallows the click, so the modal cannot
                                 open for a state that would refuse the action. --}}
                            <x-confirm-action
                                name="restart-server-admin"
                                :action="$powerAction"
                                method="POST"
                                tone="warn"
                                title="Restart {{ $server->name }}?"
                                message="Everyone online is dropped while the game stops and boots again. The world is saved first, so nothing is lost, but a busy server will notice."
                                confirm="Restart"
                                :fields="['action' => 'restart']">
                                <x-button variant="secondary" size="sm" icon="refresh"
                                          :disabled="! $server->canRestart()"
                                          ::disabled="{{ $liveRestart }}">Restart</x-button>
                            </x-confirm-action>

                            <x-confirm-action
                                name="stop-server-admin"
                                :action="$powerAction"
                                method="POST"
                                tone="warn"
                                title="Stop {{ $server->name }}?"
                                message="Everyone online is disconnected and the server stays down until somebody starts it again. The world is saved first, so nothing is lost."
                                confirm="Stop It"
                                :fields="['action' => 'stop']">
                                <x-button variant="secondary" size="sm" icon="stop"
                                          :disabled="! $server->canStop()"
                                          ::disabled="{{ $liveStop }}">Stop</x-button>
                            </x-confirm-action>

                            <x-confirm-action
                                name="kill-server-admin"
                                :action="$powerAction"
                                method="POST"
                                tone="danger"
                                title="Kill {{ $server->name }}?"
                                message="Kill pulls the plug without letting the game save. Anything since the last autosave is lost. Use Stop unless the server has stopped responding entirely."
                                confirm="Kill It"
                                confirm-variant="danger"
                                :fields="['action' => 'kill']">
                                <x-button variant="danger-soft" size="sm" icon="bolt-slash"
                                          :disabled="! $server->canKill()"
                                          ::disabled="{{ $liveKill }}">Kill</x-button>
                            </x-confirm-action>
                        </div>
                    </div>
                </div>

                {{-- Honest about why a button is dead, rather than a greyed
                     control with no explanation. --}}
                <p class="mt-3 text-xs text-slate-500">
                    @if (! $canControl)
                        This server is {{ strtolower($server->statusLabel()) }}, so power actions are refused by the panel until that clears.
                    @else
                        <span x-text="stats.state === 'running'
                            ? 'Running. Stop asks the game to save and exit; Kill does not.'
                            : 'Not running. Start boots it on ' + @js($server->node?->name ?? 'its node') + '.'"></span>
                    @endif
                </p>
            </div>
        </x-card>

        {{-- ------------------------------------------------------- state banners --}}
        @if ($memoryFloor)
            <x-alert type="warn" title="Memory Is Below What This Template Is Built For">
                This server has {{ Format::mib($server->memory) }}. The smallest published blueprint for
                {{ $server->template?->name }}, "{{ $memoryFloor['name'] }}", sets {{ Format::mib($memoryFloor['memory']) }}.
                The limit is written as a hard cgroup memory.max with swap disabled, so the process is killed
                outright rather than slowed down, and that usually lands during world load.
                <a href="{{ route('admin.servers.edit', $server) }}" class="font-semibold underline hover:no-underline">Raise The Memory Limit</a>.
            </x-alert>
        @endif

        @if ($nodeCheck && ! $nodeCheck['alive'])
            <x-alert type="danger" title="The Node Is Not Answering">
                {{ $server->node?->name ?? 'The node' }} did not respond to a health check, so nothing about this
                install can move. Check the daemon is running and reachable before looking at the server itself.
                <a href="{{ $server->node ? route('admin.nodes.show', $server->node) : '#' }}" class="font-semibold underline hover:no-underline">Open The Node</a>.
            </x-alert>
        @elseif ($nodeCheck && ! $nodeCheck['authenticated'])
            <x-alert type="danger" title="The Node Rejected The Panel's Token">
                {{ $server->node?->name }} answers its health check, which needs no credential, but refused an
                authenticated call. The panel therefore cannot start the install, read the log or send a command,
                and this server will sit at {{ strtolower($server->statusLabel()) }} until the token matches.
                Re-enroll the node or check its stored daemon secret.
                <a href="{{ $server->node ? route('admin.nodes.show', $server->node) : '#' }}" class="font-semibold underline hover:no-underline">Open The Node</a>.
            </x-alert>
        @elseif ($nodeCheck && $server->isInstalling())
            <x-alert type="info" title="Waiting On The Node">
                {{ $server->node?->name }} is answering and accepting the panel's token. The panel is waiting for
                the install to report that it finished. Until it does this server has no game files, so it cannot
                be started and commands have nowhere to go.
            </x-alert>
        @endif

        @if ($server->isSuspended())
            <x-alert type="warn" title="Suspended">
                The owner has no controls beyond reading. Files, backups and databases are untouched.
                Unsuspend from the header when the reason has cleared.
            </x-alert>
        @endif

        {{-- ------------------------------------------------------------- vitals
             Four tiles, not four label and value rows. Each carries its own icon,
             the live figure it is measuring, and the bar that says how close to
             the ceiling it is, which is the part a table of numbers never
             answers at a glance. --}}
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4 min-w-0">
            <div class="min-w-0 rounded-xl bg-white p-4 ring-1 ring-slate-200 shadow-sm">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-brand-200">
                        <x-icon name="cpu" class="w-4 h-4" />
                    </span>
                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">CPU</p>
                </div>
                <p class="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                    <span class="tabular text-xl sm:text-2xl font-semibold text-slate-900"
                          x-text="(Math.round(stats.cpu * 10) / 10) + '%'">{{ round((float) $server->cached_cpu, 1) }}%</span>
                    <span class="text-xs text-slate-400">of {{ (int) $server->cpu }}%</span>
                </p>
                <x-meter class="mt-2.5" :value="$server->cached_cpu" :max="max(1, $server->cpu)" live="cpuPercent()" />
            </div>

            <div class="min-w-0 rounded-xl bg-white p-4 ring-1 ring-slate-200 shadow-sm">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600 ring-1 ring-sky-200">
                        <x-icon name="memory" class="w-4 h-4" />
                    </span>
                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">Memory</p>
                </div>
                <p class="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                    <span class="tabular text-xl sm:text-2xl font-semibold text-slate-900"
                          x-text="formatMib(stats.memory_mib)">{{ Format::mib($server->cached_memory) }}</span>
                    <span class="text-xs text-slate-400">of {{ Format::mib($server->memory) }}</span>
                </p>
                <x-meter class="mt-2.5" :value="$server->cached_memory" :max="max(1, $server->memory)"
                         live="memoryPercent()"
                         live-tone="memoryPercent() >= 90 ? 'bg-rose-500' : (memoryPercent() >= 75 ? 'bg-amber-500' : 'bg-brand-500')" />
            </div>

            <div class="min-w-0 rounded-xl bg-white p-4 ring-1 ring-slate-200 shadow-sm">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 ring-1 ring-indigo-200">
                        <x-icon name="database" class="w-4 h-4" />
                    </span>
                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">Disk</p>
                </div>
                <p class="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                    <span class="tabular text-xl sm:text-2xl font-semibold text-slate-900"
                          x-text="formatMib(stats.disk_mib || {{ (int) $server->cached_disk }})">{{ Format::mib($server->cached_disk) }}</span>
                    <span class="text-xs text-slate-400">of {{ Format::mib($server->disk) }}</span>
                </p>
                <x-meter class="mt-2.5" :value="$server->cached_disk" :max="max(1, $server->disk)" live="diskPercent()" />
            </div>

            <div class="min-w-0 rounded-xl bg-white p-4 ring-1 ring-slate-200 shadow-sm">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200">
                        <x-icon name="user-group" class="w-4 h-4" />
                    </span>
                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">Players</p>
                </div>
                <p class="mt-3 flex flex-wrap items-baseline gap-x-1.5">
                    <span class="tabular text-xl sm:text-2xl font-semibold text-slate-900"
                          x-text="stats.players ?? 0">{{ (int) $server->cached_players }}</span>
                    <span class="text-xs text-slate-400">of <span x-text="stats.max_players || 0">{{ (int) $server->cached_max_players }}</span></span>
                </p>
                {{-- A slot cap of zero is a real answer (the template exposes no
                     player count), so the bar sits at empty rather than dividing
                     by nothing. --}}
                <x-meter class="mt-2.5" :value="$server->cached_players" :max="max(1, $server->cached_max_players)"
                         live="stats.max_players ? Math.min(100, Math.round((stats.players / stats.max_players) * 100)) : 0" />
            </div>
        </div>

        {{-- --------------------------------------------------------------- tabs
             The page's navigation, so it sits where navigation belongs: directly
             under the deck. Console is the default pane because that is what an
             operator opens this page to watch. --}}
        <x-tab-set :tabs="$detailTabs" active="console" label="Server Sections">

            <x-tab-pane id="console">
                <x-install-progress :server="$server" />
                <x-live-console :server="$server" height="h-80 sm:h-[26rem] lg:h-[30rem]" />
            </x-tab-pane>

            <x-tab-pane id="overview">
                <x-card title="Operator Facts" icon="info">
                    <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <div class="min-w-0">
                            <dt class="text-slate-500">Owner</dt>
                            <dd class="text-slate-900 [overflow-wrap:anywhere]">
                                @if ($server->owner)
                                    <a href="{{ route('admin.users.edit', $server->owner) }}" class="text-brand-700 hover:text-brand-800">{{ $server->owner->name }}</a>
                                    <span class="block text-xs text-slate-400">{{ $server->owner->email }}</span>
                                @else
                                    <span class="text-slate-400">None</span>
                                @endif
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Node</dt>
                            <dd class="[overflow-wrap:anywhere]">
                                @if ($server->node)
                                    <a href="{{ route('admin.nodes.show', $server->node) }}" class="text-brand-700 hover:text-brand-800">{{ $server->node->name }}</a>
                                    <span class="block text-xs text-slate-400">{{ $server->node->location?->flag }} {{ $server->node->location?->name }}</span>
                                @else
                                    <span class="text-slate-400">Unassigned</span>
                                @endif
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Template</dt>
                            <dd class="[overflow-wrap:anywhere]">
                                @if ($server->template)
                                    <a href="{{ route('admin.templates.show', $server->template) }}" class="text-brand-700 hover:text-brand-800">{{ $server->template->name }}</a>
                                    <span class="block text-xs text-slate-400">{{ $server->template->game?->name }}</span>
                                @else
                                    <span class="text-slate-400">None</span>
                                @endif
                            </dd>
                        </div>
                        @if ($minecraft = $server->minecraft())
                            {{-- What this server will actually download at its
                                 next start, without opening the Startup form. --}}
                            <div class="min-w-0">
                                <dt class="text-slate-500">Server Software</dt>
                                <dd class="text-slate-900 [overflow-wrap:anywhere]">
                                    {{ \Illuminate\Support\Str::headline(mb_strtolower($minecraft['type'])) }} {{ $minecraft['version'] }}
                                    <span class="block text-xs text-slate-400">
                                        @if ($minecraft['build'])
                                            Pinned to build {{ $minecraft['build'] }}
                                        @else
                                            Newest build at each start
                                        @endif
                                    </span>
                                </dd>
                            </div>
                        @endif
                        @if ($server->connectName())
                            <div class="min-w-0">
                                <dt class="text-slate-500">Connection Name</dt>
                                <dd class="font-mono text-xs text-slate-900 [overflow-wrap:anywhere]">{{ $server->connectAddress() }}</dd>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <dt class="text-slate-500">Primary Allocation</dt>
                            <dd class="font-mono text-xs text-slate-900 [overflow-wrap:anywhere]">{{ $server->address() }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">All Allocations</dt>
                            <dd class="text-slate-900">
                                @if ($server->allocations->isEmpty())
                                    <span class="text-slate-400">None</span>
                                @else
                                    <span class="flex flex-wrap gap-1.5">
                                        @foreach ($server->allocations as $allocation)
                                            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-700">{{ $allocation->address() }}</span>
                                        @endforeach
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Runtime</dt>
                            <dd><x-runtime-badge :runtime="$server->runtime" /></dd>
                        </div>
                        {{-- State is not repeated here on purpose. The header
                             pill is driven by the node and this grid would be
                             driven by the last row the database saw, and the two
                             disagreeing on one page is worse than either. --}}
                        <div class="min-w-0">
                            <dt class="text-slate-500">Installed</dt>
                            <dd class="text-slate-900">{{ $server->installed_at?->diffForHumans() ?? 'Not Yet' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Created</dt>
                            <dd class="text-slate-900">{{ $server->created_at?->diffForHumans() }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Last Started</dt>
                            <dd class="text-slate-900">{{ $server->last_started_at?->diffForHumans() ?? 'Never' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Last Crashed</dt>
                            <dd class="text-slate-900">{{ $server->last_crashed_at?->diffForHumans() ?? 'Never' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Last Sample</dt>
                            <dd class="text-slate-900">{{ $server->cached_at?->diffForHumans() ?? 'Never Taken' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">UUID</dt>
                            <dd class="font-mono text-xs text-slate-500 [overflow-wrap:anywhere]">{{ $server->uuid }}</dd>
                        </div>
                    </dl>

                    @if ($server->description)
                        <div class="mt-5 border-t border-slate-100 pt-4">
                            <p class="text-sm text-slate-500">Description</p>
                            <p class="mt-1 text-sm text-slate-800 [overflow-wrap:anywhere]">{{ $server->description }}</p>
                        </div>
                    @endif
                </x-card>

                <x-card title="Client Tools" icon="link" subtitle="The real tools live in the client area. These open it as this server.">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($clientLinks as $link)
                            <a href="{{ route($link['route'], $server) }}"
                               class="flex items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-sm text-slate-700 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-50 hover:text-slate-900 hover:ring-slate-400">
                                <x-icon :name="$link['icon']" class="w-4 h-4 shrink-0 text-slate-400" />
                                <span class="truncate">{{ $link['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </x-card>
            </x-tab-pane>

            <x-tab-pane id="limits">
                <x-card title="Resource Limits" icon="memory" subtitle="What the node enforces. Usage shown is the last cached sample.">
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-meter label="Memory" :value="$server->cached_memory" :max="max(1, $server->memory)">
                            {{ Format::mibPair($server->cached_memory, $server->memory) }}
                        </x-meter>
                        <x-meter label="Disk" :value="$server->cached_disk" :max="max(1, $server->disk)">
                            {{ Format::mibPair($server->cached_disk, $server->disk) }}
                        </x-meter>
                        <x-meter label="CPU" :value="$server->cached_cpu" :max="max(1, $server->cpu)">
                            {{ round((float) $server->cached_cpu, 1) }} / {{ (int) $server->cpu }}%
                        </x-meter>
                    </div>

                    <dl class="mt-6 grid gap-x-6 gap-y-4 border-t border-slate-100 pt-5 text-sm sm:grid-cols-3">
                        <div><dt class="text-slate-500">Swap</dt><dd class="tabular text-slate-900">{{ (int) $server->swap === -1 ? 'Unlimited' : Format::mib($server->swap) }}</dd></div>
                        <div><dt class="text-slate-500">Block IO Weight</dt><dd class="tabular text-slate-900">{{ $server->io }}</dd></div>
                        <div><dt class="text-slate-500">OOM Killer</dt><dd class="text-slate-900">{{ $server->oom_disabled ? 'Disabled' : 'Enabled' }}</dd></div>
                    </dl>
                </x-card>

                <x-card title="Feature Caps" icon="lock">
                    <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        {{-- A cap of zero is zero, not unlimited: the client tab
                             for that feature is hidden when it is 0, so reading
                             it as unlimited here contradicted the rest of the
                             panel. --}}
                        <div><dt class="text-slate-500">Databases</dt><dd class="tabular text-slate-900">{{ $server->databases->count() }} / {{ $server->database_limit > 0 ? $server->database_limit : 'None' }}</dd></div>
                        <div><dt class="text-slate-500">Backups</dt><dd class="tabular text-slate-900">{{ $server->backups->count() }} / {{ $server->backup_limit > 0 ? $server->backup_limit : 'None' }}</dd></div>
                        <div><dt class="text-slate-500">Allocations</dt><dd class="tabular text-slate-900">{{ $server->allocations->count() }} / {{ $server->allocation_limit > 0 ? $server->allocation_limit : 'None' }}</dd></div>
                        <div><dt class="text-slate-500">Schedules</dt><dd class="tabular text-slate-900">{{ $server->schedules->count() }}</dd></div>
                    </dl>
                </x-card>
            </x-tab-pane>

            <x-tab-pane id="startup">
                <x-card title="Runtime And Image" icon="play">
                    <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <div class="min-w-0">
                            <dt class="text-slate-500">Runtime</dt>
                            <dd class="mt-1"><x-runtime-badge :runtime="$server->runtime" /></dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Image</dt>
                            <dd class="mt-1 font-mono text-xs text-slate-900 [overflow-wrap:anywhere]">{{ $server->image ?: 'Not Set' }}</dd>
                        </div>
                    </dl>
                    <div class="mt-5">
                        <x-code-pane label="Startup Command" :code="$server->startup" empty="This server has no startup command of its own." />
                    </div>
                </x-card>

                <x-card title="Variables" icon="sliders" flush>
                    @if ($server->variables->isEmpty())
                        <x-empty-state icon="settings" title="No Variables"
                                       description="This template exposes nothing configurable, so there is nothing stored per server." />
                    @else
                        <x-table flush>
                            <thead><tr><th>Name</th><th>Environment</th><th>Value</th></tr></thead>
                            <tbody>
                                @foreach ($server->variables as $variable)
                                    <tr>
                                        <td class="font-medium text-slate-900">{{ $variable->variable?->name ?? 'Removed' }}</td>
                                        <td class="font-mono text-xs text-slate-500">{{ $variable->variable?->env_variable }}</td>
                                        <td class="font-mono text-xs text-slate-700 [overflow-wrap:anywhere]">{{ $variable->value === '' ? '(empty)' : $variable->value }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table>
                    @endif
                </x-card>
            </x-tab-pane>

            <x-tab-pane id="access">
                <x-card title="Shared With" icon="users" flush
                        subtitle="Subusers hold a named permission list. The owner and admins are not listed here.">
                    @if ($server->subusers->isEmpty())
                        <x-empty-state icon="users" title="Not Shared"
                                       description="Only the owner and panel administrators can reach this server." />
                    @else
                        <x-table flush>
                            <thead><tr><th>User</th><th>Email</th><th>Permissions</th></tr></thead>
                            <tbody>
                                @foreach ($server->subusers as $subuser)
                                    <tr>
                                        <td class="font-medium text-slate-900">{{ $subuser->user?->name }}</td>
                                        <td class="text-slate-500">{{ $subuser->user?->email }}</td>
                                        <td class="tabular text-slate-500">{{ count($subuser->permissions ?? []) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-table>
                    @endif
                </x-card>
            </x-tab-pane>

            <x-tab-pane id="backups">
                <x-card title="Backups" icon="archive" flush>
                    <x-slot:actions>
                        <x-button href="{{ route('server.backups', $server) }}" variant="secondary" size="sm" icon="archive">Manage</x-button>
                    </x-slot:actions>
                    @if ($server->backups->isEmpty())
                        <x-empty-state icon="archive" title="No Backups Taken"
                                       description="Nothing has been captured for this server yet." />
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($server->backups->take(10) as $backup)
                                <li class="flex items-center justify-between gap-3 px-5 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm text-slate-800">{{ $backup->name }}</p>
                                        <p class="text-xs text-slate-400">
                                            {{ Format::bytes($backup->bytes) }} &middot; {{ $backup->completed_at?->diffForHumans() ?? 'in progress' }}
                                        </p>
                                    </div>
                                    <x-status-dot :tone="$backup->statusTone()" :label="$backup->statusLabel()" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </x-tab-pane>
        </x-tab-set>
    </div>
</x-layouts.app>
