{{-- Designing a blueprint is a product decision, not data entry. "Palworld
     Test, 8 GiB, enough to prove the install and connect a few players" is the
     sentence; eight bare number boxes are only its residue.

     So this page does three things the old form did not:
       1. every raw MiB is read back as GiB, and every CPU percent as cores,
       2. the card an operator will later pick is drawn live, beside the inputs,
       3. the draft is ranked against the other sizes for the same template,
          because "is this the small one or the big one" is the actual question.

     Field names, old() defaults, error bindings and the method spoof are
     untouched: the controller sees exactly the payload it always did. The
     behaviour lives in blueprintDesigner in public/js/gamemgr.js. --}}
<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="copy"
                   subtitle="A blueprint is a size with a name. Operators pick it by that name, so make the name and the numbers agree.">
        <x-slot:actions>
            <x-button href="{{ route('admin.blueprints.index') }}" variant="secondary" size="sm" icon="chevron-left">All Blueprints</x-button>
        </x-slot:actions>
    </x-page-header>

    <div x-data="blueprintDesigner('blueprint-designer-data')">

        {{-- Narrow screens cannot hold the preview card and the inputs at once,
             so they get this instead: a slim strip that stays under the navbar
             and keeps the three numbers in view while you edit them. --}}
        <div class="lg:hidden sticky top-14 z-20 -mx-4 sm:-mx-6 mb-6 border-y border-slate-200 bg-white/95 px-4 sm:px-6 py-2 backdrop-blur">
            <div class="flex items-center gap-3">
                <span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900"
                      x-text="name || 'Untitled Blueprint'">Untitled Blueprint</span>
                <span class="shrink-0 text-xs tabular text-slate-500">
                    <span x-text="size(res.memory)">2 GiB</span> &middot;
                    <span x-text="size(res.disk)">10 GiB</span> &middot;
                    <span x-text="cores(res.cpu)">2 Cores</span>
                </span>
            </div>
        </div>

        @if ($errors->any())
            <div class="mb-6">
                <x-alert type="danger" title="Nothing Was Saved">
                    <ul class="mt-1 space-y-0.5 list-disc ps-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            </div>
        @endif

        {{-- No max-w here. The layout already sets the page width from
             config('gamemgr.max_width'), and a second cap inside it renders a
             narrow column stranded in the middle of a wide screen. --}}
        {{-- invalid does not bubble, so the listener has to capture. It fires on
             every field the browser rejects, before it reports the first one,
             which is the only moment a hidden pane can still be opened in time. --}}
        <form method="POST" action="{{ $blueprint->exists ? route('admin.blueprints.update', $blueprint) : route('admin.blueprints.store') }}"
              @invalid.capture="openPaneFor($event.target)">
            @csrf
            @if ($blueprint->exists)@method('PUT')@endif

            <div class="grid gap-6 lg:grid-cols-3 items-start">

                {{-- ------------------------------------------- the decisions --}}
                {{-- min-w-0 on both columns: a grid item will not shrink below
                     its min-content without it, so one long chip row pushed the
                     whole page sideways at 320px. --}}
                <div class="min-w-0 lg:col-span-2 space-y-6">

                    <x-card title="What This Preset Is"
                            subtitle="The name is what an operator reads at three in the morning. Make it say the job, not the numbers.">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Name" for="bp-name" required :error="$errors->first('name')">
                                <x-input id="bp-name" name="name" value="{{ old('name', $blueprint->name) }}" required
                                         maxlength="120" x-model="name" placeholder="Palworld Test" autocomplete="off" />
                            </x-field>

                            <x-field label="Template" for="bp-template" required :error="$errors->first('template_id')"
                                     hint="The game, and how it is installed and supervised.">
                                <x-select id="bp-template" name="template_id" required x-model="templateId">
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @selected(old('template_id', $blueprint->template_id) == $template->id)>
                                            {{ $template->game?->name }} : {{ $template->name }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-field>

                            <x-field class="sm:col-span-2" label="Description" for="bp-description"
                                     :error="$errors->first('description')"
                                     hint="One line of judgement: who it is for, and where it stops being enough.">
                                <x-input id="bp-description" name="description" maxlength="255"
                                         value="{{ old('description', $blueprint->description) }}" x-model="description"
                                         placeholder="Enough to prove the install and connect a few players" />
                            </x-field>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @foreach (['docker', 'steamcmd', 'linuxgsm'] as $runtime)
                                <span x-show="template && template.runtime === '{{ $runtime }}'" x-cloak>
                                    <x-runtime-badge runtime="{{ $runtime }}" />
                                </span>
                            @endforeach
                            <x-badge>
                                <x-icon name="copy" class="w-3.5 h-3.5" />
                                <span x-text="siblingLabel">No Other Sizes</span>
                            </x-badge>
                        </div>

                        <div class="mt-4" x-show="nameTaken" x-cloak>
                            <x-alert type="warn" title="That Name Is Taken">
                                Another blueprint already answers to this name. Operators pick by name, so two of them is one too many.
                            </x-alert>
                        </div>
                    </x-card>

                    {{-- Two genuinely different questions, so two panes: what the
                         server gets, and what its owner may go on to create.
                         Sizing is not a permissions decision and reads worse when
                         the two are stacked in one column.

                         A pane is display:none, and a hidden required input is
                         not focusable, which makes the browser refuse to submit
                         in silence. Two things stop that: openPaneFor() opens the
                         pane holding the first field the browser rejects, and the
                         controller reopens the pane holding the first field the
                         SERVER rejected when a POST comes back. --}}
                    <x-tab-set label="Blueprint Sections" :active="$activeTab" :tabs="[
                        ['id' => 'resources', 'label' => 'Resources', 'icon' => 'cpu'],
                        ['id' => 'caps', 'label' => 'Feature Caps', 'icon' => 'users'],
                    ]">

                    <x-tab-pane id="resources">
                    {{-- --------------------------------------------- the size --}}
                    <x-card title="The Size"
                            subtitle="Stored in MiB and CPU percent, because that is what a cgroup wants. Read back in GiB and cores, because that is what a person wants.">
                        <div class="space-y-4">

                            {{-- memory --}}
                            <div class="rounded-xl ring-1 ring-inset ring-slate-200 p-4">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <label for="bp-memory" class="flex items-center gap-2 text-sm font-medium text-slate-700">
                                        <x-icon name="memory" class="w-4 h-4 text-slate-400" /> Memory
                                        <span class="text-rose-500">*</span>
                                    </label>
                                    <span class="text-lg font-semibold tabular text-slate-900" x-text="size(res.memory)">2 GiB</span>
                                </div>

                                <input type="range" min="0" :max="memStops.length - 1" step="1"
                                       :value="stopIndex(memStops, res.memory)"
                                       @input="res.memory = memStops[$event.target.value]"
                                       aria-label="Memory Slider"
                                       class="mt-3 w-full cursor-pointer accent-brand-600">

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    {{-- The width lives on the wrapper, never on the input:
                                         x-input ships w-full and utility order, not
                                         attribute order, decides which one wins. --}}
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-28">
                                            <x-input id="bp-memory" type="number" name="memory" min="0" required
                                                     value="{{ old('memory', $limits['memory'] ?? 2048) }}"
                                                     x-model.number="res.memory" @change="res.memory = clamp(res.memory, 0, 1048576)"
                                                     class="tabular" />
                                        </div>
                                        <span class="text-xs text-slate-500">MiB</span>
                                    </div>
                                    <span class="h-5 w-px bg-slate-200"></span>
                                    <template x-for="p in memPresets" :key="p">
                                        <button type="button" @click="res.memory = p"
                                                :class="Number(res.memory) === p ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-slate-600 ring-slate-300 hover:ring-slate-500'"
                                                class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition"
                                                x-text="size(p)"></button>
                                    </template>
                                </div>

                                <p class="mt-2 text-xs text-slate-500" x-show="Number(res.memory) === 0" x-cloak>
                                    Zero means no memory cap at all. The server takes what it likes from the node.
                                </p>
                            </div>

                            {{-- disk --}}
                            <div class="rounded-xl ring-1 ring-inset ring-slate-200 p-4">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <label for="bp-disk" class="flex items-center gap-2 text-sm font-medium text-slate-700">
                                        <x-icon name="database" class="w-4 h-4 text-slate-400" /> Disk
                                        <span class="text-rose-500">*</span>
                                    </label>
                                    <span class="text-lg font-semibold tabular text-slate-900" x-text="size(res.disk)">10 GiB</span>
                                </div>

                                <input type="range" min="0" :max="diskStops.length - 1" step="1"
                                       :value="stopIndex(diskStops, res.disk)"
                                       @input="res.disk = diskStops[$event.target.value]"
                                       aria-label="Disk Slider"
                                       class="mt-3 w-full cursor-pointer accent-brand-600">

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-28">
                                            <x-input id="bp-disk" type="number" name="disk" min="0" required
                                                     value="{{ old('disk', $limits['disk'] ?? 10240) }}"
                                                     x-model.number="res.disk" @change="res.disk = clamp(res.disk, 0, 10485760)"
                                                     class="tabular" />
                                        </div>
                                        <span class="text-xs text-slate-500">MiB</span>
                                    </div>
                                    <span class="h-5 w-px bg-slate-200"></span>
                                    <template x-for="p in diskPresets" :key="p">
                                        <button type="button" @click="res.disk = p"
                                                :class="Number(res.disk) === p ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-slate-600 ring-slate-300 hover:ring-slate-500'"
                                                class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition"
                                                x-text="size(p)"></button>
                                    </template>
                                </div>

                                <p class="mt-2 text-xs text-slate-500">
                                    Game files, worlds, and every backup the owner has not yet deleted.
                                </p>
                            </div>

                            {{-- cpu --}}
                            <div class="rounded-xl ring-1 ring-inset ring-slate-200 p-4">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <label for="bp-cpu" class="flex items-center gap-2 text-sm font-medium text-slate-700">
                                        <x-icon name="cpu" class="w-4 h-4 text-slate-400" /> CPU
                                        <span class="text-rose-500">*</span>
                                    </label>
                                    <span class="text-lg font-semibold tabular text-slate-900" x-text="cores(res.cpu)">2 Cores</span>
                                </div>

                                <input type="range" min="0" :max="cpuStops.length - 1" step="1"
                                       :value="stopIndex(cpuStops, res.cpu)"
                                       @input="res.cpu = cpuStops[$event.target.value]"
                                       aria-label="CPU Slider"
                                       class="mt-3 w-full cursor-pointer accent-brand-600">

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-28">
                                            <x-input id="bp-cpu" type="number" name="cpu" min="0" required
                                                     value="{{ old('cpu', $limits['cpu'] ?? 200) }}"
                                                     x-model.number="res.cpu" @change="res.cpu = clamp(res.cpu, 0, 6400)"
                                                     class="tabular" />
                                        </div>
                                        <span class="text-xs text-slate-500">percent</span>
                                    </div>
                                    <span class="h-5 w-px bg-slate-200"></span>
                                    <template x-for="p in cpuPresets" :key="p">
                                        <button type="button" @click="res.cpu = p"
                                                :class="Number(res.cpu) === p ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-slate-600 ring-slate-300 hover:ring-slate-500'"
                                                class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition"
                                                x-text="cores(p)"></button>
                                    </template>
                                </div>

                                <p class="mt-2 text-xs text-slate-500">
                                    100 percent is one full core. Most game servers run their world on a single thread, so four cores
                                    buys headroom for everything around it rather than a faster tick.
                                </p>
                            </div>
                        </div>

                        <x-slot:footer>
                            <div class="space-y-4">
                                <button type="button" @click="showFineTuning = !showFineTuning"
                                        :aria-expanded="showFineTuning.toString()"
                                        class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 transition hover:text-slate-900">
                                    <x-icon name="chevron-down" class="w-4 h-4 transition-transform" ::class="showFineTuning && 'rotate-180'" />
                                    <span x-text="showFineTuning ? 'Hide Swap And Disk Priority' : 'Show Swap And Disk Priority'">Show Swap And Disk Priority</span>
                                </button>

                                <div id="bp-fine-tuning" x-show="showFineTuning" x-cloak class="space-y-5"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 -translate-y-1"
                                     x-transition:enter-end="opacity-100 translate-y-0">

                                    {{-- swap: two of its three useful values are magic numbers,
                                         which is exactly what a bare number box cannot teach. --}}
                                    <div>
                                        <p class="text-sm font-medium text-slate-700">Swap</p>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-3" role="radiogroup" aria-label="Swap">
                                            <button type="button" role="radio" @click="setSwap('off')"
                                                    :aria-checked="(swapMode === 'off').toString()"
                                                    :class="swapMode === 'off' ? 'ring-2 ring-brand-500 bg-brand-50' : 'ring-1 ring-slate-200 bg-white hover:ring-slate-400'"
                                                    class="rounded-lg p-3 text-left ring-inset transition">
                                                <span class="flex items-center gap-2">
                                                    <span class="inline-flex w-4 h-4 shrink-0 items-center justify-center rounded-full ring-2 ring-inset"
                                                          :class="swapMode === 'off' ? 'ring-brand-600' : 'ring-slate-300'">
                                                        <span class="w-2 h-2 rounded-full bg-brand-600" x-show="swapMode === 'off'"></span>
                                                    </span>
                                                    <span class="text-sm font-medium text-slate-900">Off</span>
                                                </span>
                                                <span class="mt-1 block text-xs text-slate-500">Stores 0. A server at its memory limit is stopped rather than slowed.</span>
                                            </button>

                                            <button type="button" role="radio" @click="setSwap('unlimited')"
                                                    :aria-checked="(swapMode === 'unlimited').toString()"
                                                    :class="swapMode === 'unlimited' ? 'ring-2 ring-brand-500 bg-brand-50' : 'ring-1 ring-slate-200 bg-white hover:ring-slate-400'"
                                                    class="rounded-lg p-3 text-left ring-inset transition">
                                                <span class="flex items-center gap-2">
                                                    <span class="inline-flex w-4 h-4 shrink-0 items-center justify-center rounded-full ring-2 ring-inset"
                                                          :class="swapMode === 'unlimited' ? 'ring-brand-600' : 'ring-slate-300'">
                                                        <span class="w-2 h-2 rounded-full bg-brand-600" x-show="swapMode === 'unlimited'"></span>
                                                    </span>
                                                    <span class="text-sm font-medium text-slate-900">Unlimited</span>
                                                </span>
                                                <span class="mt-1 block text-xs text-slate-500">Stores -1. It swaps to disk instead of dying, and every player feels it.</span>
                                            </button>

                                            <button type="button" role="radio" @click="setSwap('custom')"
                                                    :aria-checked="(swapMode === 'custom').toString()"
                                                    :class="swapMode === 'custom' ? 'ring-2 ring-brand-500 bg-brand-50' : 'ring-1 ring-slate-200 bg-white hover:ring-slate-400'"
                                                    class="rounded-lg p-3 text-left ring-inset transition">
                                                <span class="flex items-center gap-2">
                                                    <span class="inline-flex w-4 h-4 shrink-0 items-center justify-center rounded-full ring-2 ring-inset"
                                                          :class="swapMode === 'custom' ? 'ring-brand-600' : 'ring-slate-300'">
                                                        <span class="w-2 h-2 rounded-full bg-brand-600" x-show="swapMode === 'custom'"></span>
                                                    </span>
                                                    <span class="text-sm font-medium text-slate-900">A Fixed Amount</span>
                                                </span>
                                                <span class="mt-1 block text-xs text-slate-500">A cushion in MiB for the moments a world save spikes.</span>
                                            </button>
                                        </div>

                                        {{-- The input posts in every mode, hidden or not, so swap
                                             is always the number the cards above claim it is. --}}
                                        <div class="mt-3 flex items-center gap-1.5" x-show="swapMode === 'custom'" x-cloak>
                                            <div class="w-28">
                                                <x-input id="bp-swap" type="number" name="swap" min="-1" required
                                                         value="{{ old('swap', $limits['swap'] ?? 0) }}"
                                                         x-model.number="res.swap" @change="res.swap = clamp(res.swap, -1, 1048576)"
                                                         class="tabular" aria-label="Swap In MiB" />
                                            </div>
                                            <span class="text-xs text-slate-500">MiB</span>
                                        </div>
                                        @error('swap')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    {{-- io --}}
                                    <div class="section-divider pt-5">
                                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                            <label for="bp-io" class="text-sm font-medium text-slate-700">
                                                Disk Priority <span class="text-rose-500">*</span>
                                            </label>
                                            <span class="text-sm font-semibold tabular text-slate-900" x-text="res.io">500</span>
                                        </div>

                                        <input type="range" min="10" max="1000" step="10"
                                               :value="res.io" @input="res.io = Number($event.target.value)"
                                               aria-label="Disk Priority Slider"
                                               class="mt-3 w-full cursor-pointer accent-brand-600">

                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <div class="flex items-center gap-1.5">
                                                <div class="w-28">
                                                    <x-input id="bp-io" type="number" name="io" min="10" max="1000" required
                                                             value="{{ old('io', $limits['io'] ?? 500) }}"
                                                             x-model.number="res.io" @change="res.io = clamp(res.io, 10, 1000)"
                                                             class="tabular" />
                                                </div>
                                                <span class="text-xs text-slate-500">weight</span>
                                            </div>
                                            <span class="h-5 w-px bg-slate-200"></span>
                                            <template x-for="p in ioPresets" :key="p.value">
                                                <button type="button" @click="res.io = p.value"
                                                        :class="Number(res.io) === p.value ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-slate-600 ring-slate-300 hover:ring-slate-500'"
                                                        class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition"
                                                        x-text="p.label"></button>
                                            </template>
                                        </div>

                                        <p class="mt-2 text-xs text-slate-500" x-text="ioNote">
                                            The normal share. Every server on the node competes for disk time equally.
                                        </p>
                                        @error('io')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>
                        </x-slot:footer>
                    </x-card>
                    </x-tab-pane>

                    {{-- ------------------------------------- what the owner may do --}}
                    <x-tab-pane id="caps">
                    <x-card title="What The Owner May Create"
                            subtitle="Caps the client can spend for themselves, without asking you. Zero means they cannot.">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <x-field label="Databases" for="bp-databases" required :error="$errors->first('databases')"
                                     hint="MySQL databases on a database host.">
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="bump('databases', -1, 0, 50)" aria-label="Fewer Databases"
                                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-600 ring-1 ring-inset ring-slate-300 transition hover:ring-slate-500 focus-visible:ring-2 focus-visible:ring-brand-500">
                                        <span class="h-0.5 w-3.5 rounded-full bg-current"></span>
                                    </button>
                                    <x-input id="bp-databases" type="number" name="databases" min="0" max="50" required
                                             value="{{ old('databases', $features['databases'] ?? 1) }}"
                                             x-model.number="res.databases" @change="res.databases = clamp(res.databases, 0, 50)"
                                             class="w-full text-center tabular" />
                                    <button type="button" @click="bump('databases', 1, 0, 50)" aria-label="More Databases"
                                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-600 ring-1 ring-inset ring-slate-300 transition hover:ring-slate-500 focus-visible:ring-2 focus-visible:ring-brand-500">
                                        <x-icon name="plus" class="w-4 h-4" />
                                    </button>
                                </div>
                            </x-field>

                            <x-field label="Extra Ports" for="bp-allocations" required :error="$errors->first('allocations')"
                                     hint="Allocations beyond the one it starts on.">
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="bump('allocations', -1, 0, 50)" aria-label="Fewer Extra Ports"
                                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-600 ring-1 ring-inset ring-slate-300 transition hover:ring-slate-500 focus-visible:ring-2 focus-visible:ring-brand-500">
                                        <span class="h-0.5 w-3.5 rounded-full bg-current"></span>
                                    </button>
                                    <x-input id="bp-allocations" type="number" name="allocations" min="0" max="50" required
                                             value="{{ old('allocations', $features['allocations'] ?? 2) }}"
                                             x-model.number="res.allocations" @change="res.allocations = clamp(res.allocations, 0, 50)"
                                             class="w-full text-center tabular" />
                                    <button type="button" @click="bump('allocations', 1, 0, 50)" aria-label="More Extra Ports"
                                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-600 ring-1 ring-inset ring-slate-300 transition hover:ring-slate-500 focus-visible:ring-2 focus-visible:ring-brand-500">
                                        <x-icon name="plus" class="w-4 h-4" />
                                    </button>
                                </div>
                            </x-field>

                            <x-field label="Backups" for="bp-backups" required :error="$errors->first('backups')"
                                     hint="Locked backups sit outside this cap.">
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="bump('backups', -1, 0, 200)" aria-label="Fewer Backups"
                                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-600 ring-1 ring-inset ring-slate-300 transition hover:ring-slate-500 focus-visible:ring-2 focus-visible:ring-brand-500">
                                        <span class="h-0.5 w-3.5 rounded-full bg-current"></span>
                                    </button>
                                    <x-input id="bp-backups" type="number" name="backups" min="0" max="200" required
                                             value="{{ old('backups', $features['backups'] ?? 5) }}"
                                             x-model.number="res.backups" @change="res.backups = clamp(res.backups, 0, 200)"
                                             class="w-full text-center tabular" />
                                    <button type="button" @click="bump('backups', 1, 0, 200)" aria-label="More Backups"
                                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-slate-600 ring-1 ring-inset ring-slate-300 transition hover:ring-slate-500 focus-visible:ring-2 focus-visible:ring-brand-500">
                                        <x-icon name="plus" class="w-4 h-4" />
                                    </button>
                                </div>
                            </x-field>
                        </div>

                        <p class="mt-4 text-xs text-slate-500">
                            Backups are written into the disk allowance set on the Resources tab, so a generous backup count
                            on a tight disk runs out of room rather than out of permission.
                        </p>
                    </x-card>
                    </x-tab-pane>

                    </x-tab-set>
                </div>

                {{-- ---------------------------------------------- the artefact --}}
                <div class="min-w-0 space-y-6 lg:sticky lg:top-20">

                    <x-card title="The Card Operators Pick"
                            subtitle="Drawn live from the values you set.">
                        {{-- Deliberately drawn in its selected state: this is the
                             moment the blueprint is actually used, so it should
                             be designed looking the way it will be seen. --}}
                        <div class="rounded-xl bg-white p-4 shadow-sm ring-2 ring-inset ring-brand-500 transition">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900" x-text="name || 'Untitled Blueprint'">Untitled Blueprint</p>
                                    <p class="truncate text-xs text-slate-500" x-text="templateLabel">No Template</p>
                                </div>
                                <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-600 text-white">
                                    <x-icon name="check" class="w-3 h-3" />
                                </span>
                            </div>

                            <p class="mt-2 text-sm"
                               :class="description ? 'text-slate-600' : 'text-slate-400 italic'"
                               x-text="description || 'No description yet.'">No description yet.</p>

                            {{-- Same three chips, in the same order, as the size
                                 cards on the server create wizard. The point of
                                 this panel is that it is not an approximation. --}}
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-200">
                                    <x-icon name="memory" class="w-3.5 h-3.5 text-slate-400" />
                                    <span class="tabular" x-text="size(res.memory)">2 GiB</span>
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-200">
                                    <x-icon name="database" class="w-3.5 h-3.5 text-slate-400" />
                                    <span class="tabular" x-text="size(res.disk)">10 GiB</span>
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-200">
                                    <x-icon name="cpu" class="w-3.5 h-3.5 text-slate-400" />
                                    <span class="tabular" x-text="cores(res.cpu)">2 Cores</span>
                                </span>
                            </div>

                            {{-- Caps as a sentence, not more chips: three sizes and
                                 three caps in one badge pile reads as six numbers
                                 of equal weight, and they are not. --}}
                            <div class="mt-3 border-t border-slate-100 pt-3 space-y-1 text-xs text-slate-500">
                                <p>
                                    Owner may create
                                    <span class="tabular" x-text="plural(res.databases, 'Database', 'Databases')">1 Database</span>,
                                    <span class="tabular" x-text="plural(res.allocations, 'Extra Port', 'Extra Ports')">2 Extra Ports</span>,
                                    <span class="tabular" x-text="plural(res.backups, 'Backup', 'Backups')">5 Backups</span>.
                                </p>
                                <p x-show="Number(res.swap) !== 0 || Number(res.io) !== 500" x-cloak>
                                    Swap <span x-text="swapText">Off</span>, disk priority <span class="tabular" x-text="res.io">500</span>.
                                </p>
                            </div>
                        </div>

                        <p class="mt-3 text-xs text-slate-500">
                            This is what appears in the first step of New Server, and what the operator chooses between.
                        </p>
                    </x-card>

                    {{-- The question a sizing screen has to answer is not "what
                         number", it is "which of ours is this one". --}}
                    <x-card title="Where It Sits" subtitle="Every blueprint on this template, by memory.">
                        <div class="space-y-3">
                            <template x-for="row in ladder" :key="row.key">
                                <div class="min-w-0">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="min-w-0 truncate text-xs font-medium"
                                              :class="row.draft ? 'text-brand-700' : 'text-slate-600'"
                                              x-text="row.name">Name</span>
                                        <span class="shrink-0 text-xs tabular"
                                              :class="row.draft ? 'text-brand-700 font-semibold' : 'text-slate-400'"
                                              x-text="row.label">2 GiB</span>
                                    </div>
                                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full transition-all"
                                             :class="row.draft ? 'bg-brand-500' : 'bg-slate-400'"
                                             :style="'width: ' + row.pct + '%'"></div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <p class="mt-4 text-xs text-slate-500" x-show="ladder.length === 1" x-cloak>
                            The first size for this template. The next one you write will be measured against it.
                        </p>

                        <div class="mt-4" x-show="twin" x-cloak>
                            <x-alert type="warn" title="Already Have This Size">
                                <span x-text="twin ? twin.name : ''"></span> is the same memory, disk and CPU.
                                Two identical sizes with different names is a choice nobody can make.
                            </x-alert>
                        </div>
                    </x-card>

                    @if ($blueprint->exists)
                        <x-alert type="info" title="Servers Already Built">
                            Changing a blueprint changes the next server made from it. Servers already created keep the size
                            they were given, so nothing running is resized by saving this.
                        </x-alert>
                    @endif
                </div>
            </div>

            {{-- ------------------------------------------------------ actions --}}
            <x-card class="mt-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-500">
                        {{ $blueprint->exists
                            ? 'Saving replaces the stored limits. Nothing is applied to a running server.'
                            : 'Nothing is created on any node. A blueprint is only a size waiting to be picked.' }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-button href="{{ route('admin.blueprints.index') }}" variant="secondary">Cancel</x-button>
                        <x-button type="submit" icon="check">{{ $blueprint->exists ? 'Save Blueprint' : 'Create Blueprint' }}</x-button>
                    </div>
                </div>
            </x-card>
        </form>
    </div>

    <script type="application/json" id="blueprint-designer-data">@json($designer)</script>
</x-layouts.app>
