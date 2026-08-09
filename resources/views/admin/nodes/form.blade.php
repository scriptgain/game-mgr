@php
    // Which step a field belongs to, so a validation failure reopens the step
    // that actually failed rather than dumping the user back at step one to
    // go hunting for it.
    $stepOf = [
        'name' => 1, 'description' => 1, 'location_id' => 1,
        'connection_mode' => 2, 'scheme' => 2, 'fqdn' => 2, 'daemon_port' => 2, 'sftp_port' => 2,
        'runtimes' => 3,
        'memory' => 4, 'memory_overallocate' => 4, 'disk' => 4, 'disk_overallocate' => 4,
        'cpu' => 4, 'cpu_overallocate' => 4, 'upload_size' => 4,
        'public' => 5, 'maintenance_mode' => 5, 'daemon_base' => 5, 'dns_label' => 5,
    ];
    $firstBadStep = 1;
    foreach ($stepOf as $field => $step) {
        if ($errors->has($field)) { $firstBadStep = $step; break; }
    }

    $activeRuntimes = (array) old('runtimes', $node->runtimes ?? ['docker']);
    // old() gives back the posted map (docker => 1), the model gives a list.
    if (array_is_list($activeRuntimes) === false) {
        $activeRuntimes = array_keys(array_filter($activeRuntimes));
    }

    $runtimeCards = [
        'docker' => [
            'title' => 'Docker',
            'gives' => 'Every server in its own container, with its own image and its own hard limits. Almost every community template targets this, so leave it on.',
            'needs' => 'Needs the Docker daemon running.',
        ],
        'steamcmd' => [
            'title' => 'SteamCMD',
            'gives' => 'A native install with no container in the way. Less overhead, direct access to the hardware, and the usual choice for Source and Unreal servers on bare metal.',
            'needs' => 'Needs steamcmd on PATH.',
        ],
        'linuxgsm' => [
            'title' => 'LinuxGSM',
            'gives' => 'Wraps the LinuxGSM control scripts and brings their catalogue of 130 plus games along with them, updates and all.',
            'needs' => 'Needs tmux, which holds the console.',
        ],
    ];

    // One shape for the three capacity rows. Everything that differs between
    // memory, disk and CPU lives here rather than in three near-identical
    // blocks of markup.
    $resources = [
        [
            'key' => 'memory', 'kind' => 'mib', 'icon' => 'memory', 'label' => 'Memory',
            'model' => 'memory', 'over' => 'memoryOver', 'unit' => 'MiB', 'pad' => 'pr-14',
            'placeholder' => '65536',
            'note' => 'What the machine has, minus whatever you want to keep back for the host itself.',
        ],
        [
            'key' => 'disk', 'kind' => 'mib', 'icon' => 'database', 'label' => 'Disk',
            'model' => 'disk', 'over' => 'diskOver', 'unit' => 'MiB', 'pad' => 'pr-14',
            'placeholder' => '1048576',
            'note' => 'Space under the data directory. Game installs are large and world saves only grow.',
        ],
        [
            'key' => 'cpu', 'kind' => 'cpu', 'icon' => 'cpu', 'label' => 'CPU',
            'model' => 'cpu', 'over' => 'cpuOver', 'unit' => '%', 'pad' => 'pr-10',
            'placeholder' => '1600',
            'note' => '100 is one full core, so a sixteen core machine is 1600.',
        ],
    ];

    $steps = [
        1 => ['label' => 'Identity', 'icon' => 'server', 'hint' => 'Name and location'],
        2 => ['label' => 'Connection', 'icon' => 'network', 'hint' => 'How the panel reaches it'],
        3 => ['label' => 'Runtimes', 'icon' => 'cube', 'hint' => 'What it can run'],
        4 => ['label' => 'Capacity', 'icon' => 'chart', 'hint' => 'What it promises'],
        5 => ['label' => 'Placement', 'icon' => 'map', 'hint' => 'Where servers land'],
        6 => ['label' => $node->exists ? 'Review' : 'Review And Create', 'icon' => 'check-circle', 'hint' => 'Check it over'],
    ];

    $panelUrl = rtrim(config('app.url'), '/');
    $defaultLocationId = $locations->first()?->id;

    // Alpine holds every field, so the capacity numbers can be read back as
    // what they mean and the last step can summarise the whole thing. Each one
    // is seeded from old() first and the model second: x-model writes state
    // into the input on init, so an unseeded binding would blank a field the
    // moment somebody opened an existing node.
    $seed = [
        'step' => $errors->any() ? $firstBadStep : 1,
        'editing' => $node->exists,
        'locations' => $locations->map(fn ($l) => [
            'id' => (string) $l->id,
            'label' => trim($l->flag.' '.$l->name),
        ])->values()->all(),

        'name' => (string) old('name', $node->name),
        'description' => (string) old('description', $node->description),
        'locationId' => (string) old('location_id', $node->location_id ?: $defaultLocationId),

        'mode' => (string) old('connection_mode', $node->connection_mode ?: 'direct'),
        'scheme' => (string) old('scheme', $node->scheme ?: 'https'),
        'fqdn' => (string) old('fqdn', $node->fqdn),
        'daemonPort' => (string) old('daemon_port', $node->daemon_port ?: config('node.default_port')),
        'sftpPort' => (string) old('sftp_port', $node->sftp_port ?: 2022),
        'behindProxy' => (bool) old('behind_proxy', $node->behind_proxy),

        'runtimes' => [
            'docker' => in_array('docker', $activeRuntimes, true),
            'steamcmd' => in_array('steamcmd', $activeRuntimes, true),
            'linuxgsm' => in_array('linuxgsm', $activeRuntimes, true),
        ],
        'runtimeNames' => ['docker' => 'Docker', 'steamcmd' => 'SteamCMD', 'linuxgsm' => 'LinuxGSM'],

        'memory' => (string) old('memory', $node->memory ?: 0),
        'memoryOver' => (string) old('memory_overallocate', $node->memory_overallocate ?: 0),
        'disk' => (string) old('disk', $node->disk ?: 0),
        'diskOver' => (string) old('disk_overallocate', $node->disk_overallocate ?: 0),
        'cpu' => (string) old('cpu', $node->cpu ?: 0),
        'cpuOver' => (string) old('cpu_overallocate', $node->cpu_overallocate ?: 0),
        'uploadSize' => (string) old('upload_size', $node->upload_size ?: 256),

        'isPublic' => (bool) old('public', $node->public ?? true),
        'maintenance' => (bool) old('maintenance_mode', $node->maintenance_mode),
        'daemonBase' => (string) old('daemon_base', $node->daemon_base ?: '/var/lib/gamemgr/volumes'),
        'dnsLabel' => (string) old('dns_label', $node->dns_label),
    ];
@endphp

<x-layouts.app :title="$title">
    {{-- Spin buttons would sit on top of the unit suffix, and every number here
         reads better with its unit than with a pair of arrows. --}}
    <style>
        .gm-num::-webkit-outer-spin-button,
        .gm-num::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .gm-num { -moz-appearance: textfield; appearance: textfield; }
        /* Alpine applies its transitions as inline styles, so the preference is
           honoured here rather than at each call site. */
        @media (prefers-reduced-motion: reduce) {
            .gm-step, .gm-step *, .gm-rail * {
                transition-duration: .01ms !important;
                animation-duration: .01ms !important;
            }
        }
    </style>

    <x-page-header :title="$title" icon="server"
                   :subtitle="$node->exists
                        ? 'Change what you came for. Every step is reachable from the rail.'
                        : 'Two parts. Describe the machine here, then run one command on the machine itself.'">
        @if (! $node->exists)
            <x-slot:actions>
                <span class="hidden sm:inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200 shadow-sm">
                    <span class="inline-flex w-5 h-5 items-center justify-center rounded-full bg-brand-600 text-[10px] font-semibold text-white">1</span>
                    Part One Of Two
                </span>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($node->exists) @include('admin.nodes._tabs', ['node' => $node]) @endif

    {{-- No max-w here, and none on the step panels either. The layout already
         sets the page width from config('gamemgr.max_width'); a second cap
         inside it renders a narrow column stranded on a wide screen. The width
         is put to work by moving the step rail into a right hand column, where
         it stays visible while you work through a step. --}}
    <form method="POST" novalidate
          action="{{ $node->exists ? route('admin.nodes.update', $node) : route('admin.nodes.store') }}"
          x-data="nodeWizard(@js($seed))">
        {{-- novalidate on purpose. Five of six steps are hidden at any moment,
             and the browser refuses to submit a form holding an invalid control
             it cannot scroll to, silently. Every step is checked on the way
             forward instead, and the server validates the lot regardless. --}}
        @csrf
        @if ($node->exists)@method('PUT')@endif

        @if ($errors->any())
            <div class="mb-6">
                <x-alert type="danger" title="Something Needs Fixing">
                    {{ $errors->first() }} The step it belongs to is open.
                </x-alert>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3 items-start">
            <div class="lg:col-span-2 space-y-6 min-w-0">

                {{-- 1: identity ------------------------------------------- --}}
                <div class="gm-step" x-ref="step1" x-show="step === 1" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card title="Identity" icon="info" subtitle="What this machine is called, and which part of the world it sits in.">
                        <div class="space-y-5">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="Name" required hint="Short, and the same shape as your other machines."
                                         :error="$errors->first('name')">
                                    <x-input name="name" value="{{ old('name', $node->name) }}" required
                                             x-model="name" placeholder="phx-docker-01" autocomplete="off" />
                                </x-field>
                                <x-field label="Location" required hint="Players see this as the region a server is in."
                                         :error="$errors->first('location_id')">
                                    <x-select name="location_id" required x-model="locationId">
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}" @selected(old('location_id', $node->location_id) == $location->id)>
                                                {{ $location->flag }} {{ $location->name }}
                                            </option>
                                        @endforeach
                                    </x-select>
                                </x-field>
                            </div>
                            <x-field label="Description" hint="For your benefit later, when you have twenty of these."
                                     :error="$errors->first('description')">
                                <x-input name="description" value="{{ old('description', $node->description) }}"
                                         x-model="description" placeholder="General purpose Docker node" />
                            </x-field>

                            {{-- How the machine will read in the node list, live. --}}
                            <div class="rounded-xl bg-slate-50 ring-1 ring-inset ring-slate-200 p-4">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">In The Node List</p>
                                <div class="mt-2.5 flex items-start gap-3 min-w-0">
                                    <span class="inline-flex w-9 h-9 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 ring-1 ring-slate-200 shadow-sm">
                                        <x-icon name="server" class="w-4.5 h-4.5" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900 break-words"
                                           x-text="name || 'Unnamed node'"></p>
                                        <p class="mt-0.5 text-sm text-slate-500 break-words">
                                            <span x-text="locationLabel()"></span><span x-show="description" x-cloak> &middot; <span x-text="description"></span></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <x-slot:footer>
                            <div class="flex items-center justify-between gap-2">
                                <x-button href="{{ route('admin.nodes.index') }}" variant="secondary" size="sm">Cancel</x-button>
                                <x-button type="button" size="sm" @click="next()">Continue</x-button>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>

                {{-- 2: connection ----------------------------------------- --}}
                <div class="gm-step" x-ref="step2" x-show="step === 2" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card title="How The Panel Reaches It" icon="network"
                            subtitle="Two kinds of machine. Pick the one that matches where this box actually lives.">
                        <div class="space-y-5">
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ([
                                    ['direct', 'Direct', 'network', 'The panel dials the daemon.', 'A public address and one open port. The usual answer for a VPS or a dedicated server.'],
                                    ['reverse', 'Reverse', 'cloud', 'The daemon dials the panel.', 'Nothing open, nothing forwarded. This is how a box behind NAT, at home or in an office, works at all.'],
                                ] as [$value, $label, $icon, $line, $detail])
                                    {{-- The radio is visually hidden, so the card has to show the
                                         focus itself or the keyboard loses its place. Outline rather
                                         than ring: the selected state already owns the ring. --}}
                                    <label class="group relative flex h-full cursor-pointer flex-col rounded-xl p-4 ring-1 ring-inset transition
                                                  focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand-500"
                                           :class="mode === '{{ $value }}'
                                                ? 'bg-brand-50/60 ring-brand-300 shadow-sm'
                                                : 'bg-white ring-slate-200 hover:ring-slate-300'">
                                        <input type="radio" name="connection_mode" value="{{ $value }}" x-model="mode" class="sr-only">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="inline-flex w-7 h-7 shrink-0 items-center justify-center rounded-lg ring-1 transition"
                                                      :class="mode === '{{ $value }}' ? 'bg-brand-100 text-brand-700 ring-brand-200' : 'bg-slate-100 text-slate-500 ring-slate-200'">
                                                    <x-icon name="{{ $icon }}" class="w-4 h-4" />
                                                </span>
                                                <span class="text-sm font-semibold text-slate-900">{{ $label }}</span>
                                            </div>
                                            <span class="inline-flex w-5 h-5 shrink-0 items-center justify-center rounded-full ring-1 transition"
                                                  :class="mode === '{{ $value }}' ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-transparent ring-slate-300'">
                                                <x-icon name="check" class="w-3 h-3" />
                                            </span>
                                        </div>
                                        <p class="mt-2 text-sm font-medium text-slate-700">{{ $line }}</p>
                                        <p class="mt-1 text-sm text-slate-500">{{ $detail }}</p>
                                    </label>
                                @endforeach
                            </div>

                            <div x-show="mode === 'direct'" x-cloak class="space-y-4">
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <x-field label="Scheme" :error="$errors->first('scheme')">
                                        <x-select name="scheme" x-model="scheme">
                                            <option value="https" @selected(old('scheme', $node->scheme) === 'https')>https</option>
                                            <option value="http" @selected(old('scheme', $node->scheme) === 'http')>http</option>
                                        </x-select>
                                    </x-field>
                                    <x-field label="Hostname Or IP" class="sm:col-span-2" :error="$errors->first('fqdn')">
                                        <x-input name="fqdn" value="{{ old('fqdn', $node->fqdn) }}" x-model="fqdn"
                                                 placeholder="node1.example.com" autocomplete="off" />
                                    </x-field>
                                </div>
                                <div class="rounded-xl bg-slate-50 ring-1 ring-inset ring-slate-200 px-4 py-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">The Panel Will Call</p>
                                    <p class="mt-1 font-mono text-sm text-slate-800 [overflow-wrap:anywhere]" x-text="daemonUrl()"></p>
                                </div>
                            </div>

                            <div x-show="mode === 'reverse'" x-cloak>
                                <x-alert type="info" title="Nothing Needs To Be Reachable">
                                    The daemon holds an outbound connection to this panel and work is pushed down it. No
                                    public IP, no port forwarding, no firewall exception. The port below is still what the
                                    daemon listens on locally.
                                </x-alert>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="Daemon Port" required hint="Where the daemon itself listens."
                                         :error="$errors->first('daemon_port')">
                                    <x-input type="number" name="daemon_port" class="gm-num" x-model="daemonPort"
                                             value="{{ old('daemon_port', $node->daemon_port ?: config('node.default_port')) }}" required />
                                </x-field>
                                <x-field label="SFTP Port" required hint="How file access reaches server volumes."
                                         :error="$errors->first('sftp_port')">
                                    <x-input type="number" name="sftp_port" class="gm-num" x-model="sftpPort"
                                             value="{{ old('sftp_port', $node->sftp_port ?: 2022) }}" required />
                                </x-field>
                            </div>

                            <x-node-switch-card model="behindProxy" name="behind_proxy" icon="shield"
                                                title="Behind A Reverse Proxy"
                                                description="Tells the daemon to trust forwarded headers, so it logs the real client address instead of the proxy's." />
                        </div>
                        <x-slot:footer>
                            <div class="flex items-center justify-between gap-2">
                                <x-button type="button" variant="secondary" size="sm" @click="back()">Back</x-button>
                                <x-button type="button" size="sm" @click="next()">Continue</x-button>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>

                {{-- 3: runtimes ------------------------------------------- --}}
                <div class="gm-step" x-ref="step3" x-show="step === 3" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card title="Runtimes" icon="play"
                            subtitle="Three ways to run a game server. Turn on whatever this machine can actually do; the server create form only offers templates the node supports.">
                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach ($runtimeCards as $runtime => $copy)
                                {{-- Posts as runtimes[<key>] = 1 or 0. The hidden input means
                                     switching one off posts a 0 rather than vanishing, and the
                                     controller folds the map back into a list. --}}
                                <x-node-switch-card :model="'runtimes.'.$runtime"
                                                    :name="'runtimes['.$runtime.']'"
                                                    :switch-label="$copy['title']"
                                                    :description="$copy['gives']">
                                    <x-slot:heading>
                                        <x-runtime-badge :runtime="$runtime" />
                                    </x-slot:heading>
                                    <p class="flex items-start gap-1.5 text-xs text-slate-400">
                                        <x-icon name="info" class="w-3.5 h-3.5 mt-px shrink-0" />
                                        <span class="min-w-0">{{ $copy['needs'] }}</span>
                                    </p>
                                </x-node-switch-card>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <div x-show="runtimeList().length > 0" x-cloak
                                 class="flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 ring-1 ring-inset ring-slate-200 px-4 py-3">
                                <span class="text-sm text-slate-500">This machine will offer</span>
                                @foreach (array_keys($runtimeCards) as $runtime)
                                    <span x-show="runtimes.{{ $runtime }}" x-cloak>
                                        <x-runtime-badge :runtime="$runtime" />
                                    </span>
                                @endforeach
                            </div>
                            <div x-show="runtimeList().length === 0" x-cloak>
                                <x-alert type="warn" title="Nothing Selected">
                                    A node with no runtime cannot run a single game. Turn at least one on.
                                </x-alert>
                            </div>
                        </div>

                        @error('runtimes')<p class="mt-3 text-sm text-rose-600">{{ $message }}</p>@enderror

                        <x-slot:footer>
                            <div class="flex items-center justify-between gap-2">
                                <x-button type="button" variant="secondary" size="sm" @click="back()">Back</x-button>
                                <x-button type="button" size="sm" @click="next()">Continue</x-button>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>

                {{-- 4: capacity ------------------------------------------- --}}
                <div class="gm-step" x-ref="step4" x-show="step === 4" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card title="Capacity" icon="chart"
                            subtitle="What this machine promises to servers. Over-allocation lets it promise more than it has, which is normal for game hosting and dangerous when it is wrong.">
                        <div class="space-y-4">
                            @foreach ($resources as $r)
                                <div class="rounded-xl ring-1 ring-inset ring-slate-200 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="inline-flex w-8 h-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-brand-200">
                                                <x-icon name="{{ $r['icon'] }}" class="w-4 h-4" />
                                            </span>
                                            <h4 class="text-sm font-semibold text-slate-900">{{ $r['label'] }}</h4>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset tabular"
                                              :class="overChip({{ $r['over'] }})">
                                            Promises
                                            <span x-text="capacityHeadline('{{ $r['kind'] }}', {{ $r['model'] }}, {{ $r['over'] }})"></span>
                                        </span>
                                    </div>

                                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <x-field :label="$r['label'].' On The Machine'" required :error="$errors->first($r['key'])">
                                            <div class="relative">
                                                <x-input type="number" name="{{ $r['key'] }}" min="0" required
                                                         class="gm-num {{ $r['pad'] }} tabular"
                                                         x-model="{{ $r['model'] }}"
                                                         placeholder="{{ $r['placeholder'] }}"
                                                         value="{{ old($r['key'], $node->{$r['key']} ?: 0) }}" />
                                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-medium text-slate-400">{{ $r['unit'] }}</span>
                                            </div>
                                        </x-field>
                                        <x-field label="Over-allocation" required :error="$errors->first($r['key'].'_overallocate')">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <div class="relative w-28 shrink-0">
                                                    <x-input type="number" name="{{ $r['key'] }}_overallocate" min="0" max="500" required
                                                             class="gm-num pr-8 tabular"
                                                             x-model="{{ $r['over'] }}"
                                                             value="{{ old($r['key'].'_overallocate', $node->{$r['key'].'_overallocate'} ?: 0) }}" />
                                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-medium text-slate-400">%</span>
                                                </div>
                                                @foreach ([0, 25, 50, 100] as $preset)
                                                    <button type="button" @click="{{ $r['over'] }} = '{{ $preset }}'"
                                                            class="rounded-lg px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition tabular"
                                                            :class="String({{ $r['over'] }}) === '{{ $preset }}'
                                                                ? 'bg-slate-900 text-white ring-slate-900'
                                                                : 'bg-white text-slate-600 ring-slate-300 hover:ring-slate-400'">{{ $preset }}%</button>
                                                @endforeach
                                            </div>
                                        </x-field>
                                    </div>

                                    {{-- Physical first, then whatever has been invented on top of
                                         it. Widths are arithmetic, hence a bound style. --}}
                                    <div class="mt-4 flex h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full bg-brand-500 transition-all" :style="barBase({{ $r['over'] }})"></div>
                                        <div class="h-full transition-all" :class="overBar({{ $r['over'] }})" :style="barOver({{ $r['over'] }})"></div>
                                    </div>
                                    <p class="mt-2 text-sm"
                                       :class="overTone({{ $r['over'] }}) === 'risk' ? 'text-rose-700' : (overTone({{ $r['over'] }}) === 'watch' ? 'text-amber-700' : 'text-slate-500')"
                                       x-text="capacityLine('{{ $r['kind'] }}', {{ $r['model'] }}, {{ $r['over'] }})"></p>
                                    <p class="mt-1 text-xs text-slate-400">{{ $r['note'] }}</p>
                                </div>
                            @endforeach

                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="File Upload Limit" required
                                         hint="The largest single file the file manager will accept on this node."
                                         :error="$errors->first('upload_size')">
                                    <div class="relative">
                                        <x-input type="number" name="upload_size" min="1" max="4096" required
                                                 class="gm-num pr-14 tabular" x-model="uploadSize"
                                                 value="{{ old('upload_size', $node->upload_size ?: 256) }}" />
                                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-medium text-slate-400">MiB</span>
                                    </div>
                                </x-field>
                            </div>

                            <div x-show="overTone(memoryOver) === 'risk' || overTone(diskOver) === 'risk' || overTone(cpuOver) === 'risk'" x-cloak>
                                <x-alert type="danger" title="That Is Double Booking The Machine">
                                    At 100 per cent or more, this node promises at least twice what it owns. Game servers
                                    rarely peak together, which is why the setting exists, but the day they do the kernel
                                    picks the loser, not you.
                                </x-alert>
                            </div>
                        </div>
                        <x-slot:footer>
                            <div class="flex items-center justify-between gap-2">
                                <x-button type="button" variant="secondary" size="sm" @click="back()">Back</x-button>
                                <x-button type="button" size="sm" @click="next()">Continue</x-button>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>

                {{-- 5: placement ------------------------------------------ --}}
                <div class="gm-step" x-ref="step5" x-show="step === 5" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card title="Placement" icon="target" subtitle="Whether servers land here on their own, and where their files go.">
                        <div class="space-y-5">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-node-switch-card model="isPublic" name="public" icon="target"
                                                    title="Available For Auto Placement"
                                                    description="On, the placer can pick this node when nobody chose one. Off, servers only land here when somebody names it by hand." />
                                <x-node-switch-card model="maintenance" name="maintenance_mode" icon="ban"
                                                    title="Maintenance Mode"
                                                    description="Blocks every new placement while you work on the machine. Servers already here keep running and keep answering." />
                            </div>

                            <div x-show="maintenance" x-cloak>
                                <x-alert type="warn">
                                    While maintenance mode is on, this node takes nothing new. Remember to turn it back off.
                                </x-alert>
                            </div>

                            <x-field label="Data Directory" required
                                     hint="Where server files live on the machine. Changing it on a live node strands every server already on it."
                                     :error="$errors->first('daemon_base')">
                                <x-input name="daemon_base" x-model="daemonBase"
                                         value="{{ old('daemon_base', $node->daemon_base ?: '/var/lib/gamemgr/volumes') }}"
                                         required class="font-mono text-sm" spellcheck="false" autocomplete="off" />
                            </x-field>

                            <div class="rounded-xl bg-slate-50 ring-1 ring-inset ring-slate-200 px-4 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Each Server Gets</p>
                                <p class="mt-1 font-mono text-sm text-slate-800 [overflow-wrap:anywhere]" x-text="volumePath()"></p>
                            </div>

                            {{-- The middle label of every connection name here.
                                 Optional on purpose: a node without one hands
                                 out no names and its servers keep the direct
                                 address they already have. --}}
                            <x-field label="DNS Label"
                                     hint="One label, no dots, such as lax1. Leave it blank and this node hands out no names."
                                     :error="$errors->first('dns_label')">
                                <x-input name="dns_label" x-model="dnsLabel"
                                         value="{{ old('dns_label', $node->dns_label) }}"
                                         placeholder="lax1" class="font-mono text-sm" spellcheck="false" autocomplete="off" />
                            </x-field>

                            {{-- Emptying this field deletes the wildcard and
                                 every connection name on the node, which is a
                                 far bigger thing than an empty optional input
                                 looks like. It happened once, silently, so the
                                 save now refuses unless this is ticked. Only
                                 shown when there is a label to lose. --}}
                            @if ($node->exists && filled($node->dns_label))
                                <div x-show="dnsLabel.trim() === ''" x-cloak>
                                    <x-alert type="warn" title="That Removes Every Name On This Node">
                                        <p>
                                            Clearing the label deletes the wildcard record and every server here goes
                                            back to its direct address. The addresses keep working; the names stop.
                                        </p>
                                        <label class="mt-2 flex items-start gap-2 text-sm">
                                            <input type="checkbox" name="confirm_clear_dns_label" value="1"
                                                   class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                                            <span>Yes, remove the connection names from this node.</span>
                                        </label>
                                    </x-alert>
                                </div>
                            @endif

                            <div class="rounded-xl bg-slate-50 ring-1 ring-inset ring-slate-200 px-4 py-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Players Will Be Able To Type</p>
                                <p class="mt-1 font-mono text-sm text-slate-800 [overflow-wrap:anywhere]"
                                   x-text="dnsLabel ? 'alpha.' + dnsLabel + '.{{ \App\Services\Dns\DnsConfig::zone() ?: 'zone-not-set' }}:8211' : 'No label, so servers here show their address only'"></p>
                                <p class="mt-1 text-xs text-slate-500">
                                    The direct address is shown alongside it everywhere and never replaced.
                                </p>
                            </div>
                        </div>
                        <x-slot:footer>
                            <div class="flex items-center justify-between gap-2">
                                <x-button type="button" variant="secondary" size="sm" @click="back()">Back</x-button>
                                <x-button type="button" size="sm" @click="next()">Continue</x-button>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>

                {{-- 6: review and hand off to the machine ------------------ --}}
                <div class="gm-step space-y-6" x-ref="step6" x-show="step === 6" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card :title="$node->exists ? 'Review' : 'Review And Create'" icon="check-circle"
                            subtitle="Everything you chose, in one place. Anything wrong is one click away.">
                        <dl class="divide-y divide-slate-100">
                            @foreach ([
                                [1, 'Identity', 'name || \'Unnamed node\'', 'locationLabel()'],
                                [2, 'Connection', 'mode === \'reverse\' ? \'Reverse, the daemon dials out\' : \'Direct, the panel dials in\'', 'daemonUrl() + \'  \\u00b7  SFTP on \' + sftpPort + (behindProxy ? \'  \\u00b7  behind a proxy\' : \'\')'],
                                [3, 'Runtimes', 'runtimeSummary()', 'runtimeList().length + \' of 3 turned on\''],
                                [4, 'Capacity', 'fmt(\'mib\', memory) + \' memory, \' + fmt(\'mib\', disk) + \' disk, \' + fmt(\'cpu\', cpu)', '\'Promising \' + capacityHeadline(\'mib\', memory, memoryOver) + \', \' + capacityHeadline(\'mib\', disk, diskOver) + \' and \' + capacityHeadline(\'cpu\', cpu, cpuOver) + \'. Uploads capped at \' + fmt(\'mib\', uploadSize) + \'.\''],
                                [5, 'Placement', 'placementSummary()', 'daemonBase'],
                            ] as [$n, $label, $primary, $secondary])
                                <div class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                                        <dd class="mt-1 text-sm font-medium text-slate-900 [overflow-wrap:anywhere]" x-text="{{ $primary }}"></dd>
                                        <dd class="mt-0.5 text-sm text-slate-500 [overflow-wrap:anywhere]" x-text="{{ $secondary }}"></dd>
                                    </div>
                                    <button type="button" @click="go({{ $n }})"
                                            class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-300 transition hover:bg-slate-50 hover:ring-slate-400">
                                        Change
                                    </button>
                                </div>
                            @endforeach
                        </dl>
                        <x-slot:footer>
                            <div class="flex items-center justify-between gap-2">
                                <x-button type="button" variant="secondary" size="sm" @click="back()">Back</x-button>
                                <x-button type="submit" size="sm" icon="check">
                                    {{ $node->exists ? 'Save Node' : 'Create Node' }}
                                </x-button>
                            </div>
                        </x-slot:footer>
                    </x-card>

                    {{-- Act two. A node row is not a node until something on the machine
                         is told about it, and that has to be visible before the create
                         button rather than discovered after the redirect. --}}
                    <x-card :title="$node->exists ? 'Part Two: The Machine Itself' : 'What Happens Next'" icon="terminal"
                            subtitle="A node in the database does nothing until the daemon on the machine is enrolled against it.">
                        <ol class="space-y-4">
                            @foreach ([
                                ['Press ' . ($node->exists ? 'Save Node' : 'Create Node'), 'The panel writes the node and issues a single use enroll token that expires shortly.'],
                                ['You land on the Enroll screen', 'One command, already filled in with this panel address and that token.'],
                                ['Paste it into a root shell on the machine', 'It installs the daemon, trades the token for a long lived credential, and reports back what the box actually has.'],
                            ] as $i => [$head, $body])
                                <li class="flex gap-3">
                                    <span class="inline-flex w-6 h-6 shrink-0 items-center justify-center rounded-full bg-slate-900 text-[11px] font-semibold text-white tabular">{{ $i + 1 }}</span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-900">{{ $head }}</p>
                                        <p class="mt-0.5 text-sm text-slate-500">{{ $body }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>

                        <div class="console-pane mt-4 px-4 py-3 text-[13px] leading-relaxed [overflow-wrap:anywhere]">
                            <span class="text-slate-500"># on the machine, as root</span><br>
                            <span class="text-slate-200">curl -fsSL {{ $panelUrl }}/install/node | sudo bash -s -- \</span><br>
                            <span class="text-slate-200">&nbsp;&nbsp;--panel {{ $panelUrl }} --token </span><span
                                class="text-amber-300">{{ $node->exists ? '<the token on the Enroll screen>' : '<issued when you press Create>' }}</span>
                        </div>

                        @if ($node->exists)
                            <x-slot:footer>
                                <x-button href="{{ route('admin.nodes.enroll', $node) }}" variant="secondary" size="sm" icon="key">
                                    Open The Enroll Screen
                                </x-button>
                            </x-slot:footer>
                        @endif
                    </x-card>
                </div>
            </div>

            {{-- Step rail. On an existing node every step is clickable: editing one
                 port should not mean walking through six screens. On a new node it
                 is a progress indicator and only goes forward. --}}
            <div class="gm-rail space-y-6 min-w-0 lg:sticky lg:top-6">
                <x-card padding="p-4 sm:p-5">
                    <x-slot:title>{{ $node->exists ? 'Sections' : 'Part One: Describe The Machine' }}</x-slot:title>
                    <x-slot:subtitle>{{ $node->exists ? 'Jump straight to the one you came for.' : 'Six short steps, none of them long.' }}</x-slot:subtitle>

                    <div class="mb-4">
                        <div class="flex items-baseline justify-between gap-3 text-xs">
                            <span class="font-medium text-slate-500">Step <span class="tabular" x-text="step"></span> of 6</span>
                            <span class="tabular text-slate-400" x-text="Math.round(step / last * 100) + '%'"></span>
                        </div>
                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-brand-500 transition-all duration-300" :style="progress()"></div>
                        </div>
                    </div>

                    <ol class="space-y-1">
                        @foreach ($steps as $n => $s)
                            <li>
                                <button type="button" @click="go({{ $n }})" :disabled="! unlocked({{ $n }})"
                                        class="w-full inline-flex items-start gap-3 rounded-lg px-3 py-2 text-left transition border"
                                        :class="step === {{ $n }}
                                            ? 'bg-brand-50 border-brand-200'
                                            : (unlocked({{ $n }})
                                                ? 'border-transparent hover:bg-slate-100 hover:border-slate-200'
                                                : 'border-transparent cursor-default')">
                                    <span class="inline-flex w-6 h-6 mt-px shrink-0 items-center justify-center rounded-full text-[11px] font-semibold transition"
                                          :class="step > {{ $n }}
                                            ? 'bg-emerald-500 text-white'
                                            : (step === {{ $n }} ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-500')">
                                        <template x-if="step > {{ $n }}"><x-icon name="check" class="w-3.5 h-3.5" /></template>
                                        <template x-if="step <= {{ $n }}"><span>{{ $n }}</span></template>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium"
                                              :class="step === {{ $n }} ? 'text-brand-700' : (unlocked({{ $n }}) ? 'text-slate-700' : 'text-slate-400')">{{ $s['label'] }}</span>
                                        <span class="block text-xs"
                                              :class="unlocked({{ $n }}) ? 'text-slate-400' : 'text-slate-300'">{{ $s['hint'] }}</span>
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ol>

                    {{-- The second act, stated rather than implied. --}}
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <p class="px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                            Part Two: Tell The Machine
                        </p>
                        @if ($node->exists)
                            <a href="{{ route('admin.nodes.enroll', $node) }}"
                               class="mt-1.5 w-full inline-flex items-start gap-3 rounded-lg border border-transparent px-3 py-2 text-left transition hover:bg-slate-100 hover:border-slate-200">
                                <span class="inline-flex w-6 h-6 mt-px shrink-0 items-center justify-center rounded-full bg-slate-900 text-white">
                                    <x-icon name="terminal" class="w-3.5 h-3.5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-700">Run The Installer</span>
                                    <span class="block text-xs text-slate-400">One command on the box.</span>
                                </span>
                            </a>
                        @else
                            <div class="mt-1.5 flex items-start gap-3 rounded-lg px-3 py-2">
                                <span class="inline-flex w-6 h-6 mt-px shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500">
                                    <x-icon name="lock" class="w-3.5 h-3.5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-400">Run The Installer</span>
                                    <span class="block text-xs text-slate-300">Unlocks once the node exists.</span>
                                </span>
                            </div>
                        @endif
                    </div>
                </x-card>

                {{-- Editing should not require walking to the last step just to save. --}}
                <x-card padding="p-4 sm:p-5">
                    <div class="flex flex-col gap-2">
                        <x-button type="submit" class="w-full" icon="check" x-show="editing && step !== last" x-cloak>Save Node</x-button>
                        <p x-show="! editing && step < last" x-cloak class="text-sm text-slate-500">
                            The node is created at the end of the last step, and the machine is told about it after that.
                        </p>
                        <x-button href="{{ route('admin.nodes.index') }}" variant="secondary" class="w-full">Cancel</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
