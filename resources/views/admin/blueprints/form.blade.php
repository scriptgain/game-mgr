<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="copy" />

    @php $limits = $blueprint->limits ?? []; $features = $blueprint->feature_limits ?? []; @endphp

    <form method="POST" action="{{ $blueprint->exists ? route('admin.blueprints.update', $blueprint) : route('admin.blueprints.store') }}" class="max-w-3xl">
        @csrf
        @if ($blueprint->exists)@method('PUT')@endif

        <x-card title="Blueprint">
            <div class="space-y-4">
                <x-field label="Name" required :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name', $blueprint->name) }}" required placeholder="Minecraft Starter" />
                </x-field>
                <x-field label="Description">
                    <x-input name="description" value="{{ old('description', $blueprint->description) }}" />
                </x-field>
                <x-field label="Template" required :error="$errors->first('template_id')">
                    <x-select name="template_id" required>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}" @selected(old('template_id', $blueprint->template_id) == $template->id)>
                                {{ $template->game?->name }} : {{ $template->name }}
                            </option>
                        @endforeach
                    </x-select>
                </x-field>

                <div class="section-divider pt-4">
                    <p class="text-sm font-medium text-slate-700 mb-3">Resources</p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field label="Memory (MiB)" required><x-input type="number" name="memory" value="{{ old('memory', $limits['memory'] ?? 2048) }}" required /></x-field>
                        <x-field label="Disk (MiB)" required><x-input type="number" name="disk" value="{{ old('disk', $limits['disk'] ?? 10240) }}" required /></x-field>
                        <x-field label="CPU (%)" required><x-input type="number" name="cpu" value="{{ old('cpu', $limits['cpu'] ?? 200) }}" required /></x-field>
                        <x-field label="Swap (MiB)" required><x-input type="number" name="swap" value="{{ old('swap', $limits['swap'] ?? 0) }}" required /></x-field>
                        <x-field label="Block IO Weight" required><x-input type="number" name="io" value="{{ old('io', $limits['io'] ?? 500) }}" required /></x-field>
                    </div>
                </div>

                <div class="section-divider pt-4">
                    <p class="text-sm font-medium text-slate-700 mb-3">Feature Caps</p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field label="Databases" required><x-input type="number" name="databases" value="{{ old('databases', $features['databases'] ?? 1) }}" required /></x-field>
                        <x-field label="Allocations" required><x-input type="number" name="allocations" value="{{ old('allocations', $features['allocations'] ?? 2) }}" required /></x-field>
                        <x-field label="Backups" required><x-input type="number" name="backups" value="{{ old('backups', $features['backups'] ?? 5) }}" required /></x-field>
                    </div>
                </div>
            </div>
            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <x-button href="{{ route('admin.blueprints.index') }}" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" size="sm">{{ $blueprint->exists ? 'Save Blueprint' : 'Create Blueprint' }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts.app>
