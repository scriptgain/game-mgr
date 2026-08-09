{{-- maxWidth comes from config('gamemgr.max_width'), never a literal. Pages
     used to set their own and the container width jumped when you clicked
     between the dashboard and the server list. Change the width in one place:
     config/gamemgr.php. --}}
@props(['title' => null, 'maxWidth' => null])
@php $maxWidth = $maxWidth ?: config('gamemgr.max_width', 'max-w-7xl'); @endphp
@php
    $u = auth()->user();
    $isAdmin = $u?->isAdmin() ?? false;
    $openAlerts = $isAdmin ? \App\Models\Alert::whereNull('acknowledged_at')->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' | ' . config('brand.name') : config('brand.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ route('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ route('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ route('favicon.apple') }}">
    {{-- A PLAIN stylesheet, deliberately, and deliberately first.

         app.css is inlined further down as <style type="text/tailwindcss">,
         which the browser does not apply as CSS: the Tailwind browser compiler
         has to download, parse and inject it. Until that finishes there is no
         [x-cloak] rule at all, so every cloaked element renders visible and
         then vanishes when Alpine boots. On a server page that is sixteen
         elements, including the whole overflow menu, which is why tabs
         appeared and disappeared on every page load.

         Kept minimal on purpose: only the rules that must apply before any
         script has run belong here. --}}
    <style>
        [x-cloak] { display: none !important; }
    </style>
    {{-- JS that registers Alpine components must load BEFORE x-tailwind-cdn,
         which is what loads Alpine. A file loaded after it attaches its
         alpine:init listener once the event has already fired, and everything
         it registers silently never exists. --}}
    {{-- The filemtime is not decoration. This file is served with an ETag and
         no Cache-Control, so a browser is free to reuse its copy without ever
         revalidating, and a deploy that changes the behaviour changes nothing
         the browser can see. A stale copy of this file is what kept the tab
         strip flashing on a panel that had already been fixed. The URL now
         changes whenever the file does. --}}
    <script defer src="{{ asset('js/gamemgr.js') }}?v={{ \App\Support\Asset::version('js/gamemgr.js') }}"></script>
    <x-tailwind-cdn />
    <x-accent-style />
</head>
<body class="h-full min-h-full bg-slate-50">
<x-demo-banner />
<div class="min-h-full flex flex-col">

    {{-- Brand accent hairline --}}
    <div class="h-0.5 bg-gradient-to-r from-brand-600 via-brand-400 to-brand-600"></div>

    {{-- Dark top utility bar (house style) --}}
    <div class="bg-chrome text-slate-300 text-sm ring-1 ring-inset ring-white/5">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-12 items-center justify-between gap-4">
                <x-brand class="text-white" />
                <div class="flex items-center gap-2 sm:gap-3">
                    @if ($isAdmin)
                        <a href="{{ route('admin.alerts.index') }}" title="Alerts"
                           class="hidden sm:inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition
                                  {{ $openAlerts > 0 ? 'bg-amber-400/10 text-amber-200 ring-amber-400/25 hover:bg-amber-400/20' : 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/20 hover:bg-emerald-400/20' }}">
                            @if ($openAlerts > 0)
                                <x-icon name="warning" class="w-3.5 h-3.5" /> {{ $openAlerts }} Open {{ \Illuminate\Support\Str::plural('Alert', $openAlerts) }}
                            @else
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                </span>
                                All Clear
                            @endif
                        </a>
                    @endif
                    <a href="{{ route('docs') }}" target="_blank" rel="noopener" title="Documentation"
                       class="hidden md:inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition">
                        <x-icon name="book" class="w-4 h-4" />
                    </a>
                    <span class="hidden sm:inline-block h-5 w-px bg-white/10"></span>
                    <x-dropdown align="right">
                        <x-slot:trigger>
                            <button class="inline-flex items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-white/10 transition">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-brand-500/20 text-brand-200 text-xs font-semibold ring-1 ring-brand-400/40">{{ $u?->initials() ?? 'GM' }}</span>
                                <span class="hidden sm:block text-xs font-medium text-slate-200 max-w-[8rem] truncate">{{ \Illuminate\Support\Str::of($u?->name ?? 'Account')->explode(' ')->first() }}</span>
                                <x-icon name="chevron-down" class="w-4 h-4 text-slate-400" />
                            </button>
                        </x-slot:trigger>
                        @if ($u)
                            <div class="px-3 py-2.5 border-b border-slate-100">
                                <p class="text-sm font-medium text-slate-900 truncate">{{ $u->name }}</p>
                                <p class="text-xs text-slate-500 truncate">{{ $u->email }}</p>
                                <span class="mt-1.5 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset
                                             {{ $isAdmin ? 'bg-brand-50 text-brand-700 ring-brand-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                    {{ $isAdmin ? ($u->isRootAdmin() ? 'Root Admin' : 'Admin') : 'Client' }}
                                </span>
                            </div>
                        @endif
                        <x-dropdown-item icon="user-group" href="{{ route('account.index') }}">My Account</x-dropdown-item>
                        <x-dropdown-item icon="key" href="{{ route('account.api.index') }}">API Credentials</x-dropdown-item>
                        @if ($isAdmin)
                            <div class="my-1 border-t border-slate-100"></div>
                            <x-dropdown-item icon="settings" href="{{ route('settings.general.edit') }}">Panel Settings</x-dropdown-item>
                            <x-dropdown-item icon="users" href="{{ route('admin.users.index') }}">Users</x-dropdown-item>
                            <x-dropdown-item icon="book" href="{{ route('settings.audit.index') }}">Audit Log</x-dropdown-item>
                        @endif
                        <div class="my-1 border-t border-slate-100"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-left text-rose-600 hover:bg-rose-50">
                                <x-icon name="x-circle" class="w-4 h-4 shrink-0" /> Sign Out
                            </button>
                        </form>
                    </x-dropdown>
                </div>
            </div>
        </div>
    </div>

    {{-- Main navbar. Clients see a deliberately short menu: their servers and
         nothing else. The admin sections do not merely 403, they are not there. --}}
    @php
        if ($isAdmin) {
            $nav = [
                ['type' => 'link', 'label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'dashboard',
                    'active' => request()->routeIs('dashboard')],
                ['type' => 'link', 'label' => 'Servers', 'href' => route('admin.servers.index'), 'icon' => 'server',
                    'active' => request()->routeIs('admin.servers.*', 'servers.*', 'server.*')],
                ['type' => 'group', 'label' => 'Infrastructure', 'icon' => 'cloud',
                    'active' => request()->routeIs('admin.locations.*', 'admin.nodes.*', 'admin.mounts.*', 'admin.database-hosts.*'),
                    'items' => [
                        ['Nodes', route('admin.nodes.index'), 'server', request()->routeIs('admin.nodes.*')],
                        ['Locations', route('admin.locations.index'), 'globe', request()->routeIs('admin.locations.*')],
                        ['Database Hosts', route('admin.database-hosts.index'), 'database', request()->routeIs('admin.database-hosts.*')],
                        ['Mounts', route('admin.mounts.index'), 'folder', request()->routeIs('admin.mounts.*')],
                    ]],
                ['type' => 'group', 'label' => 'Catalogue', 'icon' => 'cube',
                    'active' => request()->routeIs('admin.games.*', 'admin.templates.*', 'admin.blueprints.*'),
                    'items' => [
                        ['Games', route('admin.games.index'), 'controller', request()->routeIs('admin.games.*')],
                        // Excludes import explicitly: 'admin.templates.*' matches
                        // 'admin.templates.import' too, so both entries lit up at
                        // once and neither told you where you were.
                        ['Templates', route('admin.templates.index'), 'cube',
                            request()->routeIs('admin.templates.*') && ! request()->routeIs('admin.templates.import')],
                        ['Import Template', route('admin.templates.import'), 'download', request()->routeIs('admin.templates.import')],
                        ['Blueprints', route('admin.blueprints.index'), 'copy', request()->routeIs('admin.blueprints.*')],
                    ]],
                ['type' => 'group', 'label' => 'Operations', 'icon' => 'bolt',
                    'active' => request()->routeIs('admin.alerts.*', 'admin.watchdog.*', 'admin.channels.*', 'admin.webhooks.*'),
                    'items' => [
                        ['Alerts', route('admin.alerts.index'), 'warning', request()->routeIs('admin.alerts.*')],
                        ['Watchdog Rules', route('admin.watchdog.index'), 'shield', request()->routeIs('admin.watchdog.*')],
                        ['Notification Channels', route('admin.channels.index'), 'bell', request()->routeIs('admin.channels.*')],
                        ['Webhooks', route('admin.webhooks.index'), 'link', request()->routeIs('admin.webhooks.*')],
                    ]],
            ];
        } else {
            $nav = [
                ['type' => 'link', 'label' => 'My Servers', 'href' => route('dashboard'), 'icon' => 'server',
                    'active' => request()->routeIs('dashboard', 'server.*')],
                ['type' => 'link', 'label' => 'Account', 'href' => route('account.index'), 'icon' => 'user-group',
                    'active' => request()->routeIs('account.*')],
            ];
        }

    @endphp
    <header x-data="{ mobileOpen: false }" class="bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80 border-b border-slate-200 sticky top-0 z-30">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-14 items-center justify-between gap-3">
                <div class="flex items-center gap-1 min-w-0">
                    <button type="button" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen.toString()" aria-label="Toggle menu"
                        class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-600 hover:bg-slate-100 transition shrink-0">
                        <svg x-show="!mobileOpen" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                        <svg x-show="mobileOpen" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                    <nav class="hidden lg:flex items-center gap-1">
                        @foreach ($nav as $item)
                            @if ($item['type'] === 'link')
                                <x-nav-link :href="$item['href']" :icon="$item['icon']" :active="$item['active']">{{ $item['label'] }}</x-nav-link>
                            @else
                                @php $gActive = $item['active']; @endphp
                                <div x-data="{ open: false }" class="relative" @click.outside="open = false" @keydown.escape="open = false">
                                    <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                                        @class([
                                            'inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition ring-1 ring-inset',
                                            'text-brand-700 bg-brand-50 ring-brand-200' => $gActive,
                                            'text-slate-600 ring-transparent hover:text-slate-900 hover:bg-slate-100 hover:ring-slate-200' => ! $gActive,
                                        ])>
                                        <x-icon :name="$item['icon']" class="w-4 h-4 shrink-0" />
                                        {{ $item['label'] }}
                                        <x-icon name="chevron-down" class="w-4 h-4 -mr-0.5 text-slate-400 transition-transform" ::class="open && 'rotate-180'" />
                                    </button>
                                    <div x-show="open" x-cloak x-transition
                                         class="absolute left-0 z-40 mt-2 w-60 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-slate-200 py-1"
                                         @click="open = false">
                                        @foreach ($item['items'] as [$label, $href, $icon, $active])
                                            <a href="{{ $href }}" @class([
                                                'flex items-center gap-2.5 px-3 py-2 text-sm transition',
                                                'text-brand-700 bg-brand-50 font-medium' => $active,
                                                'text-slate-700 hover:bg-slate-100' => ! $active,
                                            ])>
                                                <x-icon :name="$icon" class="w-4 h-4 shrink-0 {{ $active ? 'text-brand-600' : 'text-slate-400' }}" /> {{ $label }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </nav>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if ($isAdmin)
                        <x-create-menu />
                    @endif
                </div>
            </div>
        </div>
        {{-- Mobile slide-down menu --}}
        <div x-show="mobileOpen" x-cloak
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
             class="lg:hidden border-t border-slate-100 bg-white shadow-sm">
            <nav class="{{ $maxWidth }} mx-auto px-4 sm:px-6 py-3 space-y-3">
                @foreach ($nav as $item)
                    @if ($item['type'] === 'link')
                        <a href="{{ $item['href'] }}" @class([
                            'flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition',
                            'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200' => $item['active'],
                            'text-slate-600 hover:bg-slate-100' => ! $item['active'],
                        ])>
                            <x-icon :name="$item['icon']" class="w-4 h-4 shrink-0" /> {{ $item['label'] }}
                        </a>
                    @else
                        <div>
                            <p class="px-3 pb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $item['label'] }}</p>
                            <div class="grid grid-cols-2 gap-1.5">
                                @foreach ($item['items'] as [$label, $href, $icon, $active])
                                    <a href="{{ $href }}" @class([
                                        'flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition',
                                        'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-200' => $active,
                                        'text-slate-600 hover:bg-slate-100' => ! $active,
                                    ])>
                                        <x-icon :name="$icon" class="w-4 h-4 shrink-0" /> {{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </nav>
        </div>
    </header>

    <x-impersonation-banner />

    {{-- Page content --}}
    <main class="flex-1 py-8">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8">
            <x-update-banner />
            <x-telemetry-banner />
            @if (session('status'))
                <div class="mb-6"><x-alert type="success">{{ session('status') }}</x-alert></div>
            @endif
            @if (session('warning'))
                <div class="mb-6"><x-alert type="warn">{{ session('warning') }}</x-alert></div>
            @endif
            @if (session('error'))
                <div class="mb-6"><x-alert type="danger">{{ session('error') }}</x-alert></div>
            @endif
            {{-- Settings is the one area with its own left column, matching the
                 rest of the fleet. Everything else renders at the full
                 container width so no two pages disagree about how wide the
                 panel is. --}}
            @if (request()->routeIs('settings.*'))
                <div class="settings-shell">
                    <aside class="settings-aside"><x-settings-tabs /></aside>
                    <div>{{ $slot }}</div>
                </div>
            @else
                {{ $slot }}
            @endif
        </div>
    </main>

    {{-- Footer --}}
    <footer class="border-t border-slate-200 bg-white">
        <div class="{{ $maxWidth }} mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
            <span>{{ config('brand.name') }} &middot; {{ config('brand.tagline') }}</span>
            <span class="tabular">v{{ \App\Services\UpdateService::currentVersion() }} &middot; Docker, SteamCMD and LinuxGSM</span>
        </div>
    </footer>

</div>

{{-- Global tooltip: a single fixed-position element on <body> that reads
     [data-tip]. Fixed positioning means no ancestor's overflow can ever clip
     it (unlike a CSS ::after tip). Supports multi-line tips (newlines in the
     attribute render as line breaks). --}}
<style>
    .vx-tip{position:fixed;z-index:9999;max-width:22rem;padding:.5rem .625rem;border-radius:.5rem;background:#0f172a;color:#f8fafc;font-size:.75rem;line-height:1.2rem;white-space:pre-line;box-shadow:0 8px 24px rgba(2,6,23,.22);pointer-events:none;opacity:0;transition:opacity .12s ease;display:none}
    .vx-tip strong{color:#fff}
    /* Integrated thin scrollbar for scroll areas (matches the UI, not the OS chrome). */
    .vx-scroll{scrollbar-width:thin;scrollbar-color:#cbd5e1 transparent}
    .vx-scroll::-webkit-scrollbar{width:9px;height:9px}
    .vx-scroll::-webkit-scrollbar-track{background:transparent}
    .vx-scroll::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:9999px;border:2px solid transparent;background-clip:content-box}
    .vx-scroll::-webkit-scrollbar-thumb:hover{background:#94a3b8;background-clip:content-box}
    .vx-scroll::-webkit-scrollbar-corner{background:transparent}
    /* Dialog bodies and file panes must NEVER scroll sideways. Long paths,
       snapshot ids and shell one-liners wrap; anything genuinely un-wrappable
       (a wide table) scrolls inside its own .vx-x-scroll box instead. Setting
       overflow-y alone would silently make overflow-x scroll too. */
    /* white-space is inherited, so a dialog opened from inside a table cell
       (.vx-table sets nowrap) used to render its body as one un-readable line
       that overflow-x:hidden then clipped. Reset it here rather than at every
       call site: a wrapping body is the standard, never a horizontal scroll. */
    .vx-wrap{overflow-wrap:anywhere;white-space:normal}
    .vx-modal,.vx-modal h3,.vx-modal p,.vx-modal span,.vx-modal div{white-space:normal}
    .vx-wrap pre,.vx-wrap code{white-space:pre-wrap;overflow-wrap:anywhere}
    .vx-wrap table{width:100%;table-layout:fixed}
    .vx-wrap .vx-x-scroll{overflow-x:auto;max-width:100%}
    /* Inputs carry a ~20ch intrinsic width, which is what pushes two-column
       forms wider than the dialog on narrow viewports. */
    .vx-wrap input,.vx-wrap select,.vx-wrap textarea{min-width:0;max-width:100%}
    .vx-wrap .grid{min-width:0}
</style>
<script>
    (function () {
        var tip;
        function ensure() {
            if (!tip) { tip = document.createElement('div'); tip.className = 'vx-tip'; document.body.appendChild(tip); }
            return tip;
        }
        function show(el) {
            var t = el.getAttribute('data-tip');
            if (!t) return;
            var n = ensure();
            n.textContent = t;
            n.style.display = 'block';
            n.style.opacity = '0';
            var r = el.getBoundingClientRect(), tr = n.getBoundingClientRect();
            var left = Math.max(8, Math.min(r.left + r.width / 2 - tr.width / 2, window.innerWidth - tr.width - 8));
            var top = r.top - tr.height - 8;
            if (top < 8) top = r.bottom + 8; // flip below when there's no room above
            n.style.left = left + 'px';
            n.style.top = top + 'px';
            n.style.opacity = '1';
        }
        function hide() { if (tip) { tip.style.opacity = '0'; tip.style.display = 'none'; } }
        document.addEventListener('mouseover', function (e) { var el = e.target.closest('[data-tip]'); if (el) show(el); });
        document.addEventListener('mouseout', function (e) { var el = e.target.closest('[data-tip]'); if (el) hide(); });
        document.addEventListener('scroll', hide, true);
        window.addEventListener('resize', hide);
    })();
</script>
</body>
</html>
