<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="globe" />

    <form method="POST" action="{{ $location->exists ? route('admin.locations.update', $location) : route('admin.locations.store') }}" class="max-w-2xl">
        @csrf
        @if ($location->exists)@method('PUT')@endif
        <x-card title="Location">
            <div class="space-y-4">
                <x-field label="Name" required :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name', $location->name) }}" required placeholder="Frankfurt" />
                </x-field>
                <x-field label="Short Code" required hint="Used in URLs and in generated hostnames. Keep it lowercase." :error="$errors->first('short')">
                    <x-input name="short" value="{{ old('short', $location->short) }}" required placeholder="eu-fra" />
                </x-field>
                <x-field label="Description">
                    <x-input name="description" value="{{ old('description', $location->description) }}" placeholder="European region, low latency to the UK" />
                </x-field>
                <x-field label="Flag" hint="A single emoji. Purely cosmetic, but it makes a long node list scannable.">
                    <x-input name="flag" value="{{ old('flag', $location->flag) }}" placeholder="🇩🇪" class="w-24" />
                </x-field>
            </div>
            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <x-button href="{{ route('admin.locations.index') }}" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" size="sm">{{ $location->exists ? 'Save Location' : 'Create Location' }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts.app>
