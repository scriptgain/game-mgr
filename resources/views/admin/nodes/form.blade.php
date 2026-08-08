<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="server"
                   subtitle="A node is one machine that runs game servers. Five short steps, and nothing here needs scrolling." />
    @if ($node->exists) @include('admin.nodes._tabs', ['node' => $node]) @endif

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
            'public' => 5, 'maintenance_mode' => 5, 'daemon_base' => 5,
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
    @endphp

    {{-- No max-w here, and none on the step panels either. The layout already
         sets the page width from config('gamemgr.max_width'); a second cap
         inside it renders a narrow column stranded on a wide screen. The width
         is put to work by moving the step rail into a right hand column, where
         it stays visible while you work through a step. --}}
    <form method="POST" action="{{ $node->exists ? route('admin.nodes.update', $node) : route('admin.nodes.store') }}"
          x-data="{
              step: {{ $errors->any() ? $firstBadStep : 1 }},
              last: 5,
              editing: {{ $node->exists ? 'true' : 'false' }},
              mode: @js(old('connection_mode', $node->connection_mode ?? 'direct')),
              steps: [
                  { n: 1, label: 'Identity' },
                  { n: 2, label: 'Connection' },
                  { n: 3, label: 'Runtimes' },
                  { n: 4, label: 'Capacity' },
                  { n: 5, label: 'Placement' },
              ],
              go(n) { this.step = n; window.scrollTo({ top: 0, behavior: 'smooth' }); },
              next() { if (this.step < this.last) this.go(this.step + 1); },
              back() { if (this.step > 1) this.go(this.step - 1); },
          }">
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
            <div class="lg:col-span-2 space-y-6">

                {{-- 1: identity --}}
                <div x-show="step === 1" x-cloak>
                    <x-card title="Identity" subtitle="What this machine is called and where it lives.">
                        <div class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="Name" required :error="$errors->first('name')">
                                    <x-input name="name" value="{{ old('name', $node->name) }}" required placeholder="phx-docker-01" />
                                </x-field>
                                <x-field label="Location" required :error="$errors->first('location_id')">
                                    <x-select name="location_id" required>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}" @selected(old('location_id', $node->location_id) == $location->id)>
                                                {{ $location->flag }} {{ $location->name }}
                                            </option>
                                        @endforeach
                                    </x-select>
                                </x-field>
                            </div>
                            <x-field label="Description" hint="For your benefit later, when you have twenty of these." :error="$errors->first('description')">
                                <x-input name="description" value="{{ old('description', $node->description) }}" placeholder="General purpose Docker node" />
                            </x-field>
                        </div>
                        <x-slot:footer>
                            <div class="flex items-center justify-between gap-2">
                                <x-button href="{{ route('admin.nodes.index') }}" variant="secondary" size="sm">Cancel</x-button>
                                <x-button type="button" size="sm" @click="next()">Continue</x-button>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>

                {{-- 2: connection --}}
                <div x-show="step === 2" x-cloak>
                    <x-card title="How The Panel Reaches It"
                            subtitle="Reverse mode is how a machine behind NAT, with no port forwarding, still works.">
                        <div class="space-y-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="flex items-start gap-3 cursor-pointer rounded-lg ring-1 ring-inset p-4 transition"
                                       :class="mode === 'direct' ? 'ring-brand-300 bg-brand-50' : 'ring-slate-200 hover:ring-slate-300'">
                                    <input type="radio" name="connection_mode" value="direct" x-model="mode" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                                    <span>
                                        <span class="block text-sm font-medium text-slate-900">Direct</span>
                                        <span class="block text-xs text-slate-500">The panel dials the daemon. Needs a reachable port.</span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-3 cursor-pointer rounded-lg ring-1 ring-inset p-4 transition"
                                       :class="mode === 'reverse' ? 'ring-brand-300 bg-brand-50' : 'ring-slate-200 hover:ring-slate-300'">
                                    <input type="radio" name="connection_mode" value="reverse" x-model="mode" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                                    <span>
                                        <span class="block text-sm font-medium text-slate-900">Reverse</span>
                                        <span class="block text-xs text-slate-500">The daemon dials the panel. Works behind NAT.</span>
                                    </span>
                                </label>
                            </div>

                            <div x-show="mode === 'direct'" class="grid gap-4 sm:grid-cols-3">
                                <x-field label="Scheme">
                                    <x-select name="scheme">
                                        <option value="https" @selected(old('scheme', $node->scheme) === 'https')>https</option>
                                        <option value="http" @selected(old('scheme', $node->scheme) === 'http')>http</option>
                                    </x-select>
                                </x-field>
                                <x-field label="Hostname Or IP" class="sm:col-span-2" :error="$errors->first('fqdn')">
                                    <x-input name="fqdn" value="{{ old('fqdn', $node->fqdn) }}" placeholder="node1.example.com" />
                                </x-field>
                            </div>

                            <div x-show="mode === 'reverse'" x-cloak>
                                <x-alert type="info">
                                    Nothing needs to be reachable from the internet. The daemon holds an outbound connection to
                                    this panel and work is pushed down it.
                                </x-alert>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <x-field label="Daemon Port" required :error="$errors->first('daemon_port')">
                                    <x-input type="number" name="daemon_port" value="{{ old('daemon_port', $node->daemon_port ?: config('node.default_port')) }}" required />
                                </x-field>
                                <x-field label="SFTP Port" required :error="$errors->first('sftp_port')">
                                    <x-input type="number" name="sftp_port" value="{{ old('sftp_port', $node->sftp_port ?: 2022) }}" required />
                                </x-field>
                            </div>

                            <x-toggle name="behind_proxy" :checked="(bool) old('behind_proxy', $node->behind_proxy)"
                                      label="Behind A Reverse Proxy" description="Tells the daemon to trust forwarded headers." />
                        </div>
                        <x-slot:footer>
                            <div class="flex items-center justify-between gap-2">
                                <x-button type="button" variant="secondary" size="sm" @click="back()">Back</x-button>
                                <x-button type="button" size="sm" @click="next()">Continue</x-button>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>

                {{-- 3: runtimes --}}
                <div x-show="step === 3" x-cloak>
                    <x-card title="Runtimes"
                            subtitle="What this machine can run. The server create form only offers templates the node supports.">
                        <div class="space-y-3">
                            @foreach ([
                                'docker' => ['Containerised. What most community templates target, so leave this on unless the box has no Docker.', 'Needs the Docker daemon.'],
                                'steamcmd' => ['Native install with no container in the way. Better for Source and Unreal servers, and on bare metal.', 'Needs steamcmd on PATH.'],
                                'linuxgsm' => ['Wraps the LinuxGSM control scripts, bringing a catalogue of 130+ games with them.', 'Needs tmux, which holds the console.'],
                            ] as $runtime => $copy)
                                <div class="rounded-lg ring-1 ring-inset ring-slate-200 p-4 hover:ring-slate-300 transition">
                                    {{-- Posts as runtimes[<key>] = 1 or 0. A toggle uses a hidden
                                         input rather than a checkbox, so switching one off posts a
                                         0 instead of vanishing; the controller folds the map back
                                         into a list. --}}
                                    <x-toggle name="runtimes[{{ $runtime }}]" :checked="in_array($runtime, $activeRuntimes, true)">
                                        <x-runtime-badge :runtime="$runtime" />
                                        <span class="block mt-1.5 text-sm text-slate-600">{{ $copy[0] }}</span>
                                        <span class="block mt-0.5 text-xs text-slate-400">{{ $copy[1] }}</span>
                                    </x-toggle>
                                </div>
                            @endforeach
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

                {{-- 4: capacity --}}
                <div x-show="step === 4" x-cloak>
                    <x-card title="Capacity"
                            subtitle="Over-allocation lets a node promise more than it has, which is normal for game hosting because servers rarely peak together.">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Memory (MiB)" required :error="$errors->first('memory')">
                                <x-input type="number" name="memory" value="{{ old('memory', $node->memory ?: 0) }}" required />
                            </x-field>
                            <x-field label="Memory Over-allocation (%)" required hint="0 never promises more than exists." :error="$errors->first('memory_overallocate')">
                                <x-input type="number" name="memory_overallocate" value="{{ old('memory_overallocate', $node->memory_overallocate ?: 0) }}" required />
                            </x-field>
                            <x-field label="Disk (MiB)" required :error="$errors->first('disk')">
                                <x-input type="number" name="disk" value="{{ old('disk', $node->disk ?: 0) }}" required />
                            </x-field>
                            <x-field label="Disk Over-allocation (%)" required :error="$errors->first('disk_overallocate')">
                                <x-input type="number" name="disk_overallocate" value="{{ old('disk_overallocate', $node->disk_overallocate ?: 0) }}" required />
                            </x-field>
                            <x-field label="CPU (%)" required hint="100 is one full core. A 16 core box is 1600." :error="$errors->first('cpu')">
                                <x-input type="number" name="cpu" value="{{ old('cpu', $node->cpu ?: 0) }}" required />
                            </x-field>
                            <x-field label="CPU Over-allocation (%)" required :error="$errors->first('cpu_overallocate')">
                                <x-input type="number" name="cpu_overallocate" value="{{ old('cpu_overallocate', $node->cpu_overallocate ?: 0) }}" required />
                            </x-field>
                            <x-field label="File Upload Limit (MiB)" required :error="$errors->first('upload_size')">
                                <x-input type="number" name="upload_size" value="{{ old('upload_size', $node->upload_size ?: 256) }}" required />
                            </x-field>
                        </div>
                        <x-slot:footer>
                            <div class="flex items-center justify-between gap-2">
                                <x-button type="button" variant="secondary" size="sm" @click="back()">Back</x-button>
                                <x-button type="button" size="sm" @click="next()">Continue</x-button>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>

                {{-- 5: placement --}}
                <div x-show="step === 5" x-cloak>
                    <x-card title="Placement" subtitle="Whether servers land here on their own, and where their files go.">
                        <div class="space-y-5">
                            <x-toggle name="public" :checked="(bool) old('public', $node->public ?? true)"
                                      label="Available For Auto Placement"
                                      description="Off means servers only land here when somebody picks this node by hand." />
                            <x-toggle name="maintenance_mode" :checked="(bool) old('maintenance_mode', $node->maintenance_mode)"
                                      label="Maintenance Mode"
                                      description="Blocks new placements. Servers already here keep running." />
                            <x-field label="Data Directory" required hint="Where server files live on the machine. Changing it on a live node strands every server already on it." :error="$errors->first('daemon_base')">
                                <x-input name="daemon_base" value="{{ old('daemon_base', $node->daemon_base ?: '/var/lib/gamemgr/volumes') }}" required class="font-mono text-xs" />
                            </x-field>
                        </div>
                        <x-slot:footer>
                            <div class="flex items-center justify-between gap-2">
                                <x-button type="button" variant="secondary" size="sm" @click="back()">Back</x-button>
                                <x-button type="submit" size="sm" icon="check">
                                    {{ $node->exists ? 'Save Node' : 'Create Node' }}
                                </x-button>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>
            </div>

            {{-- Step rail. On an existing node every step is clickable: editing one
                 port should not mean walking through five screens. On a new node it
                 is a progress indicator and only goes forward. --}}
            <div class="space-y-6">
                <x-card title="Steps" :subtitle="$node->exists ? 'Jump straight to the one you came for.' : 'Each one unlocks as you go.'">
                    <ol class="space-y-1.5">
                        <template x-for="s in steps" :key="s.n">
                            <li>
                                <button type="button" @click="(editing || s.n <= step) && go(s.n)"
                                        :disabled="!editing && s.n > step"
                                        class="w-full inline-flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-left transition border"
                                        :class="s.n === step
                                            ? 'bg-brand-50 text-brand-700 border-brand-200'
                                            : ((editing || s.n < step)
                                                ? 'text-slate-600 border-transparent hover:bg-slate-100 hover:border-slate-200'
                                                : 'text-slate-400 border-transparent cursor-default')">
                                    <span class="inline-flex items-center justify-center w-6 h-6 shrink-0 rounded-full text-[11px] font-semibold"
                                          :class="s.n < step ? 'bg-emerald-500 text-white' : (s.n === step ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-500')"
                                          x-text="s.n"></span>
                                    <span class="min-w-0 truncate" x-text="s.label"></span>
                                </button>
                            </li>
                        </template>
                    </ol>
                </x-card>

                {{-- Editing should not require walking to the last step just to save. --}}
                <x-card>
                    <div class="flex flex-col gap-2">
                        <x-button type="submit" class="w-full" icon="check" x-show="editing && step !== last" x-cloak>Save Node</x-button>
                        <p x-show="!editing && step < last" x-cloak class="text-sm text-slate-500">
                            Walk through the remaining steps and the node is created at the end.
                        </p>
                        <x-button href="{{ route('admin.nodes.index') }}" variant="secondary" class="w-full">Cancel</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
