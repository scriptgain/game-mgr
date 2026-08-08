<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="copy"
                   subtitle="A blueprint is a saved server shape: one template plus its limits and caps, so the hundredth Minecraft Starter is one click rather than fifteen fields retyped." />

    @php $limits = $blueprint->limits ?? []; $features = $blueprint->feature_limits ?? []; @endphp

    {{-- No max-w here. The layout already sets the page width from
         config('gamemgr.max_width'), and a second cap inside it renders a
         narrow column stranded in the middle of a wide screen. --}}
    <form method="POST" action="{{ $blueprint->exists ? route('admin.blueprints.update', $blueprint) : route('admin.blueprints.store') }}">
        @csrf
        @if ($blueprint->exists)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3 items-start">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="The Blueprint">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Name" required :error="$errors->first('name')">
                            <x-input name="name" value="{{ old('name', $blueprint->name) }}" required placeholder="Minecraft Starter" />
                        </x-field>
                        <x-field label="Description" :error="$errors->first('description')"
                                 hint="What this shape is for, in one line.">
                            <x-input name="description" value="{{ old('description', $blueprint->description) }}" placeholder="Small survival server, four to six players" />
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Resources" subtitle="What every server built from this blueprint is allowed on its node.">
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <x-field label="Memory (MiB)" required :error="$errors->first('memory')">
                            <x-input type="number" name="memory" value="{{ old('memory', $limits['memory'] ?? 2048) }}" required />
                        </x-field>
                        <x-field label="Disk (MiB)" required :error="$errors->first('disk')">
                            <x-input type="number" name="disk" value="{{ old('disk', $limits['disk'] ?? 10240) }}" required />
                        </x-field>
                        <x-field label="CPU (%)" required :error="$errors->first('cpu')" hint="100 is one full core.">
                            <x-input type="number" name="cpu" value="{{ old('cpu', $limits['cpu'] ?? 200) }}" required />
                        </x-field>
                        <x-field label="Swap (MiB)" required :error="$errors->first('swap')" hint="0 disables it, -1 leaves it unlimited.">
                            <x-input type="number" name="swap" value="{{ old('swap', $limits['swap'] ?? 0) }}" required />
                        </x-field>
                        <x-field label="Block IO Weight" required :error="$errors->first('io')" hint="Between 10 and 1000. 500 is the normal share.">
                            <x-input type="number" name="io" value="{{ old('io', $limits['io'] ?? 500) }}" required />
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Feature Caps" subtitle="How much of each the owner may create for themselves.">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field label="Databases" required :error="$errors->first('databases')">
                            <x-input type="number" name="databases" value="{{ old('databases', $features['databases'] ?? 1) }}" required />
                        </x-field>
                        <x-field label="Allocations" required :error="$errors->first('allocations')">
                            <x-input type="number" name="allocations" value="{{ old('allocations', $features['allocations'] ?? 2) }}" required />
                        </x-field>
                        <x-field label="Backups" required :error="$errors->first('backups')">
                            <x-input type="number" name="backups" value="{{ old('backups', $features['backups'] ?? 5) }}" required />
                        </x-field>
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Built From" subtitle="The template that installs and runs the game.">
                    <x-field label="Template" required :error="$errors->first('template_id')">
                        <x-select name="template_id" required>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}" @selected(old('template_id', $blueprint->template_id) == $template->id)>
                                    {{ $template->game?->name }} : {{ $template->name }}
                                </option>
                            @endforeach
                        </x-select>
                    </x-field>
                </x-card>

                <x-card>
                    <div class="flex flex-col gap-2">
                        <x-button type="submit" class="w-full">{{ $blueprint->exists ? 'Save Blueprint' : 'Create Blueprint' }}</x-button>
                        <x-button href="{{ route('admin.blueprints.index') }}" variant="secondary" class="w-full">Cancel</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
