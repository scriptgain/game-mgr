<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="controller"
                   subtitle="A game groups the templates that install it, so twenty Minecraft variants sit under one heading instead of scattered through the catalogue." />

    {{-- No max-w here. The layout already sets the page width from
         config('gamemgr.max_width'), and a second cap inside it renders a
         narrow column stranded in the middle of a wide screen. --}}
    <form method="POST" action="{{ $game->exists ? route('admin.games.update', $game) : route('admin.games.store') }}">
        @csrf
        @if ($game->exists)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3 items-start">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="The Game">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Name" required :error="$errors->first('name')">
                                <x-input name="name" value="{{ old('name', $game->name) }}" required placeholder="Minecraft" />
                            </x-field>
                            <x-field label="Author" :error="$errors->first('author')"
                                     hint="Who maintains the templates under it.">
                                <x-input name="author" value="{{ old('author', $game->author) }}" placeholder="Mojang" />
                            </x-field>
                        </div>
                        <x-field label="Description" :error="$errors->first('description')"
                                 hint="One line. It shows under the name on the games list.">
                            <x-input name="description" value="{{ old('description', $game->description) }}" placeholder="Java and Bedrock editions, vanilla through heavily modded." />
                        </x-field>
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Appearance" subtitle="How it is drawn on the games list. Both are optional.">
                    <div class="space-y-4">
                        <x-field label="Icon" :error="$errors->first('icon')" hint="An icon name from the built-in set.">
                            <x-input name="icon" value="{{ old('icon', $game->icon) }}" placeholder="cube" class="font-mono text-xs" />
                        </x-field>
                        <x-field label="Cover Colour" :error="$errors->first('cover_color')" hint="Hex, used for the chip on the games list.">
                            <x-input name="cover_color" value="{{ old('cover_color', $game->cover_color) }}" placeholder="#16a34a" class="font-mono text-xs" />
                        </x-field>
                    </div>
                </x-card>

                <x-card>
                    <div class="flex flex-col gap-2">
                        <x-button type="submit" class="w-full">{{ $game->exists ? 'Save Game' : 'Create Game' }}</x-button>
                        <x-button href="{{ route('admin.games.index') }}" variant="secondary" class="w-full">Cancel</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
