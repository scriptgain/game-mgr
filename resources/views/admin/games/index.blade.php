<x-layouts.app :title="$title">
    <x-page-header title="Games" icon="controller"
                   subtitle="Every game this panel can host. A game holds the templates people actually pick from.">
        <x-slot:actions>
            <x-button href="{{ route('admin.templates.import') }}" variant="secondary" icon="download" size="sm">Import Template</x-button>
            <x-button href="{{ route('admin.games.create') }}" icon="plus" size="sm">New Game</x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Searched and paged on the server. The catalogue is nearly two hundred
         games, and filtering in the browser does not help when the cost is in
         sending them all. --}}
    <form method="GET" action="{{ route('admin.games.index') }}" class="mb-5">
        <div class="flex flex-wrap items-end gap-3">
            <div class="relative min-w-0 flex-1 sm:max-w-xs">
                <label for="game-search" class="sr-only">Search Games</label>
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input id="game-search" type="search" name="q" value="{{ request('q') }}"
                       placeholder="Search {{ number_format($total) }} games"
                       class="block w-full rounded-lg border-0 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">
            </div>

            @if ($categories->isNotEmpty())
                <div class="w-44">
                    <label for="game-category" class="sr-only">Category</label>
                    <x-select name="category" id="game-category">
                        <option value="">Every Category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ Str::headline($category) }}</option>
                        @endforeach
                    </x-select>
                </div>
            @endif

            <x-button type="submit" size="sm" icon="search">Search</x-button>

            @if (request('q') || request('category'))
                <x-button href="{{ route('admin.games.index') }}" variant="secondary" size="sm">Clear</x-button>
            @endif

            <span class="ms-auto text-sm text-slate-500">
                {{ number_format($games->total()) }} {{ Str::plural('game', $games->total()) }}
                @if (request('q') || request('category')) matched @endif
            </span>
        </div>
    </form>

    @if ($games->isEmpty())
        <x-card>
            <x-empty-state icon="search" title="Nothing Matched"
                           description="No game here matches that. Try a shorter search, or clear the filters.">
                <x-slot:action><x-button href="{{ route('admin.games.index') }}">Show Every Game</x-button></x-slot:action>
            </x-empty-state>
        </x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($games as $game)
                <div class="group flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 transition hover:ring-brand-300 hover:shadow-md">
                    {{-- Art-led. A 460x215 header at this aspect is the whole
                         reason the page is worth looking at; the glyph tile is
                         the fallback and fills the same box so the grid never
                         goes ragged. --}}
                    <a href="{{ route('admin.templates.index', ['game' => $game->id]) }}" class="block">
                        <x-game-art :game="$game" class="aspect-[460/215] w-full" icon-class="w-10 h-10 opacity-90" />
                    </a>

                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-slate-900">{{ $game->name }}</h3>
                                <p class="truncate font-mono text-xs text-slate-400">{{ $game->slug }}</p>
                            </div>
                            <x-badge color="neutral">{{ $game->templates_count }}</x-badge>
                        </div>

                        @if ($game->description)
                            <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $game->description }}</p>
                        @endif

                        <div class="mt-auto flex items-center gap-1 pt-4">
                            @if ($game->category)
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium capitalize text-slate-600">{{ Str::headline($game->category) }}</span>
                            @endif
                            <div class="ms-auto flex items-center gap-1">
                                <x-icon-button href="{{ route('admin.templates.index', ['game' => $game->id]) }}" icon="cube" title="Templates For {{ $game->name }}" />
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
                </div>
            @endforeach
        </div>

        <div class="mt-6">{{ $games->links() }}</div>
    @endif
</x-layouts.app>
