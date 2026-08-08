{{-- Public status page. Deliberately standalone: no panel chrome, no nav, and
     nothing that hints at the rest of the install. This is a link a community
     owner puts in their Discord. --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ route('favicon.svg') }}">
    <x-tailwind-cdn />
    <x-accent-style />
</head>
<body class="h-full bg-slate-50">
<div class="min-h-full flex flex-col">
    <div class="h-1 bg-gradient-to-r from-brand-600 via-brand-400 to-brand-600"></div>

    <main class="flex-1 flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-lg">
            <div class="bg-white rounded-2xl ring-1 ring-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-8 text-center border-b border-slate-100">
                    <span class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-brand-200">
                        <x-icon :name="$server->template?->game?->icon ?: 'controller'" class="w-7 h-7" />
                    </span>
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-900">{{ $page->headline ?: $server->name }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ $server->template?->game?->name }}</p>

                    <div class="mt-5 inline-flex items-center gap-2.5 rounded-full px-4 py-2 ring-1 ring-inset
                                {{ $server->power_state === 'running' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                        <span class="relative flex h-2 w-2">
                            @if ($server->power_state === 'running')
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            @endif
                            <span class="relative inline-flex h-2 w-2 rounded-full {{ $server->power_state === 'running' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                        </span>
                        <span class="text-sm font-medium">{{ $server->power_state === 'running' ? 'Online' : 'Offline' }}</span>
                    </div>
                </div>

                <dl class="divide-y divide-slate-100">
                    @if ($page->show_address)
                        <div class="px-6 py-4 flex items-center justify-between gap-4">
                            <dt class="text-sm text-slate-500">Connect To</dt>
                            <dd class="min-w-0 text-right">
                                @if ($server->connectAddress())
                                    <span class="block font-mono text-sm text-slate-900 truncate">{{ $server->connectAddress() }}</span>
                                    <span class="block font-mono text-xs text-slate-500 truncate">{{ $server->address() }}</span>
                                @else
                                    <span class="block font-mono text-sm text-slate-900 truncate">{{ $server->address() }}</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($page->show_players)
                        <div class="px-6 py-4 flex items-center justify-between gap-4">
                            <dt class="text-sm text-slate-500">Players</dt>
                            <dd class="text-sm font-medium text-slate-900 tabular">
                                {{ $server->cached_players }}@if ($server->cached_max_players) / {{ $server->cached_max_players }}@endif
                            </dd>
                        </div>
                        <div class="px-6 py-4 flex items-center justify-between gap-4">
                            <dt class="text-sm text-slate-500">Busiest Today</dt>
                            <dd class="text-sm font-medium text-slate-900 tabular">{{ (int) $peak }}</dd>
                        </div>
                    @endif
                    @if ($page->show_uptime && $server->last_started_at)
                        <div class="px-6 py-4 flex items-center justify-between gap-4">
                            <dt class="text-sm text-slate-500">Up Since</dt>
                            <dd class="text-sm text-slate-900">{{ $server->last_started_at->diffForHumans() }}</dd>
                        </div>
                    @endif
                    @if ($page->show_version)
                        <div class="px-6 py-4 flex items-center justify-between gap-4">
                            <dt class="text-sm text-slate-500">Running</dt>
                            <dd class="text-sm text-slate-900">{{ $server->template?->name }}</dd>
                        </div>
                    @endif
                    <div class="px-6 py-4 flex items-center justify-between gap-4">
                        <dt class="text-sm text-slate-500">Region</dt>
                        <dd class="text-sm text-slate-900">{{ $server->node?->location?->flag }} {{ $server->node?->location?->name }}</dd>
                    </div>
                </dl>
            </div>

            <p class="mt-4 text-center text-xs text-slate-400">
                Updated {{ $server->cached_at?->diffForHumans() ?? 'recently' }} &middot; Hosted with {{ config('brand.name') }}
            </p>
        </div>
    </main>
</div>
</body>
</html>
