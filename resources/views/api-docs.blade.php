{{-- The API reference.

     Standalone rather than inside the panel chrome, because this is a document
     somebody reads with the panel in another tab, or before they have installed
     anything at all. Docs pages want a wide sticky index and a long readable
     column, which is the opposite of what an admin layout is shaped for.

     Everything below is rendered from the OpenAPI document, so it cannot
     describe an endpoint that does not exist or miss one that does. --}}
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $spec['info']['title'] }} Reference</title>
    <meta name="description" content="Every endpoint in the {{ config('brand.name') }} API, generated from the routes themselves.">
    <link rel="icon" type="image/svg+xml" href="{{ route('favicon.svg') }}">
    <x-tailwind-cdn />
    <x-accent-style />
    <style>
        /* Plain CSS, not Tailwind utilities, for the handful of things the CDN
           build cannot express: the two column shell, the method chips, and the
           scroll offset that stops an anchor landing under the sticky header. */
        :root { --nav: 17rem; }
        body { background: #f8fafc; }
        .docs { display: grid; grid-template-columns: var(--nav) minmax(0, 1fr); gap: 2.5rem; align-items: start; }
        @media (max-width: 1024px) { .docs { grid-template-columns: 1fr; gap: 1.5rem; }
            .docs-aside { position: static !important; max-height: none !important; }
            .docs-nav { max-height: 22rem; } }
        /* The filter box sits OUTSIDE the scrolling area. When the whole aside
           scrolled, the scrollbar ran the full height and pressed against the
           search input, which looked like a mistake and put a grab target on
           top of a click target. Only the link list scrolls now, and it keeps
           a gutter so the bar never touches the text either. */
        .docs-aside { position: sticky; top: 5rem; max-height: calc(100vh - 7rem); display: flex; flex-direction: column; }
        .docs-nav { min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding-right: .625rem;
                    scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
        .docs-nav::-webkit-scrollbar { width: 6px; }
        .docs-nav::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .docs-nav::-webkit-scrollbar-track { background: transparent; }
        .op { scroll-margin-top: 5.5rem; }
        /* Bordered, not just tinted. On a white card a pale fill alone barely
           reads as a chip, and the four methods have to be told apart at a
           glance while scanning a hundred rows. Border-box, so the outline does
           not change the width they all share. */
        .m { display: inline-flex; align-items: center; justify-content: center; min-width: 3.75rem; padding: .1rem .4rem;
             border-radius: .3rem; border: 1px solid; font-size: .625rem; font-weight: 700; letter-spacing: .04em;
             font-family: ui-monospace, monospace; box-sizing: border-box; }
        .m-GET { background: #ecfdf5; color: #047857; border-color: #6ee7b7; }
        .m-POST { background: #eff6ff; color: #1d4ed8; border-color: #93c5fd; }
        .m-PATCH, .m-PUT { background: #fffbeb; color: #b45309; border-color: #fcd34d; }
        .m-DELETE { background: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
        /* A path is one unbroken run and will set the width floor of the page
           if it is allowed to. Nothing here ever scrolls sideways. */
        .path { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; overflow-wrap: anywhere; }
        .nav-a { display: flex; align-items: center; gap: .5rem; padding: .25rem .5rem; border-radius: .375rem;
                 font-size: .8125rem; color: #475569; text-decoration: none; }
        .nav-a:hover { background: #f1f5f9; color: #0f172a; }
        .hide { display: none !important; }
    </style>
</head>
<body class="h-full text-slate-900">

<header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
        <div class="flex items-center gap-3 min-w-0">
            <span class="text-base font-semibold tracking-tight">{{ config('brand.name') }} API</span>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-500">v{{ $spec['info']['version'] }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
            <a href="{{ route('docs') }}" class="text-slate-600 hover:text-slate-900">Guide</a>
            <a href="{{ route('api.openapi') }}" class="text-slate-600 hover:text-slate-900">openapi.json</a>
            <a href="{{ url('/') }}" class="font-medium text-brand-700 hover:text-brand-800">Open The Panel</a>
        </div>
    </div>
</header>

<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
    <div class="docs">

        {{-- Index. Filtered in the browser by public/js/api-docs.js, which is
             an enhancement: with no JavaScript this is still a complete,
             working table of contents. --}}
        <aside class="docs-aside">
            <label class="sr-only" for="docs-filter">Filter endpoints</label>
            <input id="docs-filter" type="search" placeholder="Filter endpoints"
                   class="mb-3 w-full rounded-lg border-0 bg-white px-3 py-2 text-sm ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">

            <nav class="docs-nav space-y-4" aria-label="Endpoints">
                <a href="#start" class="nav-a font-medium text-slate-900">Getting Started</a>
                @foreach ($scopes as $scope => $resources)
                    <div data-nav-scope>
                        <p class="px-2 pb-1 text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $scope }}</p>
                        @foreach ($resources as $resource => $operations)
                            <div data-nav-group>
                                <a href="#{{ Str::slug($scope.'-'.$resource) }}" class="nav-a font-medium text-slate-700">{{ $resource }}</a>
                                <div class="ml-2 border-l border-slate-200 pl-2">
                                    @foreach ($operations as $op)
                                        <a href="#{{ $op['anchor'] }}" class="nav-a" data-nav-op
                                           data-search="{{ strtolower($op['method'].' '.$op['path'].' '.$op['summary']) }}">
                                            <span class="m m-{{ $op['method'] }}">{{ $op['method'] }}</span>
                                            <span class="truncate">{{ $op['summary'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </nav>
        </aside>

        <main class="min-w-0 space-y-10">

            <section id="start" class="op">
                <h1 class="text-3xl font-semibold tracking-tight">API Reference</h1>
                <div class="mt-3 space-y-3 text-slate-600">
                    @foreach (explode("\n\n", $spec['info']['description']) as $paragraph)
                        {{-- Backticks in the description are code spans, not
                             literal characters. Escaped first, so the text can
                             never inject markup even though it is ours. --}}
                        <p class="leading-relaxed">{!! preg_replace(
                            '/`([^`]+)`/',
                            '<code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-[0.85em] text-slate-800">$1</code>',
                            nl2br(e($paragraph)),
                        ) !!}</p>
                    @endforeach
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Base URL</p>
                        <p class="path mt-1 text-sm">{{ $baseUrl }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Authentication</p>
                        <p class="path mt-1 text-sm">Authorization: Bearer &lt;token&gt;</p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl bg-slate-900 p-4">
                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">A First Call</p>
                    <pre class="overflow-x-auto text-xs leading-relaxed text-slate-100"><code>curl -H "Authorization: Bearer $TOKEN" \
     -H "Accept: application/json" \
     {{ $baseUrl }}/api/client/servers</code></pre>
                </div>

                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">Request bodies are not described yet.</p>
                    <p class="mt-1">
                        Every endpoint below lists its path and query parameters, which are generated from the routes.
                        The JSON body a write endpoint accepts is not, so those fields still have to come from the panel
                        form that does the same job. That gap is real and worth closing; it is not an oversight nobody
                        noticed.
                    </p>
                </div>
            </section>

            @foreach ($scopes as $scope => $resources)
                <section data-scope>
                    <div class="mb-4 flex items-baseline gap-3 border-b border-slate-200 pb-2">
                        <h2 class="text-xl font-semibold capitalize tracking-tight">{{ $scope }}</h2>
                        <p class="text-sm text-slate-500">
                            {{ collect($spec['tags'])->firstWhere('name', $scope)['description'] ?? '' }}
                        </p>
                    </div>

                    <div class="space-y-8">
                        @foreach ($resources as $resource => $operations)
                            <div data-group>
                                <h3 id="{{ Str::slug($scope.'-'.$resource) }}" class="op mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">
                                    {{ $resource }}
                                </h3>

                                <div class="space-y-3">
                                    @foreach ($operations as $op)
                                        <article id="{{ $op['anchor'] }}" data-op
                                                 data-search="{{ strtolower($op['method'].' '.$op['path'].' '.$op['summary']) }}"
                                                 class="op overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-slate-100 px-4 py-3">
                                                <span class="m m-{{ $op['method'] }}">{{ $op['method'] }}</span>
                                                <span class="path min-w-0 text-sm text-slate-900">{{ $op['path'] }}</span>
                                                <a href="#{{ $op['anchor'] }}" class="ml-auto text-xs text-slate-400 hover:text-slate-700" aria-label="Link to this endpoint">#</a>
                                            </div>

                                            <div class="space-y-3 px-4 py-3">
                                                <p class="text-sm text-slate-700">{{ $op['summary'] }}</p>

                                                @if ($op['parameters'])
                                                    <div>
                                                        <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Parameters</p>
                                                        <dl class="divide-y divide-slate-100 rounded-lg bg-slate-50 ring-1 ring-inset ring-slate-200">
                                                            @foreach ($op['parameters'] as $parameter)
                                                                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 px-3 py-2">
                                                                    <dt class="path text-xs font-medium text-slate-900">{{ $parameter['name'] }}</dt>
                                                                    <span class="rounded bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-500 ring-1 ring-inset ring-slate-200">
                                                                        {{ $parameter['in'] }}{{ ($parameter['required'] ?? false) ? ', required' : '' }}
                                                                    </span>
                                                                    <dd class="min-w-0 flex-1 text-xs text-slate-500">{{ $parameter['description'] ?? '' }}</dd>
                                                                </div>
                                                            @endforeach
                                                        </dl>
                                                    </div>
                                                @endif

                                                <details class="group">
                                                    <summary class="cursor-pointer text-xs font-medium text-brand-700 hover:text-brand-800">Show a curl example</summary>
                                                    <div class="mt-2 rounded-lg bg-slate-900 p-3">
                                                        <pre class="overflow-x-auto text-xs leading-relaxed text-slate-100"><code>curl -X {{ $op['method'] }} \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
@if (in_array($op['method'], ['POST', 'PATCH', 'PUT'], true))  -H "Content-Type: application/json" \
  -d '{}' \
@endif  "{{ $baseUrl }}{{ $op['path'] }}"</code></pre>
                                                    </div>
                                                </details>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <p id="docs-empty" class="hide rounded-xl bg-white px-4 py-8 text-center text-sm text-slate-500 ring-1 ring-slate-200">
                Nothing matches that. Try a shorter term, or part of a path such as <span class="path">backups</span>.
            </p>

            <footer class="border-t border-slate-200 pt-6 text-xs text-slate-400">
                Generated from the routes of this install, so it describes this version and no other.
                The machine-readable source is <a href="{{ route('api.openapi') }}" class="underline hover:text-slate-600">openapi.json</a>.
            </footer>
        </main>
    </div>
</div>

<script src="{{ asset('js/api-docs.js') }}" defer></script>
</body>
</html>
