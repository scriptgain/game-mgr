<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <x-card title="Browse Mods" subtitle="Search across every catalogue this template supports." flush>
        <x-slot:actions>
            <x-button href="{{ route('server.mods', $server) }}" variant="secondary" size="sm">Back To Installed</x-button>
        </x-slot:actions>

        <div class="px-5 py-4 border-b border-slate-100">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <x-input name="q" value="{{ $query }}" placeholder="Search for permissions, world edit, anti-grief..." class="flex-1 min-w-[16rem]" />
                <x-button type="submit" icon="search" size="sm">Search</x-button>
            </form>
            @if ($sources)
                <p class="mt-2 text-xs text-slate-500">
                    Searching {{ collect($sources)->map(fn ($s) => \App\Models\Mod::SOURCES[$s] ?? $s)->join(', ', ' and ') }}.
                </p>
            @endif
        </div>

        @if ($query === '')
            <x-empty-state icon="search" title="Search The Catalogue"
                           description="Type what you want the mod to do. Results come from the sources this template declares." />
        @elseif (empty($results))
            <x-empty-state icon="search" title="Nothing Matched"
                           description="No mod matched that. Try a shorter or more general term." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($results as $result)
                    <li class="px-5 py-4 flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-slate-900">{{ $result['name'] }}</span>
                                <x-badge color="neutral">{{ \App\Models\Mod::SOURCES[$result['source']] ?? $result['source'] }}</x-badge>
                                <span class="text-xs text-slate-400">v{{ $result['version'] }} by {{ $result['author'] }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ $result['summary'] }}</p>
                        </div>
                        <div class="shrink-0">
                            @if (in_array($result['slug'], $installed, true))
                                <x-badge color="success"><x-icon name="check" class="w-3.5 h-3.5" /> Installed</x-badge>
                            @else
                                <form method="POST" action="{{ route('server.mods.store', $server) }}">
                                    @csrf
                                    <input type="hidden" name="source" value="{{ $result['source'] }}">
                                    <input type="hidden" name="name" value="{{ $result['name'] }}">
                                    <input type="hidden" name="author" value="{{ $result['author'] }}">
                                    <input type="hidden" name="version" value="{{ $result['version'] }}">
                                    <input type="hidden" name="summary" value="{{ $result['summary'] }}">
                                    <x-button type="submit" size="sm" icon="download">Install</x-button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-layouts.app>
