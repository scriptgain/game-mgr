@props(['server'])
{{-- Fourteen destinations is too many for one row, and the previous answer,
     render them all and let JS fold the leftovers into a More menu, had two
     problems. It measured in JavaScript, so the answer changed when the webfont
     landed and a tab could appear and then be taken away. And "More" is not a
     category: it told you nothing about what was inside it.

     They are grouped by what somebody is trying to do instead. Three things an
     operator reaches for constantly stay as direct tabs; everything else lives
     under a heading that says what it is. Nothing is measured, so nothing can
     reflow, and a group carrying the current page is marked active so you can
     still see where you are.

     Anything the current user cannot reach is dropped before rendering, and a
     group left empty by that disappears with it. --}}
@php
    use Illuminate\Support\Facades\Gate;

    $can = fn (string $permission) => Gate::allows('check', [$server, $permission]);
    $template = $server->template;

    // [label, route, icon, allowed, active-pattern]
    $direct = array_values(array_filter([
        ['Console', 'server.console', 'terminal', $can('control.console'), 'server.console'],
        ['Files', 'server.files', 'folder', $can('file.read'), 'server.files*'],
        ['Players', 'server.players', 'user-group',
            $can('player.read') && (bool) ($template?->rcon_supported || $template?->query_protocol), 'server.players*'],
    ], fn ($t) => $t[3]));

    $groups = array_values(array_filter([
        ['Manage', 'cube', array_values(array_filter([
            ['Backups', 'server.backups', 'archive', $can('backup.read') && $server->backup_limit > 0, 'server.backups*'],
            ['Schedules', 'server.schedules', 'clock', $can('schedule.read'), 'server.schedules*'],
            ['Worlds', 'server.worlds', 'map', $can('world.read'), 'server.worlds*'],
            ['Mods', 'server.mods', 'puzzle', $can('mod.read') && (bool) $template?->supportsMods(), 'server.mods*'],
            ['Databases', 'server.databases', 'database', $can('database.read') && $server->database_limit > 0, 'server.databases*'],
        ], fn ($t) => $t[3]))],

        ['Configure', 'settings', array_values(array_filter([
            ['Startup', 'server.startup', 'bolt', $can('startup.read'), 'server.startup*'],
            ['Config', 'server.config', 'book', Route::has('server.config') && $can('startup.read'), 'server.config*'],
            ['Network', 'server.network', 'network', $can('allocation.read'), 'server.network*'],
            ['Settings', 'server.settings', 'settings', true, 'server.settings*'],
        ], fn ($t) => $t[3]))],

        ['Insights', 'chart', array_values(array_filter([
            ['Metrics', 'server.metrics', 'chart', $can('control.console'), 'server.metrics*'],
            ['Activity', 'server.activity', 'book', $can('activity.read'), 'server.activity*'],
            ['Users', 'server.users', 'users', $can('user.read'), 'server.users*'],
        ], fn ($t) => $t[3]))],
    ], fn ($g) => count($g[2]) > 0));
@endphp

<style>
    /* Plain CSS rather than utilities: these are toggled by state and a purged
       build has no way to know the classes were ever used. */
    .gm-tabs { display: flex; align-items: center; gap: .25rem; flex-wrap: wrap; min-width: 0; }
    .gm-tab { display: inline-flex; align-items: center; gap: .5rem; padding: .5rem .75rem; border-radius: .5rem;
              font-size: .875rem; font-weight: 500; color: #475569; white-space: nowrap; text-decoration: none;
              border: 1px solid transparent; transition: background .15s, color .15s, border-color .15s; cursor: pointer; }
    .gm-tab:hover { background: #f1f5f9; color: #0f172a; border-color: #e2e8f0; }
    .gm-tab.is-active { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; font-weight: 600; }
    .gm-tab svg { width: 1rem; height: 1rem; flex: 0 0 auto; }
    .gm-group { position: relative; }
    .gm-menu { position: absolute; left: 0; top: calc(100% + .4rem); z-index: 40; min-width: 13rem;
               background: #fff; border-radius: .625rem; border: 1px solid #e2e8f0;
               box-shadow: 0 12px 32px rgba(2, 6, 23, .14); padding: .25rem; }
    .gm-menu a { display: flex; align-items: center; gap: .625rem; padding: .5rem .625rem; border-radius: .375rem;
                 font-size: .875rem; color: #334155; text-decoration: none; white-space: nowrap; }
    .gm-menu a:hover { background: #f1f5f9; color: #0f172a; }
    .gm-menu a.is-active { background: #ede9fe; color: #5b21b6; font-weight: 600; }
    .gm-menu svg { width: 1rem; height: 1rem; flex: 0 0 auto; color: #94a3b8; }
    .gm-menu a.is-active svg { color: #7c3aed; }
</style>

<div class="bg-white rounded-xl ring-1 ring-slate-200 shadow-sm px-2 py-1.5 mb-6">
    <nav class="gm-tabs" aria-label="Server sections">
        @foreach ($direct as [$label, $route, $icon, $_, $pattern])
            <a href="{{ route($route, $server) }}" class="gm-tab {{ request()->routeIs($pattern) ? 'is-active' : '' }}">
                <x-icon :name="$icon" /> {{ $label }}
            </a>
        @endforeach

        @foreach ($groups as [$groupLabel, $groupIcon, $items])
            @php
                $groupActive = collect($items)->contains(fn ($i) => request()->routeIs($i[4]));
            @endphp
            <div class="gm-group" x-data="{ open: false }"
                 @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
                        class="gm-tab {{ $groupActive ? 'is-active' : '' }}">
                    <x-icon :name="$groupIcon" />
                    {{ $groupLabel }}
                    <x-icon name="chevron-down" class="transition-transform" ::class="open && 'rotate-180'" />
                </button>
                <div class="gm-menu" x-show="open" x-cloak x-transition @click="open = false">
                    @foreach ($items as [$label, $route, $icon, $__, $pattern])
                        <a href="{{ route($route, $server) }}" class="{{ request()->routeIs($pattern) ? 'is-active' : '' }}">
                            <x-icon :name="$icon" /> {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</div>
