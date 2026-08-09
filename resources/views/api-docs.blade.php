{{-- The API reference.

     DESIGN NOTE, because the choices here are deliberate rather than the usual
     docs-site defaults.

     The people reading this run game servers. They live at a console: tmux,
     logs, ports, a status dot that is green or it is not. So the navigation IS
     a console pane, the same #05070f the panel uses for its own server console,
     and the filter is a shell prompt rather than a search box with a magnifying
     glass. Monospace is the DISPLAY face here, not the body face, which inverts
     the usual serif-headline habit and happens to be the native typeface of the
     subject. The reading column stays light and quiet: one bold move, made once.

     No webfonts. A panel like this runs on private networks and behind
     firewalls, and a heading that depends on fonts.googleapis.com is a heading
     that does not render on half the installs that matter.

     Everything is rendered from the OpenAPI document, so the page cannot
     describe an endpoint that does not exist or miss one that does. --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $spec['info']['title'] }} Reference</title>
    <meta name="description" content="Every endpoint in the {{ config('brand.name') }} API, generated from the routes themselves.">
    <link rel="icon" type="image/svg+xml" href="{{ route('favicon.svg') }}">
    <x-tailwind-cdn />
    <x-accent-style />
    <style>
        :root {
            --rail: 16.5rem;
            --console: #05070f;
            --line: rgba(255, 255, 255, .09);
            --mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
        }
        html { scroll-behavior: smooth; }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
        body { background: #f8fafc; }

        /* ------------------------------------------------------------ shell */
        .rail {
            position: fixed; inset: 0 auto 0 0; width: var(--rail); z-index: 30;
            background: var(--console); color: #cbd5e1;
            display: flex; flex-direction: column; border-right: 1px solid var(--line);
        }
        .body { margin-left: var(--rail); }
        @media (max-width: 1024px) {
            .rail { position: static; width: auto; inset: auto; }
            .body { margin-left: 0; }
            .rail-list { max-height: 20rem; }
        }

        /* The prompt. It IS the filter: the caret belongs to the input and the
           $ is its label, so nothing here is a decorative fake control. */
        .prompt { display: flex; align-items: center; gap: .5rem; padding: .5rem .7rem;
                  border: 1px solid var(--line); border-radius: .5rem; background: rgba(255,255,255,.03); }
        .prompt:focus-within { border-color: color-mix(in srgb, var(--color-brand-500), transparent 40%);
                               background: rgba(255,255,255,.05); }
        .prompt span { font-family: var(--mono); font-size: .8125rem; color: var(--color-brand-400); }
        .prompt input { flex: 1; min-width: 0; background: none; border: 0; outline: none; padding: 0;
                        font-family: var(--mono); font-size: .8125rem; color: #e2e8f0; }
        .prompt input::placeholder { color: #475569; }

        .rail-list { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain;
                     padding: .25rem .5rem 1.5rem; scrollbar-width: thin; scrollbar-color: #1e293b transparent; }
        .rail-list::-webkit-scrollbar { width: 6px; }
        .rail-list::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 3px; }
        .rail-list::-webkit-scrollbar-track { background: transparent; }

        /* Scope headings read as markers in a log, not as UI labels. */
        .scope-h { font-family: var(--mono); font-size: .625rem; letter-spacing: .18em; text-transform: uppercase;
                   color: #475569; padding: 1rem .55rem .35rem; }

        .res { display: flex; align-items: center; gap: .55rem; width: 100%; padding: .3rem .55rem;
               border-radius: .4rem; border-left: 2px solid transparent; color: #94a3b8; text-decoration: none;
               font-size: .8125rem; }
        .res:hover { background: rgba(255,255,255,.05); color: #f1f5f9; }
        .res svg { width: .9rem; height: .9rem; flex: 0 0 auto; opacity: .75; }
        .res .n { margin-left: auto; font-family: var(--mono); font-size: .625rem; color: #475569; }
        /* Where the reader is. Set by the scrollspy in api-docs.js; with no
           JavaScript nothing lights up and the list still works. */
        .res.on { background: rgba(255,255,255,.06); color: #fff; border-left-color: var(--color-brand-500); }
        .res.on svg { opacity: 1; color: var(--color-brand-400); }
        .res.on .n { color: #94a3b8; }

        .ops { margin: .1rem 0 .35rem 1.35rem; padding-left: .55rem; border-left: 1px solid rgba(255,255,255,.07); }
        .op-a { display: flex; align-items: center; gap: .45rem; padding: .17rem .4rem; border-radius: .3rem;
                color: #64748b; text-decoration: none; font-size: .75rem; }
        .op-a:hover { background: rgba(255,255,255,.05); color: #e2e8f0; }
        .op-a .t { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* ------------------------------------------------------- method tags */
        .m { display: inline-flex; align-items: center; justify-content: center; min-width: 3.4rem;
             padding: .08rem .35rem; border-radius: .25rem; border: 1px solid; box-sizing: border-box;
             font-family: var(--mono); font-size: .5625rem; font-weight: 700; letter-spacing: .06em; }
        /* Luminous on the rail, tinted on paper. Same semantics, two
           environments, so neither is a compromise for the other. */
        .rail .m { background: transparent; }
        .rail .m-GET { color: #34d399; border-color: rgba(52,211,153,.4); }
        .rail .m-POST { color: #60a5fa; border-color: rgba(96,165,250,.4); }
        .rail .m-PATCH, .rail .m-PUT { color: #fbbf24; border-color: rgba(251,191,36,.4); }
        .rail .m-DELETE { color: #f87171; border-color: rgba(248,113,113,.4); }
        .paper .m { font-size: .625rem; min-width: 3.75rem; padding: .1rem .4rem; }
        .paper .m-GET { background: #ecfdf5; color: #047857; border-color: #6ee7b7; }
        .paper .m-POST { background: #eff6ff; color: #1d4ed8; border-color: #93c5fd; }
        .paper .m-PATCH, .paper .m-PUT { background: #fffbeb; color: #b45309; border-color: #fcd34d; }
        .paper .m-DELETE { background: #fef2f2; color: #b91c1c; border-color: #fca5a5; }

        /* ----------------------------------------------------------- content */
        /* Monospace display: the subject's own typeface, used for headings and
           nowhere else, so it reads as a voice rather than as code. */
        .disp { font-family: var(--mono); letter-spacing: -.02em; }
        .eyebrow { font-family: var(--mono); font-size: .6875rem; letter-spacing: .16em; text-transform: uppercase; color: #64748b; }
        .op { scroll-margin-top: 1.5rem; }
        .path { font-family: var(--mono); overflow-wrap: anywhere; }
        .hide { display: none !important; }
        .card { background: #fff; border-radius: .75rem; box-shadow: 0 1px 2px rgba(15,23,42,.04); outline: 1px solid #e2e8f0; }
        .card:hover { outline-color: #cbd5e1; }
        .anchor { opacity: 0; transition: opacity .12s; }
        .card:hover .anchor, .anchor:focus { opacity: 1; }
        @media (prefers-reduced-motion: reduce) { .anchor { transition: none; } }
    </style>
</head>
<body class="h-full text-slate-900">

<aside class="rail">
    <div class="border-b px-4 py-4" style="border-color: var(--line)">
        <a href="{{ url('/') }}" class="flex items-baseline gap-2 no-underline">
            <span class="disp text-sm font-bold text-white">{{ config('brand.name') }}</span>
            <span class="disp text-[.625rem] text-slate-500">v{{ $spec['info']['version'] }}</span>
        </a>
        <p class="eyebrow mt-0.5" style="color:#475569">API Reference</p>
    </div>

    <div class="px-4 py-3">
        {{-- The signature. A filter, dressed as the thing these people already
             spend their day in. Focus, typing and Escape all behave normally. --}}
        <label class="prompt" for="docs-filter">
            <span aria-hidden="true">$</span>
            <input id="docs-filter" type="search" autocomplete="off" spellcheck="false" placeholder="filter endpoints">
        </label>
        <p class="mt-2 text-[.6875rem] leading-snug text-slate-600">
            <span class="disp text-slate-500">{{ $total }}</span> endpoints, generated from the routes of this install.
        </p>
    </div>

    <nav class="rail-list" aria-label="Endpoints">
        <a href="#start" class="res" data-spy-link="start">
            <x-icon name="book" />
            <span>Getting Started</span>
        </a>

        @foreach ($scopes as $scope => $resources)
            <div data-nav-scope>
                <p class="scope-h">{{ $scope }}</p>
                @foreach ($resources as $resource => $operations)
                    <div data-nav-group>
                        <a href="#{{ Str::slug($scope.'-'.$resource) }}" class="res"
                           data-spy-link="{{ Str::slug($scope.'-'.$resource) }}">
                            <x-icon :name="\App\Http\Controllers\ApiDocsController::iconFor($resource)" />
                            <span class="truncate">{{ $resource }}</span>
                            <span class="n">{{ count($operations) }}</span>
                        </a>
                        <div class="ops">
                            @foreach ($operations as $op)
                                <a href="#{{ $op['anchor'] }}" class="op-a" data-nav-op
                                   data-search="{{ strtolower($op['method'].' '.$op['path'].' '.$op['summary']) }}">
                                    <span class="m m-{{ $op['method'] }}">{{ $op['method'] }}</span>
                                    <span class="t">{{ $op['summary'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </nav>
</aside>

<div class="body paper">
    <div class="mx-auto max-w-4xl px-5 py-12 sm:px-8">

        <section id="start" data-spy="start" class="op">
            <p class="eyebrow">{{ config('brand.name') }} REST API</p>
            <h1 class="disp mt-2 text-4xl font-bold tracking-tight text-slate-900">Two scopes,<br>one bearer token.</h1>

            <div class="mt-5 space-y-3 text-[.9375rem] leading-relaxed text-slate-600">
                @foreach (explode("\n\n", $spec['info']['description']) as $paragraph)
                    {{-- Backticks in the description are code spans, not literal
                         characters. Escaped first, so the text can never inject
                         markup even though it is ours. --}}
                    <p>{!! preg_replace(
                        '/`([^`]+)`/',
                        '<code class="rounded bg-slate-100 px-1 py-0.5 font-mono text-[0.85em] text-slate-800">$1</code>',
                        nl2br(e($paragraph)),
                    ) !!}</p>
                @endforeach
            </div>

            <div class="mt-7 overflow-hidden rounded-xl" style="background: var(--console)">
                <div class="flex items-center gap-2 border-b px-4 py-2" style="border-color: var(--line)">
                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                    <p class="eyebrow" style="color:#475569">Your first call</p>
                </div>
                <pre class="overflow-x-auto px-4 py-3 text-[.8125rem] leading-relaxed text-slate-200" style="font-family: var(--mono)"><code><span class="text-slate-500">$</span> curl -H <span class="text-emerald-300">"Authorization: Bearer $TOKEN"</span> \
       -H <span class="text-emerald-300">"Accept: application/json"</span> \
       {{ $baseUrl }}/api/client/servers</code></pre>
            </div>

            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="card px-4 py-3">
                    <dt class="eyebrow">Base URL</dt>
                    <dd class="path mt-1 text-sm text-slate-900">{{ $baseUrl }}</dd>
                </div>
                <div class="card px-4 py-3">
                    <dt class="eyebrow">Auth header</dt>
                    <dd class="path mt-1 text-sm text-slate-900">Authorization: Bearer &lt;token&gt;</dd>
                </div>
            </dl>

            <div class="mt-4 rounded-xl border-l-2 border-amber-400 bg-amber-50/70 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Request bodies are not described yet.</p>
                <p class="mt-1 leading-relaxed">
                    Paths and query parameters below are generated from the routes. The JSON body a write endpoint
                    accepts is not, so those fields still have to come from the panel form that does the same job.
                    A real gap, and not one nobody noticed.
                </p>
            </div>
        </section>

        @foreach ($scopes as $scope => $resources)
            <section data-scope class="mt-14">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <h2 class="disp text-2xl font-bold capitalize tracking-tight">{{ $scope }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ collect($spec['tags'])->firstWhere('name', $scope)['description'] ?? '' }}
                    </p>
                </div>
                <div class="mt-1 h-px bg-gradient-to-r from-slate-300 to-transparent"></div>

                @foreach ($resources as $resource => $operations)
                    <div data-group class="mt-8">
                        <h3 id="{{ Str::slug($scope.'-'.$resource) }}" data-spy="{{ Str::slug($scope.'-'.$resource) }}"
                            class="op mb-3 flex items-center gap-2 text-slate-900">
                            <x-icon :name="\App\Http\Controllers\ApiDocsController::iconFor($resource)" class="h-4 w-4 text-slate-400" />
                            <span class="disp text-base font-bold tracking-tight">{{ $resource }}</span>
                            <span class="eyebrow" style="letter-spacing:.1em">{{ count($operations) }}</span>
                        </h3>

                        <div class="space-y-2.5">
                            @foreach ($operations as $op)
                                <article id="{{ $op['anchor'] }}" data-op
                                         data-search="{{ strtolower($op['method'].' '.$op['path'].' '.$op['summary']) }}"
                                         class="op card overflow-hidden">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3">
                                        <span class="m m-{{ $op['method'] }}">{{ $op['method'] }}</span>
                                        <span class="path min-w-0 text-[.8125rem] text-slate-900">{{ $op['path'] }}</span>
                                        <a href="#{{ $op['anchor'] }}" class="anchor ml-auto text-xs text-slate-400 hover:text-slate-700"
                                           aria-label="Link to this endpoint">#</a>
                                    </div>

                                    <div class="space-y-3 border-t border-slate-100 px-4 py-3">
                                        <p class="text-sm text-slate-700">{{ $op['summary'] }}</p>

                                        @if ($op['parameters'])
                                            <dl class="overflow-hidden rounded-lg bg-slate-50 outline outline-1 outline-slate-200">
                                                @foreach ($op['parameters'] as $parameter)
                                                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 border-b border-slate-200/70 px-3 py-2 last:border-0">
                                                        <dt class="path text-xs font-semibold text-slate-900">{{ $parameter['name'] }}</dt>
                                                        <span class="rounded bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-500 outline outline-1 outline-slate-200">
                                                            {{ $parameter['in'] }}{{ ($parameter['required'] ?? false) ? ', required' : '' }}
                                                        </span>
                                                        <dd class="min-w-0 flex-1 text-xs text-slate-500">{{ $parameter['description'] ?? '' }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @endif

                                        <details>
                                            <summary class="cursor-pointer text-xs font-medium text-brand-700 hover:text-brand-800">curl</summary>
                                            <div class="mt-2 overflow-hidden rounded-lg" style="background: var(--console)">
                                                <pre class="overflow-x-auto px-3 py-2.5 text-xs leading-relaxed text-slate-200" style="font-family: var(--mono)"><code>curl -X {{ $op['method'] }} \
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
            </section>
        @endforeach

        <p id="docs-empty" class="hide card mt-8 px-4 py-10 text-center text-sm text-slate-500">
            Nothing matches that. Try part of a path, such as <span class="path text-slate-700">backups</span>.
        </p>

        <footer class="mt-14 border-t border-slate-200 pt-6 text-xs text-slate-400">
            Generated from the routes of this install, so it describes this version and no other.
            The machine-readable source is
            <a href="{{ route('api.openapi') }}" class="underline hover:text-slate-600">openapi.json</a>,
            and the concepts behind it are in the <a href="{{ route('docs') }}" class="underline hover:text-slate-600">guide</a>.
        </footer>
    </div>
</div>

<script src="{{ asset('js/api-docs.js') }}" defer></script>
</body>
</html>
