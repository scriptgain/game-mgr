<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    @if ($catalogue['note'])
        <div class="mb-6">
            <x-alert :type="$catalogue['tone']" :title="$catalogue['title']">
                {{ $catalogue['note'] }}
            </x-alert>
        </div>
    @endif

    <x-card title="Browse Catalogues" icon="puzzle"
            subtitle="Every result here is a file this server can actually load."
            flush>
        <x-slot:actions>
            <x-button href="{{ route('server.mods', $server) }}" variant="secondary" size="sm">Back To Installed</x-button>
        </x-slot:actions>

        {{-- One tab per catalogue that can really serve this server. A tab is
             only drawn when the source is built, available and suits the
             loader, so nothing here is a dead end. --}}
        @if (count($usable) > 1)
            {{-- Wraps onto a second row rather than scrolling sideways, per
                 house rule. Two to four catalogues fit either way. --}}
            <div class="flex flex-wrap gap-1.5 px-5 pt-4">
                @foreach ($usable as $option)
                    @php $isActive = $source && $option->key() === $source->key(); @endphp
                    <a href="{{ route('server.mods.browse', ['server' => $server, 'source' => $option->key(), 'q' => $query]) }}"
                       @if ($isActive) aria-current="page" @endif
                       class="rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors
                              {{ $isActive
                                  ? 'border-brand-600 bg-brand-600 text-white'
                                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-400 hover:text-slate-900' }}">
                        {{ $option->label() }}
                    </a>
                @endforeach
            </div>
        @endif

        @if ($byId ?? false)
            {{-- No Steam key, so there is no search endpoint to call. Installing
                 by id needs none and is how people arrive anyway: they find an
                 item on the Workshop site and come back with the link. --}}
            <div class="px-5 py-4 border-b border-slate-100">
                <form method="POST" action="{{ route('server.mods.store', $server) }}"
                      class="flex flex-wrap items-center gap-2">
                    @csrf
                    <input type="hidden" name="source" value="workshop">
                    <x-input name="project" class="flex-1 min-w-[18rem]"
                             placeholder="Workshop item id, or paste its whole address" />
                    <x-button type="submit" icon="download" size="sm">Install</x-button>
                </form>
                <p class="mt-2 text-sm text-slate-500">
                    Steam only lets a search happen with an API key. Add one in Settings, Mods and a search box appears
                    here; until then, paste the item and it installs the same way.
                </p>
            </div>
        @else
        <div class="px-5 py-4 border-b border-slate-100">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="source" value="{{ $source?->key() }}">
                <x-input name="q" value="{{ $query }}" placeholder="Search for permissions, world edit, anti-grief..."
                         class="flex-1 min-w-[16rem]" :disabled="! $catalogue['ok']" />
                <x-button type="submit" icon="search" size="sm" :disabled="! $catalogue['ok']">Search</x-button>
            </form>

            {{-- The loader and Minecraft version only mean anything to a
                 Minecraft server. Saying "Searching for Unknown files" at an
                 ARK owner is worse than saying nothing. --}}
            @if ($source && $target->loader !== null)
                <p class="mt-2 text-sm text-slate-500">
                    Searching {{ $source->label() }} for {{ $target->loaderLabel }} files only, so nothing offered here is
                    something this server cannot load.
                    @if ($target->versionKnown())
                        Narrowed to Minecraft {{ $target->gameVersion }}.
                    @else
                        <span class="text-amber-700">This server's Minecraft version is not pinned, so results are not narrowed by version.</span>
                        Pin one on the Startup tab to filter by it.
                    @endif
                </p>
            @elseif ($source instanceof \App\Services\Mods\Contracts\VariableManagedSource && $source->managesByList($target))
                <p class="mt-2 text-sm text-slate-500">
                    This game downloads its own mods. Installing one here adds it to
                    <span class="font-mono text-xs">{{ $source->listVariable() }}</span>, and the server fetches it on its
                    next start. Nothing is downloaded by the panel, so there is no checksum to verify.
                </p>
            @endif

            {{-- Named, with the reason, rather than dropped. A source that
                 vanishes silently reads as a feature that was never built. --}}
            @if ($unusable)
                <div class="mt-2 space-y-0.5">
                    @foreach ($unusable as $label => $reason)
                        <p class="text-xs text-slate-400">
                            <span class="font-medium text-slate-500">{{ $label }}:</span> {{ $reason }}
                        </p>
                    @endforeach
                </div>
            @endif
        </div>
        @endif

        @if ($byId ?? false)
            <x-empty-state icon="cube" title="Install By Id"
                           description="Paste a Workshop item above. It is fetched by steamcmd on the node itself, because Steam will not hand its content to anything else." />
        @elseif (! $catalogue['ok'])
            <x-empty-state icon="search" title="Nothing To Search"
                           description="The catalogue is unavailable, so there is nothing to search right now. Installed mods are still listed on the Mods tab." />
        @elseif ($query === '')
            <x-empty-state icon="search" title="Search {{ $source?->label() ?? 'The Catalogue' }}"
                           description="Type what you want the mod to do. Results are filtered to what this server can run and install straight onto it." />
        @elseif ($results === null)
            <x-empty-state icon="warning" title="{{ $source?->label() ?? 'The Catalogue' }} Did Not Answer"
                           description="The search timed out or was rate limited. Nothing is broken, and everything already installed still works. Try again in a moment." />
        @elseif ($results === [])
            <x-empty-state icon="search" title="Nothing Matched"
                           description="Nothing matched that{{ $target->loader ? ' for '.$target->loaderLabel : '' }}. Try a shorter or more general term." />
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($results as $result)
                    <li class="px-5 py-4 flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-slate-900">{{ $result->name }}</span>
                                <x-badge color="neutral">{{ $source?->label() }}</x-badge>
                                @if ($result->author)
                                    <span class="text-xs text-slate-400">by {{ $result->author }}</span>
                                @endif
                                @if ($result->downloads)
                                    <span class="text-xs text-slate-400">{{ number_format($result->downloads) }} downloads</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ $result->summary }}</p>
                        </div>
                        <div class="shrink-0">
                            @if (in_array($result->id, $installed, true))
                                <x-badge color="success"><x-icon name="check" class="w-3.5 h-3.5" /> Installed</x-badge>
                            @elseif (! $result->installable)
                                {{-- Hosted off-site or paid. A link, never an
                                     install button that is going to fail. --}}
                                <x-button href="{{ $result->url }}" target="_blank" rel="noopener"
                                          variant="secondary" size="sm" icon="link">Get It Yourself</x-button>
                            @elseif (auth()->user()->can('check', [$server, 'mod.install']))
                                <form method="POST" action="{{ route('server.mods.store', $server) }}">
                                    @csrf
                                    <input type="hidden" name="source" value="{{ $source?->key() }}">
                                    <input type="hidden" name="project" value="{{ $result->id }}">
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
