<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="folder" />

    <form method="POST" action="{{ $mount->exists ? route('admin.mounts.update', $mount) : route('admin.mounts.store') }}" class="max-w-3xl">
        @csrf
        @if ($mount->exists)@method('PUT')@endif

        <x-card title="Mount">
            <div class="space-y-4">
                <x-field label="Name" required :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name', $mount->name) }}" required placeholder="Shared Map Pool" />
                </x-field>
                <x-field label="Description">
                    <x-input name="description" value="{{ old('description', $mount->description) }}" />
                </x-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Path On The Node" required :error="$errors->first('source')">
                        <x-input name="source" value="{{ old('source', $mount->source) }}" required class="font-mono text-xs" placeholder="/srv/gamemgr/maps" />
                    </x-field>
                    <x-field label="Path Inside The Server" required :error="$errors->first('target')">
                        <x-input name="target" value="{{ old('target', $mount->target) }}" required class="font-mono text-xs" placeholder="/maps" />
                    </x-field>
                </div>
                <x-toggle name="read_only" :checked="(bool) old('read_only', $mount->read_only ?? true)"
                          label="Read Only" description="Leave this on unless a server genuinely needs to write back to a shared path." />
                <x-toggle name="user_mountable" :checked="(bool) old('user_mountable', $mount->user_mountable)"
                          label="Clients May Attach It" description="Off means only an administrator can put this on a server." />

                <div class="grid gap-5 sm:grid-cols-2 section-divider pt-4">
                    <div>
                        <p class="text-sm font-medium text-slate-700 mb-2">Allowed On These Nodes</p>
                        @php $selectedNodes = (array) old('nodes', $mount->exists ? $mount->nodes->pluck('id')->all() : []); @endphp
                        <div class="space-y-2 max-h-56 overflow-y-auto vx-scroll pr-1">
                            @foreach ($nodes as $node)
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox" name="nodes[]" value="{{ $node->id }}"
                                           @checked(in_array($node->id, $selectedNodes))
                                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    <span class="text-sm text-slate-700">{{ $node->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-700 mb-2">Allowed For These Templates</p>
                        @php $selectedTemplates = (array) old('templates', $mount->exists ? $mount->templates->pluck('id')->all() : []); @endphp
                        <div class="space-y-2 max-h-56 overflow-y-auto vx-scroll pr-1">
                            @foreach ($templates as $template)
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox" name="templates[]" value="{{ $template->id }}"
                                           @checked(in_array($template->id, $selectedTemplates))
                                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    <span class="text-sm text-slate-700 truncate">{{ $template->game?->name }} : {{ $template->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <x-button href="{{ route('admin.mounts.index') }}" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" size="sm">{{ $mount->exists ? 'Save Mount' : 'Create Mount' }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts.app>
