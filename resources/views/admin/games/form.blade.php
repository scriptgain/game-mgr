<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="controller" />

    <form method="POST" action="{{ $game->exists ? route('admin.games.update', $game) : route('admin.games.store') }}" class="max-w-2xl">
        @csrf
        @if ($game->exists)@method('PUT')@endif
        <x-card title="Game">
            <div class="space-y-4">
                <x-field label="Name" required :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name', $game->name) }}" required placeholder="Minecraft" />
                </x-field>
                <x-field label="Description">
                    <x-input name="description" value="{{ old('description', $game->description) }}" placeholder="Java and Bedrock editions, vanilla through heavily modded." />
                </x-field>
                <x-field label="Author">
                    <x-input name="author" value="{{ old('author', $game->author) }}" />
                </x-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Icon" hint="An icon name from the built-in set.">
                        <x-input name="icon" value="{{ old('icon', $game->icon) }}" placeholder="cube" />
                    </x-field>
                    <x-field label="Cover Colour" hint="Hex, used for the chip on the games list.">
                        <x-input name="cover_color" value="{{ old('cover_color', $game->cover_color) }}" placeholder="#16a34a" />
                    </x-field>
                </div>
            </div>
            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <x-button href="{{ route('admin.games.index') }}" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" size="sm">{{ $game->exists ? 'Save Game' : 'Create Game' }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts.app>
