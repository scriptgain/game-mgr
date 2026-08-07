{{-- The stepped create screen. One form, one POST, six short steps.

     Every field stays in the DOM the whole time and keeps the name the store
     action already expects, so the controller sees exactly the payload the old
     single page form sent. All the stepping lives in the serverWizard Alpine
     component in public/js/gamemgr.js.

     Editing an existing server is a different job and keeps the plain
     admin.servers.form view: nobody should walk six steps to change one memory
     limit. --}}
<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="server"
                   subtitle="Six short steps. Nothing is created until you press Create Server on the last one." />

    <div x-data="serverWizard('server-wizard-data')">

        {{-- Progress. Six labelled pills do not fit a phone, so narrow screens
             get a counter and a bar instead. Nothing here scrolls sideways. --}}
        <x-card class="mb-6">
            <div class="sm:hidden">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-900" x-text="steps[step - 1].label">Step</p>
                    <p class="text-xs tabular text-slate-500 shrink-0">
                        Step <span x-text="step">1</span> Of <span x-text="steps.length">6</span>
                    </p>
                </div>
                <div class="mt-2 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full bg-brand-500 transition-all"
                         :style="'width: ' + Math.round(step / steps.length * 100) + '%'"></div>
                </div>
            </div>

            <ol class="hidden sm:flex flex-wrap items-center gap-2">
                <template x-for="s in steps" :key="s.n">
                    <li class="min-w-0">
                        <button type="button" @click="go(s.n)"
                                :aria-current="s.n === step ? 'step' : false"
                                class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm font-medium ring-1 ring-inset transition"
                                :class="s.n === step
                                    ? 'bg-brand-50 text-brand-700 ring-brand-300'
                                    : (s.n < furthest
                                        ? 'bg-white text-slate-700 ring-slate-200 hover:ring-slate-400'
                                        : 'bg-white text-slate-400 ring-slate-200 hover:ring-slate-400')">
                            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[11px] font-semibold tabular shrink-0"
                                  :class="s.n === step
                                      ? 'bg-brand-600 text-white'
                                      : (s.n < furthest ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500')">
                                <svg x-show="s.n < furthest && s.n !== step" x-cloak class="w-3 h-3" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span x-show="!(s.n < furthest && s.n !== step)" x-text="s.n">1</span>
                            </span>
                            <span x-text="s.label">Step</span>
                        </button>
                    </li>
                </template>
            </ol>
        </x-card>

        <form method="POST" action="{{ route('admin.servers.store') }}" x-ref="form"
              @submit="onSubmit($event)" @keydown.enter="onEnter($event)">
            @csrf

            {{-- ---------------------------------------------------- step one --}}
            <div data-step="1" x-show="step === 1" x-cloak class="space-y-6">
                <x-card title="What To Run"
                        subtitle="Pick a blueprint to preset everything, or go straight to a template.">
                    <div class="space-y-4">
                        @if ($blueprints->isNotEmpty())
                            <x-field label="Start From A Blueprint"
                                     hint="Optional. Fills in the template and every limit for you, and you can still change them.">
                                {{-- No name attribute on purpose: this only
                                     drives the fields below, it is never posted. --}}
                                <x-select x-model="blueprintId" @change="applyBlueprint()">
                                    <option value="">Start From Scratch</option>
                                    @foreach ($blueprints as $blueprint)
                                        <option value="{{ $blueprint->id }}">
                                            {{ $blueprint->name }} ({{ $blueprint->summary() }})
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-field>
                        @endif

                        <x-field label="Template" required :error="$errors->first('template_id')">
                            <x-select name="template_id" x-model="templateId" required>
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}" @selected(old('template_id', $templates->first()?->id) == $template->id)>
                                        {{ $template->game?->name }} : {{ $template->name }}
                                    </option>
                                @endforeach
                            </x-select>
                        </x-field>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge color="info">
                                <span x-text="template ? template.runtime_label : 'No Template'">Runtime</span>
                            </x-badge>
                            <x-badge>
                                <span x-text="variables.length + (variables.length === 1 ? ' Variable' : ' Variables')">Variables</span>
                            </x-badge>
                            <x-badge>
                                <span x-text="nodeChoices.length + (nodeChoices.length === 1 ? ' Node Can Run It' : ' Nodes Can Run It')">Nodes</span>
                            </x-badge>
                        </div>

                        <p class="text-sm text-slate-500" x-show="template && template.description" x-cloak
                           x-text="template ? template.description : ''"></p>
                    </div>

                    <x-slot:footer>
                        <div class="space-y-4">
                            <button type="button" @click="showAdvanced = !showAdvanced"
                                    class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-slate-900 transition">
                                <x-icon name="chevron-down" class="w-4 h-4 transition-transform"
                                        ::class="showAdvanced && 'rotate-180'" />
                                <span x-text="showAdvanced ? 'Hide Advanced Overrides' : 'Show Advanced Overrides'">Show Advanced Overrides</span>
                            </button>

                            <div x-show="showAdvanced" x-cloak class="grid gap-4 sm:grid-cols-2">
                                <x-field label="Docker Image" hint="Blank uses the template default."
                                         :error="$errors->first('image')">
                                    <x-input name="image" value="{{ old('image') }}" class="font-mono text-xs"
                                             x-bind:placeholder="template && template.default_image ? template.default_image : ''" />
                                </x-field>
                                <x-field label="Startup Override" hint="Blank uses the template command."
                                         :error="$errors->first('startup')">
                                    <x-input name="startup" value="{{ old('startup') }}" class="font-mono text-xs"
                                             x-bind:placeholder="template && template.startup ? template.startup : ''" />
                                </x-field>
                            </div>
                        </div>
                    </x-slot:footer>
                </x-card>
            </div>

            {{-- ---------------------------------------------------- step two --}}
            <div data-step="2" x-show="step === 2" x-cloak class="space-y-6">
                @error('node_id')
                    <x-alert type="danger" title="That Placement Will Not Work">{{ $message }}</x-alert>
                @enderror

                <x-card title="Where It Runs"
                        subtitle="Auto puts it on the emptiest node that can run this template and has the room.">
                    <div class="space-y-4">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label class="flex items-start gap-3 cursor-pointer rounded-lg ring-1 ring-inset p-3 transition"
                                   :class="placement === 'auto' ? 'ring-brand-300 bg-brand-50' : 'ring-slate-200 hover:ring-slate-400'">
                                <input type="radio" x-model="placement" value="auto" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                                <span>
                                    <span class="block text-sm font-medium text-slate-900">Auto Place</span>
                                    <span class="block text-xs text-slate-500">Pick the emptiest suitable node for me.</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-3 cursor-pointer rounded-lg ring-1 ring-inset p-3 transition"
                                   :class="placement === 'manual' ? 'ring-brand-300 bg-brand-50' : 'ring-slate-200 hover:ring-slate-400'">
                                <input type="radio" x-model="placement" value="manual" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                                <span>
                                    <span class="block text-sm font-medium text-slate-900">Choose A Node</span>
                                    <span class="block text-xs text-slate-500">I know exactly where this goes.</span>
                                </span>
                            </label>
                        </div>

                        <div x-show="placement === 'auto'">
                            <x-field label="Prefer This Location" hint="Leave blank to consider every location."
                                     :error="$errors->first('location_id')">
                                <x-select name="location_id">
                                    <option value="">Anywhere</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>
                                            {{ $location->flag }} {{ $location->name }}
                                        </option>
                                    @endforeach
                                </x-select>
                            </x-field>
                        </div>

                        <div x-show="placement === 'manual'" x-cloak class="space-y-4">
                            <x-field label="Node">
                                <x-select name="node_id" x-model="nodeId">
                                    <option value="">Choose A Node</option>
                                    <template x-for="n in nodeChoices" :key="n.id">
                                        <option :value="n.id"
                                                x-text="n.name + ' (' + (n.location || 'No Location') + ', ' + n.pressure + '% full)' + (n.maintenance ? ' (maintenance)' : '')"></option>
                                    </template>
                                </x-select>
                                <p class="text-sm text-slate-500" x-show="hiddenNodeCount > 0" x-cloak>
                                    <span x-text="hiddenNodeCount"></span> node(s) are hidden because they cannot run
                                    <span x-text="template ? template.runtime_label : ''"></span> templates.
                                </p>
                            </x-field>

                            <x-field label="Port" hint="Leave it on the default and the first free port is taken."
                                     :error="$errors->first('allocation_id')">
                                <x-select name="allocation_id" x-model="allocationId">
                                    <option value="">First Free Port On The Node</option>
                                    <template x-for="a in allocationChoices" :key="a.id">
                                        <option :value="a.id" x-text="a.label + (a.notes ? ' (' + a.notes + ')' : '')"></option>
                                    </template>
                                </x-select>
                            </x-field>

                            <p class="text-sm text-rose-600" x-show="nodeId && allocationChoices.length === 0" x-cloak>
                                This node has no free ports left. Add allocations to it first, or pick another node.
                            </p>
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- -------------------------------------------------- step three --}}
            <div data-step="3" x-show="step === 3" x-cloak class="space-y-6">
                <x-card title="Owner And Name" subtitle="Who this server belongs to and what it is called.">
                    <div class="space-y-4">
                        <x-field label="Name" required :error="$errors->first('name')">
                            <x-input name="name" value="{{ old('name') }}" required maxlength="120" placeholder="Survival SMP" />
                        </x-field>
                        <x-field label="Description" hint="Optional. Shown in the server list."
                                 :error="$errors->first('description')">
                            <x-input name="description" value="{{ old('description') }}" maxlength="500" />
                        </x-field>
                        <x-field label="Owner" required :error="$errors->first('owner_id')">
                            <x-select name="owner_id" required>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('owner_id') == $user->id)>
                                        {{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </x-select>
                        </x-field>
                    </div>
                </x-card>
            </div>

            {{-- --------------------------------------------------- step four --}}
            <div data-step="4" x-show="step === 4" x-cloak class="space-y-6">
                <x-card title="Resources" subtitle="A blueprint fills these in. Change anything that does not suit.">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <x-field label="Memory (MiB)" required :error="$errors->first('memory')">
                            <x-input type="number" name="memory" value="{{ old('memory', $server->memory) }}" required min="0" />
                        </x-field>
                        <x-field label="Disk (MiB)" required :error="$errors->first('disk')">
                            <x-input type="number" name="disk" value="{{ old('disk', $server->disk) }}" required min="0" />
                        </x-field>
                        <x-field label="CPU (%)" required hint="100 is one core. Most game servers are single threaded."
                                 :error="$errors->first('cpu')">
                            <x-input type="number" name="cpu" value="{{ old('cpu', $server->cpu) }}" required min="0" />
                        </x-field>
                        <x-field label="Swap (MiB)" required hint="-1 for unlimited." :error="$errors->first('swap')">
                            <x-input type="number" name="swap" value="{{ old('swap', $server->swap) }}" required min="-1" />
                        </x-field>
                        <x-field label="Block IO Weight" required hint="Between 10 and 1000." :error="$errors->first('io')">
                            <x-input type="number" name="io" value="{{ old('io', $server->io) }}" required min="10" max="1000" />
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Feature Caps" subtitle="What the owner may create for themselves later.">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field label="Databases" required :error="$errors->first('database_limit')">
                            <x-input type="number" name="database_limit" value="{{ old('database_limit', $server->database_limit) }}" required min="0" max="50" />
                        </x-field>
                        <x-field label="Extra Ports" required :error="$errors->first('allocation_limit')">
                            <x-input type="number" name="allocation_limit" value="{{ old('allocation_limit', $server->allocation_limit) }}" required min="0" max="50" />
                        </x-field>
                        <x-field label="Backups" required hint="Locked backups sit outside this cap."
                                 :error="$errors->first('backup_limit')">
                            <x-input type="number" name="backup_limit" value="{{ old('backup_limit', $server->backup_limit) }}" required min="0" max="200" />
                        </x-field>
                    </div>
                    <div class="mt-5 space-y-4 section-divider pt-5">
                        <x-toggle name="auto_restart" :checked="(bool) old('auto_restart', $server->auto_restart)"
                                  label="Restart After A Crash"
                                  description="The watchdog brings it back unless it was stopped on purpose." />
                        <x-toggle name="auto_update" :checked="(bool) old('auto_update', $server->auto_update)"
                                  label="Update Game Files Automatically"
                                  description="Runs the template update command before every boot." />
                    </div>
                </x-card>
            </div>

            {{-- --------------------------------------------------- step five --}}
            <div data-step="5" x-show="step === 5" x-cloak class="space-y-6">
                <x-card title="Startup Variables"
                        subtitle="These are baked into the startup command. The template defaults are already filled in.">
                    <div x-show="variables.length === 0" x-cloak>
                        <x-empty-state icon="bolt" title="Nothing To Configure"
                                       description="This template exposes no variables. Move on to the review." />
                    </div>

                    <div class="space-y-5" x-show="variables.length > 0" x-cloak>
                        <template x-for="v in variables" :key="v.id">
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-slate-700" :for="'var-' + v.id">
                                    <span x-text="v.name"></span>
                                    <span class="text-rose-500" x-show="v.required">*</span>
                                </label>
                                <input type="text" :id="'var-' + v.id" :name="'variables[' + v.id + ']'"
                                       x-model="vars[v.id]"
                                       class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                                <p class="text-sm text-rose-600" x-show="variableError(v.id)" x-cloak
                                   x-text="variableError(v.id)"></p>
                                <p class="text-sm text-slate-500" x-show="!variableError(v.id) && v.description"
                                   x-text="v.description"></p>
                                <p class="text-xs text-slate-400 font-mono break-words" x-text="v.env"></p>
                            </div>
                        </template>
                    </div>
                </x-card>
            </div>

            {{-- ---------------------------------------------------- step six --}}
            <div data-step="6" x-show="step === 6" x-cloak class="space-y-6">
                <x-card title="Review" subtitle="Everything this server will be created with. Nothing is written until you press Create Server.">
                    <div class="space-y-6">
                        <template x-for="group in review" :key="group.step">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <h4 class="text-sm font-semibold text-slate-900" x-text="group.title"></h4>
                                    <button type="button" @click="go(group.step)"
                                            class="text-sm font-medium text-brand-700 rounded-lg px-2 py-1 ring-1 ring-inset ring-transparent hover:ring-brand-200 transition">
                                        Change
                                    </button>
                                </div>
                                <dl class="mt-2 divide-y divide-slate-100 rounded-lg ring-1 ring-slate-200">
                                    <template x-for="row in group.rows" :key="row[0]">
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-3 px-3 py-2">
                                            <dt class="text-sm text-slate-500 min-w-0 break-words" x-text="row[0]"></dt>
                                            <dd class="sm:col-span-2 text-sm text-slate-900 min-w-0 break-words" x-text="row[1]"></dd>
                                        </div>
                                    </template>
                                </dl>
                            </div>
                        </template>
                    </div>
                </x-card>
            </div>

            {{-- ------------------------------------------------------ footer --}}
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <x-button type="button" variant="secondary" size="sm" x-show="step > 1" x-cloak
                              icon="chevron-left" @click="back()">Back</x-button>
                </div>
                <div class="flex items-center gap-2">
                    <x-button href="{{ route('admin.servers.index') }}" variant="ghost" size="sm">Cancel</x-button>
                    <x-button type="button" size="sm" x-show="step < steps.length" @click="next()">Next</x-button>
                    <x-button type="submit" size="sm" x-show="step === steps.length" x-cloak>Create Server</x-button>
                </div>
            </div>
        </form>
    </div>

    {{-- Nodes, allocations, templates, variables and blueprints for the browser.
         A JSON island rather than a giant x-data attribute: the escaping is
         simple and the markup above stays readable. --}}
    <script type="application/json" id="server-wizard-data">@json($wizard)</script>
</x-layouts.app>
