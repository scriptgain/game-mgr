<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="globe"
                   subtitle="A location is the region a node sits in, so a client picking where their server lives chooses a place rather than a hostname." />

    {{-- No max-w here. The layout already sets the page width from
         config('gamemgr.max_width'), and a second cap inside it renders a
         narrow column stranded in the middle of a wide screen. --}}
    <form method="POST" action="{{ $location->exists ? route('admin.locations.update', $location) : route('admin.locations.store') }}">
        @csrf
        @if ($location->exists)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3 items-start">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="The Location" icon="flag">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Name" required :error="$errors->first('name')">
                                <x-input name="name" value="{{ old('name', $location->name) }}" required placeholder="Frankfurt" />
                            </x-field>
                            <x-field label="Short Code" required
                                     hint="Used in URLs and in generated hostnames. Keep it lowercase."
                                     :error="$errors->first('short')">
                                <x-input name="short" value="{{ old('short', $location->short) }}" required placeholder="eu-fra" class="font-mono text-xs" />
                            </x-field>
                        </div>
                        <x-field label="Description" :error="$errors->first('description')"
                                 hint="One line. It shows next to the location wherever a node is placed.">
                            <x-input name="description" value="{{ old('description', $location->description) }}" placeholder="European region, low latency to the UK" />
                        </x-field>
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Appearance" icon="eye">
                    <x-field label="Flag" :error="$errors->first('flag')"
                             hint="A single emoji. Purely cosmetic, but it makes a long node list scannable.">
                        <x-input name="flag" value="{{ old('flag', $location->flag) }}" placeholder="🇩🇪" class="w-24 text-lg" />
                    </x-field>
                </x-card>

                <x-card>
                    <div class="flex flex-col gap-2">
                        <x-button type="submit" class="w-full">{{ $location->exists ? 'Save Location' : 'Create Location' }}</x-button>
                        <x-button href="{{ route('admin.locations.index') }}" variant="secondary" class="w-full">Cancel</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
