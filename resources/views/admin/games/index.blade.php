<x-layouts.app :title="$title">
    <x-page-header title="Games" icon="controller"
                   subtitle="A game holds the templates people actually pick from.">
        <x-slot:actions>
            <x-button href="{{ route('admin.templates.import') }}" variant="secondary" icon="download" size="sm">Import Template</x-button>
            <x-button href="{{ route('admin.games.create') }}" icon="plus" size="sm">New Game</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($games as $game)
            <div class="bg-white rounded-xl ring-1 ring-slate-200 shadow-sm p-5 border border-transparent hover:border-brand-300 transition">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg ring-1 shrink-0"
                              style="background: {{ $game->cover_color ?? '#6d28d9' }}1a; color: {{ $game->cover_color ?? '#6d28d9' }}; --tw-ring-color: {{ $game->cover_color ?? '#6d28d9' }}33;">
                            <x-icon :name="$game->icon ?: 'controller'" class="w-5 h-5" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-slate-900 truncate">{{ $game->name }}</h3>
                            <p class="text-xs text-slate-400 font-mono truncate">{{ $game->slug }}</p>
                            @if ($game->category)
                                {{-- What a thing is, which stops mattering at six
                                     games and starts mattering the moment the
                                     catalogue holds things that are not games at
                                     all, such as a voice server. --}}
                                <span class="mt-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium capitalize text-slate-600">{{ $game->category }}</span>
                            @endif
                        </div>
                    </div>
                    <x-badge color="neutral">{{ $game->templates_count }} {{ Str::plural('template', $game->templates_count) }}</x-badge>
                </div>
                @if ($game->description)
                    <p class="mt-3 text-sm text-slate-600">{{ $game->description }}</p>
                @endif
                <div class="mt-4 flex items-center gap-1">
                    <x-button href="{{ route('admin.templates.index', ['game' => $game->id]) }}" variant="secondary" size="sm" icon="cube">Templates</x-button>
                    <div class="ml-auto flex items-center gap-1">
                        <x-icon-button href="{{ route('admin.games.edit', $game) }}" icon="edit" title="Edit Game" />
                        <x-delete-button
                            name="delete-game-{{ $game->id }}"
                            :action="route('admin.games.destroy', $game)"
                            title="Delete {{ $game->name }}?"
                            message="Only possible while it has no templates."
                            label="Delete Game" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
