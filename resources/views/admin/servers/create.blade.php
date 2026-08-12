{{-- The stepped create screen. One form, one POST, six short decisions.

     Every field stays in the DOM the whole time and keeps the name the store
     action already expects, so the controller sees exactly the payload the old
     single page form sent. All the stepping lives in the serverWizard Alpine
     component in public/js/gamemgr.js.

     Two rules shape the markup. Choosing what to run should feel like choosing
     a game, so templates are cards carrying their game's own colour and icon,
     never a row in a dropdown. And a size that will not fit anywhere has to say
     so while it is being chosen, not after the POST, so every capacity figure
     the browser needs travels down with the page.

     Editing an existing server is a different job and keeps the plain
     admin.servers.form view: nobody should walk six steps to change one memory
     limit. --}}
@php
    $steps = [
        ['n' => 1, 'label' => 'Pick A Game', 'icon' => 'controller'],
        ['n' => 2, 'label' => 'Pick A Machine', 'icon' => 'server'],
        ['n' => 3, 'label' => 'Name And Owner', 'icon' => 'users'],
        ['n' => 4, 'label' => 'Choose A Size', 'icon' => 'cpu'],
        ['n' => 5, 'label' => 'Game Settings', 'icon' => 'bolt'],
        ['n' => 6, 'label' => 'Review', 'icon' => 'check-circle'],
    ];

    // Templates arrive sorted by game, so grouping keeps that order.
    $byGame = $templates->groupBy(fn ($t) => $t->game?->name ?? 'Other');
    // $games comes from the controller now, with its template counts. Derived
    // here it had no counts, and deriving it made the picker depend on every
    // template being loaded, which is the thing the picker was changed to stop
    // doing. A {{-- --}} comment inside @php is not a comment, it is a syntax
    // error, which is how this was found.
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="controller"
                   subtitle="Six short steps. Nothing is created until you press Create Server on the last one.">
        <x-slot:actions>
            <x-button href="{{ route('admin.servers.index') }}" variant="secondary" size="sm" icon="chevron-left">
                All Servers
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div x-data="serverWizard('server-wizard-data')">
        <form method="POST" action="{{ route('admin.servers.store') }}" x-ref="form"
              @submit="onSubmit($event)" @keydown.enter="onEnter($event)"
              class="grid grid-cols-1 gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
            @csrf

            {{-- ---------------------------------------------------- the rail --}}
            <aside class="hidden lg:block">
                <div class="sticky top-20">
                    <ol class="relative">
                        {{-- The thread the numbered discs sit on. Behind them, so
                             each disc masks the piece it covers. --}}
                        <span class="absolute left-[27px] top-7 bottom-7 w-px bg-slate-200" aria-hidden="true"></span>
                        @foreach ($steps as $s)
                            <li class="relative">
                                <button type="button" @click="go({{ $s['n'] }})"
                                        :aria-current="step === {{ $s['n'] }} ? 'step' : false"
                                        class="flex w-full items-start gap-3 rounded-xl px-3 py-2.5 text-left transition"
                                        :class="step === {{ $s['n'] }}
                                            ? 'bg-white shadow-sm ring-1 ring-brand-200'
                                            : 'hover:bg-white/70'">
                                    <span class="relative z-10 mt-px inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold tabular ring-1 ring-inset transition"
                                          :class="step === {{ $s['n'] }}
                                              ? 'bg-brand-600 text-white ring-brand-600'
                                              : (furthest > {{ $s['n'] }}
                                                  ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                                  : 'bg-white text-slate-400 ring-slate-200')">
                                        <span x-show="furthest > {{ $s['n'] }} && step !== {{ $s['n'] }}" x-cloak>
                                            <x-icon name="check" class="w-3.5 h-3.5" />
                                        </span>
                                        <span x-show="!(furthest > {{ $s['n'] }} && step !== {{ $s['n'] }})">{{ $s['n'] }}</span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium"
                                              :class="step === {{ $s['n'] }} ? 'text-brand-700' : 'text-slate-700'">{{ $s['label'] }}</span>
                                        <span class="mt-0.5 block truncate text-xs text-slate-500"
                                              x-text="stepSummary({{ $s['n'] }})"></span>
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ol>

                    <div class="mt-4 px-3">
                        <div class="flex items-baseline justify-between gap-3 text-xs text-slate-500">
                            <span class="font-medium">Progress</span>
                            <span class="tabular"><span x-text="Math.round(step / total * 100)">17</span>%</span>
                        </div>
                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                            <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-brand-700 transition-all duration-300"
                                 :style="'width: ' + Math.round(step / total * 100) + '%'"></div>
                        </div>
                    </div>
                </div>
            </aside>

            <div class="min-w-0">
                {{-- Six labelled pills do not fit a phone, so narrow screens get
                     the current step, a counter and a bar. Nothing scrolls sideways. --}}
                <div class="mb-5 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200 lg:hidden">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="min-w-0 truncate text-sm font-semibold text-slate-900">
                            @foreach ($steps as $s)
                                <span x-show="step === {{ $s['n'] }}" @if ($s['n'] > 1) x-cloak @endif>{{ $s['label'] }}</span>
                            @endforeach
                        </p>
                        <p class="shrink-0 text-xs tabular text-slate-500">
                            Step <span x-text="step">1</span> Of <span x-text="total">6</span>
                        </p>
                    </div>
                    <p class="mt-0.5 truncate text-xs text-slate-500" x-text="stepSummary(step)"></p>
                    <div class="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-brand-700 transition-all duration-300"
                             :style="'width: ' + Math.round(step / total * 100) + '%'"></div>
                    </div>
                </div>

                {{-- ------------------------------------------------ step one --}}
                <div data-step="1" x-show="step === 1" x-cloak class="space-y-6"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    @if ($templates->isEmpty())
                        <x-card>
                            <x-empty-state icon="cube" title="No Templates Yet"
                                           description="A server needs something to run. Import a template or write one, then come back.">
                                <x-slot:action>
                                    <x-button href="{{ route('admin.templates.import') }}" size="sm" icon="download">
                                        Import A Template
                                    </x-button>
                                </x-slot:action>
                            </x-empty-state>
                        </x-card>
                    @else
                        <x-card title="Pick A Game" icon="controller"
                                subtitle="Every template on this panel, grouped by the game it runs.">
                            <div class="space-y-6">
                                <div class="relative sm:max-w-sm">
                                    <label for="template-search" class="sr-only">Search Games And Templates</label>
                                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 w-4 h-4 -translate-y-1/2 text-slate-400" />
                                    <input id="template-search" type="search" x-model="query" autocomplete="off"
                                           placeholder="Search games, templates, runtimes"
                                           class="block w-full rounded-lg border-0 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                </div>

                                {{-- Named gameGroup, not group. A Blade foreach leaves its
                                     variable behind in the shared scope, and step five
                                     includes _variable.blade.php, which reads a variable of
                                     that name as the form field array name. The bare one
                                     therefore renamed every template setting to a JSON dump
                                     of this loop, so nothing typed on step five ever posted. --}}
                                {{-- Games first, templates on demand.

                                     This used to render every game AND every one of
                                     its templates as a card, all in the page, filtered
                                     with x-show. At nine templates that was a nice
                                     picker. At two hundred and fifty nine it is an
                                     unusable wall and most of a megabyte, and the games
                                     are what somebody is actually looking for. Picking
                                     one fetches its templates. --}}
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                                    @foreach ($games as $game)
                                        <button type="button"
                                                x-show="gameMatches({{ Illuminate\Support\Js::from($game->name.' '.$game->slug) }})"
                                                @click="pickGame('{{ $game->id }}')"
                                                class="gm-pick flex items-center gap-2.5 rounded-lg bg-white p-2 text-left ring-1 ring-inset transition"
                                                :class="pickedGame === '{{ $game->id }}'
                                                    ? 'ring-2 ring-brand-500 bg-brand-50/60'
                                                    : 'ring-slate-200 hover:ring-brand-300'">
                                            <x-game-art :game="$game" class="h-9 w-9 rounded-md" icon-class="w-4 h-4" />
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-medium text-slate-900">{{ $game->name }}</span>
                                                <span class="block text-xs text-slate-400">
                                                    {{ $game->templates_count }} {{ \Illuminate\Support\Str::plural('template', $game->templates_count) }}
                                                </span>
                                            </span>
                                        </button>
                                    @endforeach
                                </div>

                                {{-- The chosen game's templates, fetched. --}}
                                <div id="game-templates" class="mt-2"
                                     data-game-templates-url="{{ route('admin.servers.game-templates', ['game' => '__ID__']) }}"></div>

                                <div x-show="matchCount === 0" x-cloak>
                                    <x-empty-state icon="search" title="Nothing Matches That"
                                                   description="No template on this panel answers to that. Clear the search and browse instead.">
                                        <x-slot:action>
                                            <x-button type="button" variant="secondary" size="sm" @click="query = ''">
                                                Clear Search
                                            </x-button>
                                        </x-slot:action>
                                    </x-empty-state>
                                </div>
                            </div>

                            <x-slot:footer>
                                <div class="space-y-4">
                                    <p class="line-clamp-3 text-sm text-slate-600"
                                       x-text="template ? template.description : ''"></p>
                                    <button type="button" @click="showAdvanced = !showAdvanced"
                                            class="inline-flex items-center gap-1.5 rounded-lg text-sm font-medium text-slate-600 transition hover:text-slate-900">
                                        <x-icon name="chevron-down" class="w-4 h-4 transition-transform"
                                                ::class="showAdvanced && 'rotate-180'" />
                                        <span x-text="showAdvanced ? 'Hide Advanced Overrides' : 'Show Advanced Overrides'">Show Advanced Overrides</span>
                                    </button>

                                    <div x-show="showAdvanced" x-cloak class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <x-field label="Docker Image" hint="Blank uses the template default."
                                                 :error="$errors->first('image')">
                                            <x-input name="image" x-model="image" class="font-mono text-xs"
                                                     x-bind:placeholder="template && template.default_image ? template.default_image : ''" />
                                        </x-field>
                                        <x-field label="Startup Override" hint="Blank uses the template command."
                                                 :error="$errors->first('startup')">
                                            <x-input name="startup" x-model="startup" class="font-mono text-xs"
                                                     x-bind:placeholder="template && template.startup ? template.startup : ''" />
                                        </x-field>
                                    </div>
                                </div>
                            </x-slot:footer>
                        </x-card>
                    @endif
                </div>

                {{-- ------------------------------------------------ step two --}}
                <div data-step="2" x-show="step === 2" x-cloak class="space-y-6"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    @error('node_id')
                        <x-alert type="danger" title="That Placement Will Not Work">{{ $message }}</x-alert>
                    @enderror

                    <x-card title="Pick A Machine" icon="cpu"
                            subtitle="Auto puts it on the emptiest machine that can run this template and has the room.">
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Placement">
                                <label class="gm-pick flex cursor-pointer items-start gap-3 rounded-xl p-3 ring-1 ring-inset transition"
                                       :class="placement === 'auto' ? 'ring-2 ring-brand-500 bg-brand-50/60' : 'ring-slate-200 hover:ring-brand-300'">
                                    <input type="radio" x-model="placement" value="auto" class="sr-only">
                                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset transition"
                                          :class="placement === 'auto' ? 'bg-brand-600 text-white ring-brand-600' : 'bg-slate-100 text-slate-500 ring-slate-200'">
                                        <x-icon name="sparkles" class="w-5 h-5" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-slate-900">Place It For Me</span>
                                        <span class="block text-xs text-slate-500">The emptiest machine with room, chosen at create time.</span>
                                    </span>
                                </label>
                                <label class="gm-pick flex cursor-pointer items-start gap-3 rounded-xl p-3 ring-1 ring-inset transition"
                                       :class="placement === 'manual' ? 'ring-2 ring-brand-500 bg-brand-50/60' : 'ring-slate-200 hover:ring-brand-300'">
                                    <input type="radio" x-model="placement" value="manual" class="sr-only">
                                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset transition"
                                          :class="placement === 'manual' ? 'bg-brand-600 text-white ring-brand-600' : 'bg-slate-100 text-slate-500 ring-slate-200'">
                                        <x-icon name="target" class="w-5 h-5" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-slate-900">Choose It Myself</span>
                                        <span class="block text-xs text-slate-500">I know exactly which machine this goes on.</span>
                                    </span>
                                </label>
                            </div>

                            {{-- automatic ------------------------------------- --}}
                            <div x-show="placement === 'auto'" class="space-y-4">
                                <div class="sm:max-w-sm">
                                    <x-field label="Prefer This Location" hint="Leave it on Anywhere to consider every location."
                                             :error="$errors->first('location_id')">
                                        <x-select name="location_id" x-model="locationId"
                                                  x-bind:disabled="placement !== 'auto'">
                                            <option value="">Anywhere</option>
                                            @foreach ($locations as $location)
                                                <option value="{{ $location->id }}">{{ $location->flag }} {{ $location->name }}</option>
                                            @endforeach
                                        </x-select>
                                    </x-field>
                                </div>

                                <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200">
                                    <template x-if="autoPick">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                            <x-icon name="check-circle" class="w-4 h-4 shrink-0 text-emerald-600" />
                                            <span class="text-slate-600">On today's numbers this would land on</span>
                                            <span class="font-semibold text-slate-900" x-text="autoPick.name"></span>
                                            <span class="text-slate-500" x-text="'(' + (autoPick.location || 'no location') + ', ' + usedPct(autoPick, 'memory') + '% full)'"></span>
                                        </div>
                                    </template>
                                    <template x-if="!autoPick">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                            <x-icon name="warning" class="w-4 h-4 shrink-0 text-amber-600" />
                                            <span class="text-slate-600">
                                                Nothing has room for this size here yet. Change the size on step four, widen the location, or choose a machine by hand.
                                            </span>
                                        </div>
                                    </template>
                                    <p class="mt-2 text-xs text-slate-500">
                                        <span x-text="autoCandidates.length"></span> of <span x-text="nodeChoices.length"></span>
                                        suitable machines can take it. The real choice is made when you press Create Server.
                                    </p>

                                    {{-- Automatic should still show its working:
                                         these are the machines in the running,
                                         emptiest first, which is the order the
                                         controller picks in. --}}
                                    <div class="mt-4 space-y-2.5" x-show="autoCandidates.length > 0" x-cloak>
                                        <template x-for="(n, i) in autoCandidates.slice().sort((a, b) => usedPct(a, 'memory') - usedPct(b, 'memory'))" :key="n.id">
                                            <div class="flex items-center gap-3">
                                                <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold tabular ring-1 ring-inset"
                                                      :class="i === 0 ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-slate-400 ring-slate-200'"
                                                      x-text="i + 1"></span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="flex items-baseline justify-between gap-3 text-xs">
                                                        <span class="min-w-0 truncate font-medium text-slate-700" x-text="n.name"></span>
                                                        <span class="shrink-0 tabular text-slate-500"
                                                              x-text="(n.location || 'No location') + ', ' + usedPct(n, 'memory') + '% full'"></span>
                                                    </span>
                                                    <span class="mt-1 flex h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                                        <span class="h-full bg-slate-400 transition-all" :style="'width: ' + usedPct(n, 'memory') + '%'"></span>
                                                        <span class="h-full bg-brand-500 transition-all" :style="'width: ' + askPct(n, 'memory') + '%'"></span>
                                                    </span>
                                                </span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            {{-- by hand ---------------------------------------- --}}
                            <div x-show="placement === 'manual'" x-cloak class="space-y-4">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Machine">
                                    @foreach ($nodes as $node)
                                        @php $freePorts = $node->freeAllocations()->count(); @endphp
                                        <label x-show="canRun({{ Illuminate\Support\Js::from(array_values($node->runtimes ?? [])) }})" x-cloak
                                               class="gm-pick flex cursor-pointer flex-col gap-3 rounded-xl bg-white p-4 ring-1 ring-inset transition"
                                               :class="nodeId === '{{ $node->id }}'
                                                   ? 'ring-2 ring-brand-500 bg-brand-50/40 shadow-sm'
                                                   : 'ring-slate-200 hover:ring-brand-300 hover:shadow-sm'">
                                            <input type="radio" name="node_id" value="{{ $node->id }}" x-model="nodeId"
                                                   class="sr-only" x-bind:disabled="placement !== 'manual'">

                                            <span class="flex items-start justify-between gap-3">
                                                <span class="min-w-0">
                                                    <span class="flex items-center gap-2">
                                                        {{-- The global tooltip on <body>, never a ::after,
                                                             which a card's own rounding would clip. --}}
                                                        <x-status-dot :tone="$node->statusTone()"
                                                                      data-tip="{{ $node->statusLabel() }}" />
                                                        <span class="min-w-0 truncate text-sm font-semibold text-slate-900">{{ $node->name }}</span>
                                                    </span>
                                                    <span class="mt-1 block truncate text-xs text-slate-500">
                                                        {{ $node->location?->flag }} {{ $node->location?->name ?? 'No Location' }}
                                                        &middot; {{ $node->servers_count }} {{ \Illuminate\Support\Str::plural('server', $node->servers_count) }}
                                                        &middot; {{ $freePorts }} free {{ \Illuminate\Support\Str::plural('port', $freePorts) }}
                                                    </span>
                                                </span>
                                                <span class="shrink-0">
                                                    <span x-show="fits(nodeById({{ $node->id }}))">
                                                        <x-badge color="success" dot>Room For It</x-badge>
                                                    </span>
                                                    <span x-show="!fits(nodeById({{ $node->id }}))" x-cloak>
                                                        <x-badge color="warn" dot>
                                                            <span x-text="verdictLabel(nodeById({{ $node->id }}))"></span>
                                                        </x-badge>
                                                    </span>
                                                </span>
                                            </span>

                                            <span class="flex flex-wrap gap-1.5">
                                                @foreach ($node->runtimes ?? [] as $runtime)
                                                    <x-runtime-badge :runtime="$runtime" />
                                                @endforeach
                                            </span>

                                            <span class="block space-y-2">
                                                <x-meter :value="$node->memoryAllocated()" :max="$node->memoryCapacity()" label="Memory">
                                                    {{ round($node->memoryAllocated() / 1024, 1) }} of {{ round($node->memoryCapacity() / 1024) }} GiB
                                                </x-meter>
                                                <x-meter :value="$node->diskAllocated()" :max="$node->diskCapacity()" label="Disk">
                                                    {{ round($node->diskAllocated() / 1024) }} of {{ round($node->diskCapacity() / 1024) }} GiB
                                                </x-meter>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>

                                <div x-show="nodeChoices.length === 0" x-cloak>
                                    <x-empty-state icon="server" title="No Machine Can Run It"
                                                   description="Not one node has this template's runtime enabled. Turn it on for a node, or pick a different template." />
                                </div>

                                <p class="text-sm text-slate-500" x-show="hiddenNodeCount > 0" x-cloak>
                                    <span x-text="hiddenNodeCount"></span> hidden, because they cannot run
                                    <span x-text="template ? template.runtime_label : ''"></span> templates.
                                </p>

                                <div class="sm:max-w-sm" x-show="nodeId" x-cloak>
                                    <x-field label="Game Port"
                                             hint="Leave it automatic and the game gets its canonical port, or the nearest free set if something already holds it."
                                             :error="$errors->first('allocation_id')">
                                        <x-select name="allocation_id" x-model="allocationId"
                                                  x-bind:disabled="placement !== 'manual'">
                                            <option value="">Automatic, Canonical Where It Can Be</option>
                                            <template x-for="a in allocationChoices" :key="a.id">
                                                <option :value="a.id" x-text="a.label + (a.notes ? ' (' + a.notes + ')' : '')"></option>
                                            </template>
                                        </x-select>
                                    </x-field>
                                </div>

                                {{-- Only a template with no declared port set actually depends on
                                     a free row already sitting in the pool. One that has a set
                                     brings its own numbers and the planner adds them. --}}
                                <div x-show="nodeId && allocationChoices.length === 0 && !(template && template.port_set.length)" x-cloak>
                                    <x-alert type="warn" title="No Free Ports Left">
                                        This machine has no unassigned ports, and this template does not declare which ports its
                                        game needs. Add allocations to the machine first, or pick another one.
                                    </x-alert>
                                </div>
                            </div>

                            {{-- Outside both placement branches, because what a game
                                 listens on is the same whether the machine was picked
                                 by hand or chosen for you. The whole set is reserved
                                 together, so showing one port here would be exactly the
                                 half truth the old allocator told. --}}
                            <div class="section-divider pt-5" x-show="template && template.port_set.length" x-cloak>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Ports This Game Needs</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <template x-for="p in (template ? template.port_set : [])" :key="p.port">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-200">
                                            <span class="tabular" x-text="p.port"></span>
                                            <span class="text-slate-400" x-text="p.protocol"></span>
                                            <span x-text="p.label"></span>
                                        </span>
                                    </template>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">
                                    All of them are reserved together on one address, or none are. On an address with nothing
                                    else on it the game gets its real port,
                                    <span class="tabular font-medium text-slate-700" x-text="template ? template.canonical_port : ''"></span>.
                                    On a busy address the whole set shifts by the same amount and you are told by how much.
                                </p>
                            </div>
                        </div>
                    </x-card>
                </div>

                {{-- ---------------------------------------------- step three --}}
                <div data-step="3" x-show="step === 3" x-cloak class="space-y-6"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <x-card title="Name And Owner" icon="users" subtitle="What this server is called, and who it belongs to.">
                        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                            <div class="space-y-4">
                                <x-field label="Name" for="server-name" required :error="$errors->first('name')">
                                    <x-input id="server-name" name="name" x-model="name" required maxlength="120"
                                             placeholder="Survival SMP" />
                                </x-field>
                                <x-field label="Description" for="server-description"
                                         hint="Optional. Shown in the server list."
                                         :error="$errors->first('description')">
                                    <x-input id="server-description" name="description" x-model="description" maxlength="500"
                                             placeholder="Friday nights, twelve players" />
                                </x-field>
                                <x-field label="Owner" for="server-owner" required :error="$errors->first('owner_id')">
                                    <x-select id="server-owner" name="owner_id" x-model="ownerId" required>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                        @endforeach
                                    </x-select>
                                </x-field>
                            </div>

                            {{-- What the server list will show once this exists. --}}
                            <div>
                                <p class="text-sm font-medium text-slate-700">How It Will Look</p>
                                <div class="mt-1.5 rounded-xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200">
                                    <div class="flex items-start gap-3">
                                        <span class="relative inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-white ring-1 ring-inset ring-black/10">
                                            @foreach ($templates as $template)
                                                <span x-show="templateId === '{{ $template->id }}'" @if (! $loop->first) x-cloak @endif
                                                      class="gm-art gm-art-{{ $template->game?->id ?? 0 }} absolute inset-0 inline-flex items-center justify-center rounded-lg">
                                                    <x-icon :name="$template->game?->icon ?: 'controller'" class="w-5 h-5" />
                                                </span>
                                            @endforeach
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-slate-900"
                                               x-text="name || 'Unnamed Server'">Unnamed Server</p>
                                            <p class="truncate text-xs text-slate-500"
                                               x-text="description || (template ? template.game + ' : ' + template.name : '')"></p>
                                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                                <x-badge color="warn" dot>Installing</x-badge>
                                                <x-badge>
                                                    <span x-text="placement === 'manual' && node ? node.name : (autoPick ? autoPick.name : 'Machine picked at create time')"></span>
                                                </x-badge>
                                            </div>
                                        </div>
                                    </div>
                                    <dl class="mt-4 space-y-1.5 border-t border-slate-200 pt-3 text-xs">
                                        <div class="flex items-baseline justify-between gap-3">
                                            <dt class="text-slate-500">Owner</dt>
                                            <dd class="min-w-0 truncate text-slate-900" x-text="owner ? owner.name : 'Nobody yet'"></dd>
                                        </div>
                                        <div class="flex items-baseline justify-between gap-3">
                                            <dt class="text-slate-500">Size</dt>
                                            <dd class="min-w-0 truncate tabular text-slate-900" x-text="stepSummary(4)"></dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </x-card>
                </div>

                {{-- ----------------------------------------------- step four --}}
                <div data-step="4" x-show="step === 4" x-cloak class="space-y-6"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">

                    @if ($blueprints->isNotEmpty())
                        {{-- Only worth a card when this template actually has
                             saved sizes. Otherwise the sliders below are the
                             whole story and an empty panel is just noise. --}}
                        <div x-show="blueprintChoices.length > 0" x-cloak>
                            <x-card title="Start From A Saved Size" icon="sparkles"
                                    subtitle="Sizes your panel already keeps for this template. One click fills in every limit below.">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($blueprints as $blueprint)
                                        <button type="button" x-show="String(templateId) === '{{ $blueprint->template_id }}'" x-cloak
                                                @click="applyBlueprint({{ $blueprint->id }})"
                                                class="flex flex-col gap-2 rounded-xl bg-white p-4 text-left ring-1 ring-inset transition"
                                                :class="blueprintId === '{{ $blueprint->id }}'
                                                    ? 'ring-2 ring-brand-500 bg-brand-50/60 shadow-sm'
                                                    : 'ring-slate-200 hover:ring-brand-300 hover:shadow-sm'">
                                            <span class="flex items-start justify-between gap-2">
                                                <span class="min-w-0 text-sm font-semibold text-slate-900">{{ $blueprint->name }}</span>
                                                <span x-show="recommendedBlueprintId === '{{ $blueprint->id }}'" x-cloak class="shrink-0">
                                                    <x-badge color="info">Recommended</x-badge>
                                                </span>
                                            </span>
                                            @if ($blueprint->description)
                                                <span class="block text-xs text-slate-500">{{ $blueprint->description }}</span>
                                            @endif
                                            <span class="mt-auto flex flex-wrap gap-1.5 pt-1">
                                                <x-badge><x-icon name="memory" class="w-3.5 h-3.5" /> {{ round(($blueprint->limits['memory'] ?? 0) / 1024, 1) }} GiB</x-badge>
                                                <x-badge><x-icon name="database" class="w-3.5 h-3.5" /> {{ round(($blueprint->limits['disk'] ?? 0) / 1024) }} GiB</x-badge>
                                                <x-badge><x-icon name="cpu" class="w-3.5 h-3.5" /> {{ ($blueprint->limits['cpu'] ?? 0) / 100 }} {{ \Illuminate\Support\Str::plural('Core', ($blueprint->limits['cpu'] ?? 0) / 100) }}</x-badge>
                                            </span>
                                            <span class="block text-xs"
                                                  :class="blueprintNodeCount({{ $blueprint->id }}) > 0 ? 'text-slate-500' : 'text-rose-600'"
                                                  x-text="blueprintFitLabel({{ $blueprint->id }})"></span>
                                        </button>
                                    @endforeach
                                </div>
                            </x-card>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div class="space-y-6 lg:col-span-2">
                            <x-card title="Size" icon="memory" subtitle="Drag for the common sizes, type for anything else.">
                                <div class="space-y-6">
                                    {{-- memory --}}
                                    <div>
                                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                                            <label for="memory" class="text-sm font-medium text-slate-700">Memory <span class="text-rose-500">*</span></label>
                                            <span class="text-sm tabular text-slate-500" x-text="mib(res.memory)">2 GiB</span>
                                        </div>
                                        <div class="mt-2 flex items-center gap-3">
                                            <input type="range" min="0" :max="memStops.length - 1"
                                                   :value="stopIndex(memStops, res.memory)"
                                                   @input="setStop('memory', memStops, $event.target.value)"
                                                   aria-label="Memory"
                                                   class="h-2 min-w-0 flex-1 cursor-pointer appearance-none rounded-full bg-slate-200 accent-brand-600">
                                            <div class="flex shrink-0 items-center gap-1.5">
                                                <input type="number" id="memory" name="memory" x-model.number="res.memory"
                                                       required min="0" step="1"
                                                       class="block w-24 rounded-lg border-0 bg-white px-3 py-2 text-sm tabular text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                                <span class="text-xs text-slate-500">MiB</span>
                                            </div>
                                        </div>
                                        @error('memory')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    {{-- disk --}}
                                    <div>
                                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                                            <label for="disk" class="text-sm font-medium text-slate-700">Disk <span class="text-rose-500">*</span></label>
                                            <span class="text-sm tabular text-slate-500" x-text="mib(res.disk)">10 GiB</span>
                                        </div>
                                        <div class="mt-2 flex items-center gap-3">
                                            <input type="range" min="0" :max="diskStops.length - 1"
                                                   :value="stopIndex(diskStops, res.disk)"
                                                   @input="setStop('disk', diskStops, $event.target.value)"
                                                   aria-label="Disk"
                                                   class="h-2 min-w-0 flex-1 cursor-pointer appearance-none rounded-full bg-slate-200 accent-brand-600">
                                            <div class="flex shrink-0 items-center gap-1.5">
                                                <input type="number" id="disk" name="disk" x-model.number="res.disk"
                                                       required min="0" step="1"
                                                       class="block w-24 rounded-lg border-0 bg-white px-3 py-2 text-sm tabular text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                                <span class="text-xs text-slate-500">MiB</span>
                                            </div>
                                        </div>
                                        @error('disk')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    {{-- cpu --}}
                                    <div>
                                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                                            <label for="cpu" class="text-sm font-medium text-slate-700">CPU <span class="text-rose-500">*</span></label>
                                            <span class="text-sm tabular text-slate-500" x-text="cores(res.cpu)">2 Cores</span>
                                        </div>
                                        <div class="mt-2 flex items-center gap-3">
                                            <input type="range" min="0" :max="cpuStops.length - 1"
                                                   :value="stopIndex(cpuStops, res.cpu)"
                                                   @input="setStop('cpu', cpuStops, $event.target.value)"
                                                   aria-label="CPU"
                                                   class="h-2 min-w-0 flex-1 cursor-pointer appearance-none rounded-full bg-slate-200 accent-brand-600">
                                            <div class="flex shrink-0 items-center gap-1.5">
                                                <input type="number" id="cpu" name="cpu" x-model.number="res.cpu"
                                                       required min="0" step="1"
                                                       class="block w-24 rounded-lg border-0 bg-white px-3 py-2 text-sm tabular text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                                <span class="text-xs text-slate-500">%</span>
                                            </div>
                                        </div>
                                        <p class="mt-1.5 text-sm text-slate-500">100% is one core. Most game servers only ever use one.</p>
                                        @error('cpu')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <x-slot:footer>
                                    <div class="space-y-4">
                                        <button type="button" @click="showFineTuning = !showFineTuning"
                                                class="inline-flex items-center gap-1.5 rounded-lg text-sm font-medium text-slate-600 transition hover:text-slate-900">
                                            <x-icon name="chevron-down" class="w-4 h-4 transition-transform"
                                                    ::class="showFineTuning && 'rotate-180'" />
                                            <span x-text="showFineTuning ? 'Hide Swap And Block IO' : 'Show Swap And Block IO'">Show Swap And Block IO</span>
                                        </button>

                                        <div x-show="showFineTuning" x-cloak class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <x-field label="Swap" for="swap" required
                                                     hint="-1 for unlimited, 0 for none." :error="$errors->first('swap')">
                                                <div class="flex items-center gap-1.5">
                                                    <input type="number" id="swap" name="swap" x-model.number="res.swap"
                                                           required min="-1" step="1"
                                                           class="block w-28 rounded-lg border-0 bg-white px-3 py-2 text-sm tabular text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                                    <span class="text-xs text-slate-500">MiB</span>
                                                    <span class="ms-2 text-xs text-slate-500" x-text="mib(res.swap)"></span>
                                                </div>
                                            </x-field>
                                            <x-field label="Block IO Weight" for="io" required
                                                     hint="Between 10 and 1000. Higher gets more disk time under load."
                                                     :error="$errors->first('io')">
                                                <div class="flex items-center gap-3">
                                                    <input type="range" min="10" max="1000" step="10" x-model.number="res.io"
                                                           aria-label="Block IO Weight"
                                                           class="h-2 min-w-0 flex-1 cursor-pointer appearance-none rounded-full bg-slate-200 accent-brand-600">
                                                    <input type="number" id="io" name="io" x-model.number="res.io"
                                                           required min="10" max="1000" step="1"
                                                           class="block w-20 shrink-0 rounded-lg border-0 bg-white px-3 py-2 text-sm tabular text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                                </div>
                                            </x-field>
                                        </div>
                                    </div>
                                </x-slot:footer>
                            </x-card>

                            <x-card title="What The Owner May Add Later" icon="lock"
                                    subtitle="Caps the owner manages for themselves, without asking you.">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    @foreach ([
                                        ['database_limit', 'Databases', 50],
                                        ['allocation_limit', 'Extra Ports', 50],
                                        ['backup_limit', 'Backups', 200],
                                    ] as [$field, $label, $ceiling])
                                        <x-field :label="$label" :for="$field" required :error="$errors->first($field)">
                                            <div class="flex items-center gap-2">
                                                <button type="button" @click="nudge('{{ $field }}', -1, 0, {{ $ceiling }})"
                                                        aria-label="Fewer {{ $label }}"
                                                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-600 ring-1 ring-inset ring-slate-300 transition hover:bg-slate-50 hover:text-slate-900">
                                                    <span aria-hidden="true" class="text-lg leading-none">&minus;</span>
                                                </button>
                                                <input type="number" id="{{ $field }}" name="{{ $field }}"
                                                       x-model.number="res.{{ $field }}" required min="0" max="{{ $ceiling }}"
                                                       class="block w-full min-w-0 rounded-lg border-0 bg-white px-3 py-2 text-center text-sm tabular text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                                <button type="button" @click="nudge('{{ $field }}', 1, 0, {{ $ceiling }})"
                                                        aria-label="More {{ $label }}"
                                                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-600 ring-1 ring-inset ring-slate-300 transition hover:bg-slate-50 hover:text-slate-900">
                                                    <x-icon name="plus" class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </x-field>
                                    @endforeach
                                </div>

                                <div class="section-divider mt-5 space-y-4 pt-5">
                                    <x-toggle name="start_on_install" :checked="(bool) old('start_on_install', $server->start_on_install ?? true)"
                                              label="Start When The Install Finishes"
                                              description="A large game can take an hour to download. This brings it up the moment it is ready rather than leaving it offline until somebody looks." />
                                    <x-toggle name="auto_restart" :checked="(bool) old('auto_restart', $server->auto_restart)"
                                              label="Restart After A Crash"
                                              description="The watchdog brings it back unless it was stopped on purpose." />
                                    <x-toggle name="auto_update" :checked="(bool) old('auto_update', $server->auto_update)"
                                              label="Update Game Files Automatically"
                                              description="Runs the template update command before every boot." />
                                </div>
                            </x-card>
                        </div>

                        {{-- Will it fit. Answered here, live, rather than by a
                             rejected POST two steps later. --}}
                        <div class="lg:col-span-1">
                            <div class="lg:sticky lg:top-20">
                                <x-card title="Will It Fit" icon="chart">
                                    <div class="space-y-4">
                                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 rounded-lg bg-slate-50 px-3 py-2 text-sm ring-1 ring-inset ring-slate-200">
                                            <span class="text-slate-500">Asking for</span>
                                            <span class="font-semibold tabular text-slate-900" x-text="mib(res.memory)"></span>
                                            <span class="text-slate-500">memory and</span>
                                            <span class="font-semibold tabular text-slate-900" x-text="mib(res.disk)"></span>
                                            <span class="text-slate-500">disk.</span>
                                        </div>

                                        {{-- chosen by hand --}}
                                        <template x-if="placement === 'manual' && node">
                                            <div class="space-y-4">
                                                <div>
                                                    <p class="truncate text-sm font-semibold text-slate-900" x-text="node.name"></p>
                                                    <p class="mt-1.5">
                                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
                                                              :class="fits(node) ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-rose-50 text-rose-700 ring-rose-200'"
                                                              x-text="fits(node) ? 'Room For It' : verdictLabel(node)"></span>
                                                    </p>
                                                </div>

                                                <template x-for="kind in ['memory', 'disk']" :key="kind">
                                                    <div>
                                                        <div class="flex items-baseline justify-between gap-3 text-sm">
                                                            <span class="font-medium capitalize text-slate-700" x-text="kind"></span>
                                                            <span class="tabular text-slate-500"
                                                                  x-text="freeLabel(node, kind)"></span>
                                                        </div>
                                                        <div class="mt-1.5 flex h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                                                            <div class="h-full bg-slate-300 transition-all" :style="'width: ' + usedPct(node, kind) + '%'"></div>
                                                            <div class="h-full bg-brand-500 transition-all" :style="'width: ' + askPct(node, kind) + '%'"></div>
                                                            <div class="h-full bg-rose-500 transition-all" :style="'width: ' + overPct(node, kind) + '%'"></div>
                                                        </div>
                                                    </div>
                                                </template>

                                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                                    <span class="inline-flex items-center gap-1.5">
                                                        <span class="inline-block h-2 w-2 rounded-full bg-slate-300"></span> In use
                                                    </span>
                                                    <span class="inline-flex items-center gap-1.5">
                                                        <span class="inline-block h-2 w-2 rounded-full bg-brand-500"></span> This server
                                                    </span>
                                                    <span class="inline-flex items-center gap-1.5" x-show="!fits(node)" x-cloak>
                                                        <span class="inline-block h-2 w-2 rounded-full bg-rose-500"></span> Over capacity
                                                    </span>
                                                </div>
                                            </div>
                                        </template>

                                        <template x-if="placement === 'manual' && !node">
                                            <p class="text-sm text-slate-500">
                                                No machine chosen yet. Go back a step to pick one, or switch to automatic placement.
                                            </p>
                                        </template>

                                        {{-- automatic --}}
                                        <template x-if="placement === 'auto'">
                                            <div class="space-y-4">
                                                <div class="flex items-baseline justify-between gap-3">
                                                    <span class="text-sm text-slate-600">Machines With Room</span>
                                                    <span class="text-sm font-semibold tabular"
                                                          :class="autoCandidates.length ? 'text-emerald-700' : 'text-rose-600'">
                                                        <span x-text="autoCandidates.length">0</span> of <span x-text="nodeChoices.length">0</span>
                                                    </span>
                                                </div>

                                                <div class="space-y-3" x-show="autoCandidates.length > 0" x-cloak>
                                                    <template x-for="n in autoCandidates.slice(0, 4)" :key="n.id">
                                                        <div>
                                                            <div class="flex items-baseline justify-between gap-3 text-xs">
                                                                <span class="min-w-0 truncate font-medium text-slate-700" x-text="n.name"></span>
                                                                <span class="shrink-0 tabular text-slate-500"
                                                                      x-text="freeLabel(n, 'memory')"></span>
                                                            </div>
                                                            <div class="mt-1 flex h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                                                <div class="h-full bg-slate-300 transition-all" :style="'width: ' + usedPct(n, 'memory') + '%'"></div>
                                                                <div class="h-full bg-brand-500 transition-all" :style="'width: ' + askPct(n, 'memory') + '%'"></div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>

                                                <div x-show="autoCandidates.length === 0" x-cloak>
                                                    <x-alert type="danger" title="Nothing Has Room">
                                                        Every suitable machine is too full for
                                                        <span x-text="mib(res.memory)"></span> of memory and
                                                        <span x-text="mib(res.disk)"></span> of disk. Ask for less, widen the
                                                        location, or free up capacity first.
                                                    </x-alert>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </x-card>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ----------------------------------------------- step five --}}
                <div data-step="5" x-show="step === 5" x-cloak class="space-y-6"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    {{-- Same shape as the variable blocks below: one per template,
                         all in the DOM, Alpine shows the chosen one. Only rendered
                         for templates that install a paid game, so the common case
                         never sees a Steam question it has no answer for. --}}
                    @foreach ($templates->where('requires_steam_account', true) as $template)
                        <div x-show="templateId === '{{ $template->id }}'" x-cloak>
                            <x-card title="Steam Account" icon="key"
                                    subtitle="{{ $template->game?->name ?: $template->name }} cannot be downloaded anonymously. Pick an account that owns it.">
                                @if ($steamAccounts->isEmpty())
                                    <x-alert type="warn">
                                        No Steam accounts are registered, so this install will fail at the login step.
                                        <a href="{{ route('admin.steam-accounts.create') }}" class="font-medium underline">Add one first</a>.
                                    </x-alert>
                                @else
                                    <x-field label="Steam Account" :error="$errors->first('steam_account_id')"
                                             hint="Stored once in Admin, never shown to the client who owns this server.">
                                        <x-select name="steam_account_id">
                                            @foreach ($steamAccounts as $steamAccount)
                                                <option value="{{ $steamAccount->id }}" @selected(old('steam_account_id') == $steamAccount->id)>
                                                    {{ $steamAccount->label }}
                                                </option>
                                            @endforeach
                                        </x-select>
                                    </x-field>
                                @endif
                            </x-card>
                        </div>
                    @endforeach

                    <x-card title="Game Settings"
                            subtitle="These are baked into the startup command. The template defaults are already filled in.">
                        {{-- Loaded when a template is chosen, not all at once.

                             Rendering every template's settings behind x-show
                             produced a 5.7 MB page at two hundred and fifty nine
                             templates. The same partial renders here for the one
                             template that matters, fetched from
                             admin.servers.template-fields. --}}
                        <div id="template-fields"
                             data-template-fields-url="{{ route('admin.servers.template-fields', ['template' => '__ID__']) }}">
                            <div class="py-8 text-center text-sm text-slate-500" data-fields-loading>
                                Loading this template's settings...
                            </div>
                        </div>
                    </x-card>
                </div>

                {{-- ------------------------------------------------ step six --}}
                <div data-step="6" x-show="step === 6" x-cloak class="space-y-6"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">

                    <x-card flush>
                        <div class="relative">
                            {{-- The chosen game's own colour, washed across the
                                 header, so the review looks like the thing being
                                 made rather than a form printout. --}}
                            @foreach ($games as $game)
                                <span aria-hidden="true" x-cloak
                                      x-show="template && String(template.game_id) === '{{ $game->id }}'"
                                      class="gm-wash gm-art-{{ $game->id }} absolute inset-0"></span>
                            @endforeach

                            <div class="relative flex flex-wrap items-center gap-4 p-5 sm:p-6">
                                <span class="relative inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-xl text-white ring-1 ring-inset ring-black/10">
                                    @foreach ($templates as $template)
                                        <span x-show="templateId === '{{ $template->id }}'" @if (! $loop->first) x-cloak @endif
                                              class="gm-art gm-art-{{ $template->game?->id ?? 0 }} absolute inset-0 inline-flex items-center justify-center rounded-xl">
                                            <x-icon :name="$template->game?->icon ?: 'controller'" class="w-7 h-7" />
                                        </span>
                                    @endforeach
                                </span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate text-lg font-semibold text-slate-900" x-text="sum.name || 'Unnamed Server'">Unnamed Server</h3>
                                    <p class="truncate text-sm text-slate-600">
                                        <span x-text="sum.game"></span>
                                        <span x-show="sum.game">&middot;</span>
                                        <span x-text="sum.template"></span>
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-wrap gap-1.5">
                                    <x-badge color="info"><span x-text="sum.runtime"></span></x-badge>
                                    <x-badge><span x-text="sum.memory"></span></x-badge>
                                    <x-badge><span x-text="sum.cpu"></span></x-badge>
                                </div>
                            </div>
                        </div>
                    </x-card>

                    <div x-show="sum.fits === false" x-cloak>
                        <x-alert type="warn" title="This May Not Fit">
                            The machine this would land on does not have room for
                            <span x-text="sum.memory"></span> of memory and <span x-text="sum.disk"></span> of disk right now.
                            You can still try, but the panel will refuse it. Go back to Choose A Size and ask for less.
                        </x-alert>
                    </div>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <x-card title="What It Runs" icon="play">
                            <x-slot:actions>
                                <x-button type="button" variant="ghost" size="sm" @click="go(1)">Change</x-button>
                            </x-slot:actions>
                            <dl class="divide-y divide-slate-100 text-sm">
                                <div class="flex items-baseline justify-between gap-4 py-2 first:pt-0">
                                    <dt class="shrink-0 text-slate-500">Game</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right text-slate-900" x-text="sum.game || 'None'"></dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-2">
                                    <dt class="shrink-0 text-slate-500">Template</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right text-slate-900" x-text="sum.template"></dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-2">
                                    <dt class="shrink-0 text-slate-500">Runtime</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right text-slate-900" x-text="sum.runtime"></dd>
                                </div>
                                {{-- A docker tag and a startup line are too long
                                     to sit beside their own label at any width,
                                     so they get the full row. --}}
                                <div class="py-2">
                                    <dt class="text-slate-500">Image</dt>
                                    <dd class="mt-1 font-mono text-xs [overflow-wrap:anywhere] text-slate-900" x-text="sum.image"></dd>
                                </div>
                                <div class="py-2 last:pb-0">
                                    <dt class="text-slate-500">Startup</dt>
                                    <dd class="mt-1 font-mono text-xs [overflow-wrap:anywhere] text-slate-900" x-text="sum.startup"></dd>
                                </div>
                            </dl>
                        </x-card>

                        <x-card title="Where It Runs" icon="cpu">
                            <x-slot:actions>
                                <x-button type="button" variant="ghost" size="sm" @click="go(2)">Change</x-button>
                            </x-slot:actions>
                            <dl class="divide-y divide-slate-100 text-sm">
                                <div class="flex items-baseline justify-between gap-4 py-2 first:pt-0">
                                    <dt class="shrink-0 text-slate-500">Placement</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right text-slate-900" x-text="sum.placement"></dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-2">
                                    <dt class="shrink-0 text-slate-500">Machine</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right text-slate-900" x-text="sum.node"></dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-2">
                                    <dt class="shrink-0 text-slate-500">Location</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right text-slate-900" x-text="sum.nodeLocation"></dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-2 last:pb-0">
                                    <dt class="shrink-0 text-slate-500">Port</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right tabular text-slate-900" x-text="sum.port"></dd>
                                </div>
                            </dl>
                        </x-card>

                        <x-card title="Name And Owner" icon="users">
                            <x-slot:actions>
                                <x-button type="button" variant="ghost" size="sm" @click="go(3)">Change</x-button>
                            </x-slot:actions>
                            <dl class="divide-y divide-slate-100 text-sm">
                                <div class="flex items-baseline justify-between gap-4 py-2 first:pt-0">
                                    <dt class="shrink-0 text-slate-500">Name</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right text-slate-900" x-text="sum.name"></dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-2">
                                    <dt class="shrink-0 text-slate-500">Description</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right text-slate-900" x-text="sum.description"></dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-2 last:pb-0">
                                    <dt class="shrink-0 text-slate-500">Owner</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right text-slate-900">
                                        <span x-text="sum.owner"></span>
                                        <span class="block text-xs text-slate-500" x-text="sum.ownerEmail"></span>
                                    </dd>
                                </div>
                            </dl>
                        </x-card>

                        <x-card title="Size And Caps" icon="memory">
                            <x-slot:actions>
                                <x-button type="button" variant="ghost" size="sm" @click="go(4)">Change</x-button>
                            </x-slot:actions>
                            <dl class="divide-y divide-slate-100 text-sm">
                                <div class="flex items-baseline justify-between gap-4 py-2 first:pt-0">
                                    <dt class="shrink-0 text-slate-500">Memory</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right tabular text-slate-900" x-text="sum.memory"></dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-2">
                                    <dt class="shrink-0 text-slate-500">Disk</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right tabular text-slate-900" x-text="sum.disk"></dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-2">
                                    <dt class="shrink-0 text-slate-500">CPU</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right tabular text-slate-900" x-text="sum.cpu"></dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-2">
                                    <dt class="shrink-0 text-slate-500">Swap And Block IO</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right tabular text-slate-900">
                                        <span x-text="sum.swap"></span> &middot; <span x-text="sum.io"></span>
                                    </dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-2">
                                    <dt class="shrink-0 text-slate-500">Databases, Ports, Backups</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right tabular text-slate-900">
                                        <span x-text="sum.databases"></span> &middot;
                                        <span x-text="sum.ports"></span> &middot;
                                        <span x-text="sum.backups"></span>
                                    </dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-4 py-2 last:pb-0">
                                    <dt class="shrink-0 text-slate-500">Restart, Auto Update</dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right text-slate-900">
                                        <span x-text="sum.autoRestart"></span> &middot; <span x-text="sum.autoUpdate"></span>
                                    </dd>
                                </div>
                            </dl>
                        </x-card>
                    </div>

                    <x-card title="Game Settings">
                        <x-slot:actions>
                            <x-button type="button" variant="ghost" size="sm" @click="go(5)">Change</x-button>
                        </x-slot:actions>
                        <div x-show="!sum.variables || sum.variables.length === 0" x-cloak>
                            <p class="text-sm text-slate-500">This template exposes no settings.</p>
                        </div>
                        <dl class="grid grid-cols-1 gap-x-6 sm:grid-cols-2" x-show="sum.variables && sum.variables.length > 0">
                            <template x-for="v in sum.variables" :key="v.env">
                                <div class="flex items-baseline justify-between gap-4 border-b border-slate-100 py-2 text-sm">
                                    <dt class="min-w-0 shrink text-slate-500">
                                        <span x-text="v.name"></span>
                                        <span class="block font-mono text-xs text-slate-400" x-text="v.env"></span>
                                    </dt>
                                    <dd class="min-w-0 [overflow-wrap:anywhere] text-right text-slate-900" x-text="v.value"></dd>
                                </div>
                            </template>
                        </dl>
                    </x-card>
                </div>

                {{-- ---------------------------------------------------- footer --}}
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <x-button type="button" variant="secondary" size="sm" x-show="step > 1" x-cloak
                                  icon="chevron-left" @click="back()">Back</x-button>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-button href="{{ route('admin.servers.index') }}" variant="ghost" size="sm">Cancel</x-button>
                        <x-button type="button" size="sm" x-show="step < total" @click="next()">Next</x-button>
                        <x-button type="submit" icon="check" x-show="step === total" x-cloak>Create Server</x-button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- Each game's own colour, straight from the games table, as a custom
         property the art tiles read. A stylesheet rather than a style attribute
         on every tile: one rule per game instead of one per card. --}}
    <style>
        .gm-art {
            --gm-art: {{ config('brand.accent', '#6d28d9') }};
            background-image: linear-gradient(135deg,
                color-mix(in srgb, var(--gm-art) 88%, white),
                color-mix(in srgb, var(--gm-art) 60%, black));
            box-shadow: 0 6px 14px -8px color-mix(in srgb, var(--gm-art) 70%, transparent);
        }
        /* The same colour, laid flat behind the review header. */
        .gm-wash {
            --gm-art: {{ config('brand.accent', '#6d28d9') }};
            background-image: linear-gradient(100deg,
                color-mix(in srgb, var(--gm-art) 14%, white),
                color-mix(in srgb, var(--gm-art) 4%, white) 55%,
                #fff);
        }
        /* A card whose radio is hidden still has to show keyboard focus, and an
           outline cannot fight the selected state's ring the way another ring
           would. */
        .gm-pick:has(:focus-visible) {
            outline: 2px solid {{ config('brand.accent', '#6d28d9') }};
            outline-offset: 2px;
        }
        /* Range thumbs, sized for a finger rather than a mouse. */
        input[type='range'].accent-brand-600::-webkit-slider-thumb {
            appearance: none;
            width: 1.125rem;
            height: 1.125rem;
            border-radius: 9999px;
            background: var(--color-brand-600);
            border: 2px solid #fff;
            box-shadow: 0 1px 3px rgba(2, 6, 23, .3);
            cursor: pointer;
        }
        input[type='range'].accent-brand-600::-moz-range-thumb {
            width: 1.125rem;
            height: 1.125rem;
            border-radius: 9999px;
            background: var(--color-brand-600);
            border: 2px solid #fff;
            box-shadow: 0 1px 3px rgba(2, 6, 23, .3);
            cursor: pointer;
        }
        @foreach ($games as $game)
            @php $colour = preg_replace('/[^#0-9a-fA-F]/', '', (string) $game->cover_color); @endphp
            @if ($colour !== '')
                .gm-art-{{ $game->id }} { --gm-art: {{ $colour }}; }
            @endif
        @endforeach
    </style>

    {{-- Nodes, allocations, templates, blueprints and locations for the browser.
         A JSON island rather than a giant x-data attribute: the escaping is
         simple and the markup above stays readable. --}}
    <script type="application/json" id="server-wizard-data">@json($wizard)</script>
</x-layouts.app>
