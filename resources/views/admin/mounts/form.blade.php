<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="folder"
                   subtitle="A mount shares one directory on a node into the servers you allow, so a map pool or an asset library lives in one place instead of a copy per server." />

    {{-- No max-w here. The layout already sets the page width from
         config('gamemgr.max_width'), and a second cap inside it makes a wide
         screen render a narrow column with empty space either side. --}}
    <form method="POST" action="{{ $mount->exists ? route('admin.mounts.update', $mount) : route('admin.mounts.store') }}">
        @csrf
        @if ($mount->exists)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3 items-start">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="The Mount">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Name" required :error="$errors->first('name')">
                                <x-input name="name" value="{{ old('name', $mount->name) }}" required placeholder="Shared Map Pool" />
                            </x-field>
                            <x-field label="Description">
                                <x-input name="description" value="{{ old('description', $mount->description) }}" placeholder="What lives in here" />
                            </x-field>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Path On The Node" required :error="$errors->first('source')"
                                     hint="Must already exist on every node you allow below.">
                                <x-input name="source" value="{{ old('source', $mount->source) }}" required class="font-mono text-xs" placeholder="/srv/gamemgr/maps" />
                            </x-field>
                            <x-field label="Path Inside The Server" required :error="$errors->first('target')"
                                     hint="Where the server sees it, relative to its own files.">
                                <x-input name="target" value="{{ old('target', $mount->target) }}" required class="font-mono text-xs" placeholder="/maps" />
                            </x-field>
                        </div>
                    </div>
                </x-card>

                <x-card title="Where It May Be Used"
                        subtitle="A mount is offered only where both lists allow it. Leave a list empty to allow everything in it.">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <p class="text-sm font-medium text-slate-700 mb-3">Nodes</p>
                            @php $selectedNodes = (array) old('nodes', $mount->exists ? $mount->nodes->pluck('id')->all() : []); @endphp
                            @if ($nodes->isEmpty())
                                <p class="text-sm text-slate-500">No nodes yet.</p>
                            @else
                                <div class="space-y-2.5">
                                    @foreach ($nodes as $node)
                                        <x-check-switch name="nodes[]" :value="$node->id"
                                                        :checked="in_array($node->id, $selectedNodes)">{{ $node->name }}</x-check-switch>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-700 mb-3">Templates</p>
                            @php $selectedTemplates = (array) old('templates', $mount->exists ? $mount->templates->pluck('id')->all() : []); @endphp
                            @if ($templates->isEmpty())
                                <p class="text-sm text-slate-500">No templates yet.</p>
                            @else
                                <div class="space-y-2.5">
                                    @foreach ($templates as $template)
                                        <x-check-switch name="templates[]" :value="$template->id"
                                                        :checked="in_array($template->id, $selectedTemplates)">{{ $template->game?->name }}: {{ $template->name }}</x-check-switch>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Access">
                    <div class="space-y-5">
                        <x-toggle name="read_only" :checked="(bool) old('read_only', $mount->read_only ?? true)"
                                  label="Read Only"
                                  description="Leave this on unless a server genuinely needs to write back to a shared path. One server writing to a pool every other server reads is how a bad file reaches all of them at once." />
                        <x-toggle name="user_mountable" :checked="(bool) old('user_mountable', $mount->user_mountable)"
                                  label="Clients May Attach It"
                                  description="Off means only an administrator can put this on a server." />
                    </div>
                </x-card>

                <x-card>
                    <div class="flex flex-col gap-2">
                        <x-button type="submit" class="w-full">{{ $mount->exists ? 'Save Mount' : 'Create Mount' }}</x-button>
                        <x-button href="{{ route('admin.mounts.index') }}" variant="secondary" class="w-full">Cancel</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
