<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="server" />

    <form method="POST" action="{{ $server->exists ? route('admin.servers.update', $server) : route('admin.servers.store') }}"
          x-data="{ placement: '{{ $server->exists ? 'manual' : 'auto' }}' }">
        @csrf
        @if ($server->exists)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Server">
                    <div class="space-y-4">
                        <x-field label="Name" required :error="$errors->first('name')">
                            <x-input name="name" value="{{ old('name', $server->name) }}" required placeholder="Survival SMP" />
                        </x-field>
                        <x-field label="Description">
                            <x-input name="description" value="{{ old('description', $server->description) }}" />
                        </x-field>
                        <x-field label="Owner" required :error="$errors->first('owner_id')">
                            <x-select name="owner_id" required>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('owner_id', $server->owner_id) == $user->id)>
                                        {{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </x-select>
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Template" subtitle="Copied onto the server at create time, so editing a template later never re-points a running server.">
                    <x-field label="Template" required :error="$errors->first('template_id')">
                        <x-select name="template_id" required :disabled="$server->exists">
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}" @selected(old('template_id', $server->template_id) == $template->id)>
                                    {{ $template->game?->name }} : {{ $template->name }} ({{ $template->runtimeLabel() }})
                                </option>
                            @endforeach
                        </x-select>
                    </x-field>
                    @if ($server->exists)
                        <p class="mt-2 text-xs text-slate-500">The template cannot be changed after creation. Reinstall onto a new server instead.</p>
                    @endif
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <x-field label="Docker Image" hint="Blank uses the template default.">
                            <x-input name="image" value="{{ old('image', $server->image) }}" class="font-mono text-xs" />
                        </x-field>
                        <x-field label="Startup Override" hint="Blank uses the template command.">
                            <x-input name="startup" value="{{ old('startup', $server->startup) }}" class="font-mono text-xs" />
                        </x-field>
                    </div>
                </x-card>

                @unless ($server->exists)
                    <x-card title="Placement" subtitle="Auto puts it on the emptiest node that can run this template and has the room.">
                        <div class="space-y-4">
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label class="flex items-start gap-3 cursor-pointer rounded-lg ring-1 ring-inset p-3 transition"
                                       :class="placement === 'auto' ? 'ring-brand-300 bg-brand-50' : 'ring-slate-200 hover:ring-slate-300'">
                                    <input type="radio" x-model="placement" value="auto" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                                    <span>
                                        <span class="block text-sm font-medium text-slate-900">Auto Place</span>
                                        <span class="block text-xs text-slate-500">Pick the emptiest suitable node for me.</span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-3 cursor-pointer rounded-lg ring-1 ring-inset p-3 transition"
                                       :class="placement === 'manual' ? 'ring-brand-300 bg-brand-50' : 'ring-slate-200 hover:ring-slate-300'">
                                    <input type="radio" x-model="placement" value="manual" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                                    <span>
                                        <span class="block text-sm font-medium text-slate-900">Choose A Node</span>
                                        <span class="block text-xs text-slate-500">I know exactly where this goes.</span>
                                    </span>
                                </label>
                            </div>

                            <div x-show="placement === 'auto'">
                                <x-field label="Prefer This Location" hint="Leave blank to consider every location.">
                                    <x-select name="location_id">
                                        <option value="">Anywhere</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}">{{ $location->flag }} {{ $location->name }}</option>
                                        @endforeach
                                    </x-select>
                                </x-field>
                            </div>

                            <div x-show="placement === 'manual'" x-cloak>
                                <x-field label="Node" :error="$errors->first('node_id')">
                                    <x-select name="node_id">
                                        <option value="">Choose a node</option>
                                        @foreach ($nodes as $node)
                                            <option value="{{ $node->id }}">
                                                {{ $node->name }} ({{ $node->location?->name }}, {{ $node->memoryPressure() }}% full)
                                            </option>
                                        @endforeach
                                    </x-select>
                                </x-field>
                            </div>
                        </div>
                    </x-card>
                @endunless
            </div>

            <div class="space-y-6">
                <x-card title="Limits">
                    <div class="space-y-4">
                        <x-field label="Memory (MiB)" required>
                            <x-input type="number" name="memory" value="{{ old('memory', $server->memory) }}" required />
                        </x-field>
                        <x-field label="Disk (MiB)" required>
                            <x-input type="number" name="disk" value="{{ old('disk', $server->disk) }}" required />
                        </x-field>
                        <x-field label="CPU (%)" required hint="100 is one core. Most game servers are single threaded, so 200 is generous.">
                            <x-input type="number" name="cpu" value="{{ old('cpu', $server->cpu) }}" required />
                        </x-field>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Swap (MiB)" required hint="-1 for unlimited.">
                                <x-input type="number" name="swap" value="{{ old('swap', $server->swap ?? 0) }}" required />
                            </x-field>
                            <x-field label="Block IO Weight" required>
                                <x-input type="number" name="io" value="{{ old('io', $server->io ?? 500) }}" required />
                            </x-field>
                        </div>
                    </div>
                </x-card>

                <x-card title="Feature Caps">
                    <div class="space-y-4">
                        <x-field label="Databases" required>
                            <x-input type="number" name="database_limit" value="{{ old('database_limit', $server->database_limit ?? 2) }}" required />
                        </x-field>
                        <x-field label="Allocations" required>
                            <x-input type="number" name="allocation_limit" value="{{ old('allocation_limit', $server->allocation_limit ?? 3) }}" required />
                        </x-field>
                        <x-field label="Backups" required hint="Locked backups sit outside this cap.">
                            <x-input type="number" name="backup_limit" value="{{ old('backup_limit', $server->backup_limit ?? 5) }}" required />
                        </x-field>
                        <x-toggle name="auto_restart" :checked="(bool) old('auto_restart', $server->auto_restart ?? true)" label="Restart After A Crash" />
                        <x-toggle name="auto_update" :checked="(bool) old('auto_update', $server->auto_update ?? false)" label="Update Game Files Automatically" />
                    </div>
                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2">
                            <x-button href="{{ route('admin.servers.index') }}" variant="secondary" size="sm">Cancel</x-button>
                            <x-button type="submit" size="sm">{{ $server->exists ? 'Save Server' : 'Create Server' }}</x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
