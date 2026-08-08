{{-- The one "make something" entry point in the header.

     It used to be a single New Server button, which was honest about the most
     common act and silent about the twelve other things an operator creates.
     Everything creatable now lives here, grouped by what it is for, so nobody
     has to remember which section of the nav hides Mounts.

     Width is clamped against the viewport rather than fixed. A fixed wide panel
     is the classic way a header menu forces the whole page to scroll sideways
     on a phone, and nothing in this panel scrolls sideways. --}}
@php
    $groups = [
        [
            'label' => 'Servers And Games',
            'items' => [
                ['Server', 'admin.servers.create', 'server', 'A game server on one of your nodes'],
                ['Template', 'admin.templates.create', 'cube', 'How a game installs and starts'],
                ['Import An Egg', 'admin.templates.import', 'download', 'Bring in a Pterodactyl egg definition'],
                ['Game', 'admin.games.create', 'controller', 'A title to group templates under'],
                ['Blueprint', 'admin.blueprints.create', 'sparkles', 'A named size operators can pick'],
            ],
        ],
        [
            'label' => 'Infrastructure',
            'items' => [
                ['Node', 'admin.nodes.create', 'cpu', 'A machine that runs game servers'],
                ['Location', 'admin.locations.create', 'map', 'A region to group nodes under'],
                ['Database Host', 'admin.database-hosts.create', 'database', 'Where player databases are created'],
                ['Mount', 'admin.mounts.create', 'folder', 'Share a directory into servers'],
            ],
        ],
        [
            'label' => 'People And Automation',
            'items' => [
                ['User', 'admin.users.create', 'users', 'An account that can own servers'],
                ['Watchdog Rule', 'admin.watchdog.create', 'shield', 'Act on a crash, or a log line'],
                ['Notification Channel', 'admin.channels.create', 'bell', 'Where alerts are delivered'],
                ['Webhook', 'admin.webhooks.create', 'link', 'Post events to another system'],
            ],
        ],
    ];
@endphp

<div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false" @click.outside="open = false">
    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-2">
        <x-icon name="plus" class="w-4 h-4" />
        <span>Create</span>
        <x-icon name="chevron-down" class="w-3.5 h-3.5 transition-transform" ::class="open && 'rotate-180'" />
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="open = false"
         class="absolute right-0 z-50 mt-2 w-[min(56rem,calc(100vw-1.5rem))] origin-top-right rounded-xl bg-white shadow-xl ring-1 ring-slate-200">
        <div class="grid gap-x-6 gap-y-5 p-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($groups as $group)
                <div class="min-w-0">
                    <p class="px-2 pb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $group['label'] }}</p>
                    <div class="space-y-0.5">
                        @foreach ($group['items'] as [$label, $route, $icon, $blurb])
                            @if (Route::has($route))
                                <a href="{{ route($route) }}"
                                   class="group flex items-start gap-3 rounded-lg border border-transparent px-2 py-2 transition hover:border-brand-200 hover:bg-brand-50/60">
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition group-hover:bg-brand-100 group-hover:text-brand-700">
                                        <x-icon :name="$icon" class="w-4 h-4" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-slate-900">{{ $label }}</span>
                                        <span class="block text-xs text-slate-500" style="overflow-wrap: anywhere;">{{ $blurb }}</span>
                                    </span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
