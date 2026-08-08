{{-- The page opens on the facts somebody came for, then offers the long
     content behind tabs. It used to stack seven cards, of which the startup
     command alone could be two thousand characters of shell, so reaching the
     server list meant scrolling past a program nobody asked to read. --}}
@php
    $tabs = [
        ['id' => 'overview', 'label' => 'Overview', 'icon' => 'info'],
        ['id' => 'startup', 'label' => 'Startup', 'icon' => 'terminal'],
        ['id' => 'variables', 'label' => 'Variables', 'icon' => 'bolt', 'count' => $template->variables->count()],
        ['id' => 'servers', 'label' => 'Servers', 'icon' => 'server', 'count' => $servers->count()],
        ['id' => 'advanced', 'label' => 'Advanced', 'icon' => 'settings'],
    ];
@endphp

<x-layouts.app :title="$title">
    <x-page-header :title="$template->name" icon="cube" :subtitle="$template->game?->name.' template'">
        <x-slot:actions>
            <x-button href="{{ route('admin.templates.variables', $template) }}" variant="secondary" size="sm">Variables</x-button>
            <x-button href="{{ route('admin.templates.edit', $template) }}" icon="edit" size="sm">Edit</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('import_warnings'))
        <div class="mb-6 space-y-2">
            @foreach (session('import_warnings') as $warning)
                <x-alert type="warn">{{ $warning }}</x-alert>
            @endforeach
        </div>
    @endif

    {{-- Summary strip. Every value here used to be buried in one of the cards
         below it. Long values truncate and get the global [data-tip] tooltip,
         which is fixed to <body> and so cannot be clipped by this grid. --}}
    <x-card class="mb-6">
        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
            <div class="min-w-0">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Runtime</dt>
                <dd class="mt-1.5"><x-runtime-badge :runtime="$template->runtime" /></dd>
            </div>
            <div class="min-w-0">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Game</dt>
                <dd class="mt-1.5 truncate text-sm font-medium text-slate-900">
                    @if ($template->game)
                        <a href="{{ route('admin.templates.index', ['game' => $template->game_id]) }}"
                           class="text-brand-700 hover:text-brand-800">{{ $template->game->name }}</a>
                    @else
                        Not set
                    @endif
                </dd>
            </div>
            <div class="min-w-0">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Data Path</dt>
                <dd class="mt-1.5 truncate font-mono text-[13px] text-slate-900"
                    data-tip="{{ $template->data_path ?: 'Not set' }}">{{ $template->data_path ?: 'Not set' }}</dd>
            </div>
            {{-- These two wrap rather than truncate: they are short phrases with
                 spaces to break at, and "Minecraft, Game P..." tells nobody
                 anything. --}}
            <div class="min-w-0">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">RCON</dt>
                <dd class="mt-1.5 text-sm text-slate-900">{{ $rconSummary }}</dd>
            </div>
            <div class="min-w-0">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Query</dt>
                <dd class="mt-1.5 text-sm text-slate-900">{{ $querySummary }}</dd>
            </div>
            <div class="min-w-0">
                @if ($template->steam_app_id)
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Steam App ID</dt>
                    <dd class="mt-1.5 truncate text-sm tabular text-slate-900">{{ $template->steam_app_id }}</dd>
                @elseif ($template->lgsm_shortname)
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">LinuxGSM Script</dt>
                    <dd class="mt-1.5 truncate font-mono text-[13px] text-slate-900">{{ $template->lgsm_shortname }}</dd>
                @else
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Default Image</dt>
                    <dd class="mt-1.5 truncate font-mono text-[13px] text-slate-900"
                        data-tip="{{ $template->defaultImage() ?: 'Not set' }}">{{ $template->defaultImage() ?: 'Not set' }}</dd>
                @endif
            </div>
            <div class="min-w-0">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Servers Using It</dt>
                <dd class="mt-1.5 text-sm tabular text-slate-900">{{ $servers->count() }}</dd>
            </div>
        </dl>
    </x-card>

    <x-tab-set :tabs="$tabs" active="overview" label="Template sections">

        {{-- ------------------------------------------------------- overview --}}
        <x-tab-pane id="overview">
            <x-card title="Ports" icon="network" class="mb-6" flush
                    subtitle="What this game listens on. A server built from this template reserves the whole set together on one address, or it is not created.">
                @if ($template->ports->isEmpty())
                    <x-empty-state icon="network" title="No Ports Declared"
                                   description="Without a port set a server gets whatever number happens to be free, and nothing downstream knows which port is the game and which is RCON. Edit the template to declare them." />
                @else
                    <x-table flush>
                        <thead><tr><th>Purpose</th><th>Key</th><th>Port</th><th>Protocol</th><th>How It Is Worked Out</th><th>Required</th></tr></thead>
                        <tbody>
                            @foreach ($template->ports as $port)
                                <tr>
                                    <td class="font-medium text-slate-900">{{ $port->label ?: $port->roleLabel() }}</td>
                                    <td class="font-mono text-xs text-slate-500">{{ $port->role }}</td>
                                    <td class="tabular text-slate-900">{{ $port->resolve((int) $template->canonicalGamePort()) }}</td>
                                    <td><x-badge color="{{ $port->protocol === 'both' ? 'info' : 'neutral' }}">{{ $port->protocolLabel() }}</x-badge></td>
                                    <td class="text-slate-500">{{ $port->derivationLabel() }}</td>
                                    <td>
                                        @if ($port->required)
                                            <x-badge color="success">Required</x-badge>
                                        @else
                                            <x-badge color="neutral">Optional</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>

            <x-card title="What This Template Does" icon="info">
                <div class="space-y-5">
                    <p class="max-w-3xl text-sm leading-relaxed text-slate-600">
                        {{ $template->description ?: 'No description has been written for this template yet.' }}
                    </p>
                    <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Author</dt>
                            <dd class="mt-1 truncate text-sm text-slate-900">{{ $template->author ?: 'Not set' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Runtime</dt>
                            <dd class="mt-1 truncate text-sm text-slate-900">{{ $template->runtimeLabel() }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Update Command</dt>
                            <dd class="mt-1 truncate font-mono text-[13px] text-slate-900"
                                data-tip="{{ $template->update_command ?: 'Not set' }}">{{ $template->update_command ?: 'Not set' }}</dd>
                        </div>
                        <div class="min-w-0 sm:col-span-2 lg:col-span-1">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Features</dt>
                            <dd class="mt-1.5 flex flex-wrap gap-1.5">
                                @forelse ($template->features ?? [] as $feature)
                                    <x-badge color="info">{{ \Illuminate\Support\Str::headline($feature) }}</x-badge>
                                @empty
                                    <span class="text-sm text-slate-500">None</span>
                                @endforelse
                            </dd>
                        </div>
                        <div class="min-w-0 sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Mod Sources</dt>
                            <dd class="mt-1.5 flex flex-wrap gap-1.5">
                                @forelse ($template->mod_sources ?? [] as $source)
                                    <x-badge color="success">{{ \Illuminate\Support\Str::headline($source) }}</x-badge>
                                @empty
                                    <span class="text-sm text-slate-500">Mods are not offered for this template.</span>
                                @endforelse
                            </dd>
                        </div>
                    </dl>
                </div>
            </x-card>
        </x-tab-pane>

        {{-- -------------------------------------------------------- startup --}}
        <x-tab-pane id="startup">
            {{-- One card, not three. The command, the two lifecycle strings and
                 the install script are one story, and each extra card header
                 costs about eighty pixels of nothing. --}}
            <x-card title="How It Runs" icon="play" subtitle="Variables are substituted before any of this reaches the node.">
                <div class="space-y-6">
                    <x-code-pane label="Startup Command" :code="$template->startup" tall
                                 empty="No startup command. Edit this template before building a server from it." />

                    <dl class="grid gap-5 sm:grid-cols-2">
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Stop Command</dt>
                            <dd class="mt-1 truncate font-mono text-[13px] text-slate-900"
                                data-tip="{{ $template->stopCommand() }}">{{ $template->stopCommand() }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Ready When Output Contains</dt>
                            <dd class="mt-1 truncate font-mono text-[13px] text-slate-900"
                                data-tip="{{ $template->doneMarker() ?: 'Not set' }}">{{ $template->doneMarker() ?: 'Not set' }}</dd>
                        </div>
                    </dl>

                    <x-code-pane label="Install Script" :code="$template->script_install" tall
                                 :empty="'No install script. The '.$template->runtimeLabel().' runtime installs this game itself.'" />
                </div>
            </x-card>
        </x-tab-pane>

        {{-- ------------------------------------------------------ variables --}}
        <x-tab-pane id="variables">
            <x-card title="Variables" icon="sliders" subtitle="What a client can change on their Startup tab, and what stays yours." flush>
                <x-slot:actions>
                    <x-button href="{{ route('admin.templates.variables', $template) }}" variant="secondary" size="sm" icon="bolt">Manage</x-button>
                </x-slot:actions>
                @if ($template->variables->isEmpty())
                    <x-empty-state icon="bolt" title="No Variables"
                                   description="Add one for anything the startup command needs, like a version or a map name." />
                @else
                    <x-table flush>
                        <thead><tr><th>Name</th><th>Environment</th><th>Default</th><th>Visibility</th></tr></thead>
                        <tbody>
                            @foreach ($template->variables as $variable)
                                <tr>
                                    <td>
                                        <span class="font-medium text-slate-900">{{ $variable->name }}</span>
                                        @if ($variable->description)<span class="block truncate text-xs text-slate-400">{{ $variable->description }}</span>@endif
                                    </td>
                                    <td class="font-mono text-xs text-slate-500">{{ $variable->env_variable }}</td>
                                    <td class="font-mono text-xs text-slate-500">{{ $variable->default_value }}</td>
                                    <td>
                                        <span class="flex items-center gap-1">
                                            @if ($variable->user_editable)<x-badge color="success">Editable</x-badge>
                                            @elseif ($variable->user_viewable)<x-badge color="neutral">Read Only</x-badge>
                                            @else<x-badge color="warn">Hidden</x-badge>@endif
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </x-tab-pane>

        {{-- -------------------------------------------------------- servers --}}
        <x-tab-pane id="servers">
            <x-card title="Servers Built From This" icon="server" flush>
                @if ($servers->isEmpty())
                    <x-empty-state icon="server" title="Nothing Uses It Yet"
                                   description="Create a server from this template to try it out." />
                @else
                    <x-table flush>
                        <thead><tr><th>Server</th><th>Owner</th><th>Node</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($servers as $server)
                                <tr>
                                    <td><a href="{{ route('admin.servers.show', $server) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $server->name }}</a></td>
                                    <td class="text-slate-500">{{ $server->owner?->name }}</td>
                                    <td class="text-slate-500">{{ $server->node?->name }}</td>
                                    <td><x-status-dot :tone="$server->statusTone()" :label="$server->statusLabel()" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                @endif
            </x-card>
        </x-tab-pane>

        {{-- ------------------------------------------------------- advanced --}}
        <x-tab-pane id="advanced">
            <div class="grid gap-6 lg:grid-cols-2">
                <x-card title="Install Environment" icon="download" class="min-w-0"
                        subtitle="The throwaway container the install script runs in, whatever the runtime.">
                    <dl class="space-y-4">
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Script Container</dt>
                            <dd class="mt-1 truncate font-mono text-[13px] text-slate-900"
                                data-tip="{{ $template->script_container ?: 'Not set' }}">{{ $template->script_container ?: 'Not set' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Script Entrypoint</dt>
                            <dd class="mt-1 truncate font-mono text-[13px] text-slate-900">{{ $template->script_entry ?: 'Not set' }}</dd>
                        </div>
                        @if ($template->lgsm_shortname)
                            <div class="min-w-0">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">LinuxGSM Script</dt>
                                <dd class="mt-1 truncate font-mono text-[13px] text-slate-900">{{ $template->lgsm_shortname }}</dd>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Log Location</dt>
                            <dd class="mt-1 truncate font-mono text-[13px] text-slate-900"
                                data-tip="{{ data_get($template->config_logs, 'location') ?: 'Standard output' }}">{{ data_get($template->config_logs, 'location') ?: 'Standard output' }}</dd>
                        </div>
                    </dl>
                </x-card>

                <x-card title="Docker Images" icon="cloud" class="min-w-0"
                        subtitle="Offered when a server is created. The first one is the default.">
                    @if ($template->docker_images)
                        <dl class="space-y-3">
                            @foreach ($template->docker_images as $label => $image)
                                <div class="min-w-0">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                                    <dd class="mt-1 truncate font-mono text-[13px] text-slate-900" data-tip="{{ $image }}">{{ $image }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <p class="text-sm text-slate-500">
                            No images. The {{ $template->runtimeLabel() }} runtime installs onto the node itself rather than into a container.
                        </p>
                    @endif
                </x-card>

                <x-card title="Steam" icon="controller" class="min-w-0">
                    @if ($template->steam_app_id)
                        <dl class="space-y-4">
                            <div class="flex justify-between gap-3">
                                <dt class="text-sm text-slate-500">App ID</dt>
                                <dd class="tabular text-sm text-slate-900">{{ $template->steam_app_id }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-sm text-slate-500">Login</dt>
                                <dd class="text-sm text-slate-900">{{ $template->steam_anonymous ? 'Anonymous' : 'Account Required' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 min-w-0">
                                <dt class="text-sm text-slate-500 shrink-0">Branch</dt>
                                <dd class="truncate font-mono text-[13px] text-slate-900">{{ $template->steam_branch ?: 'public' }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="text-sm text-slate-500">This template does not pull anything from Steam.</p>
                    @endif
                </x-card>

                <x-card title="Provenance" icon="book" class="min-w-0">
                    <dl class="space-y-4">
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Imported From</dt>
                            <dd class="mt-1 truncate font-mono text-[13px] text-slate-900">
                                @if ($template->wasImported())
                                    {{ $template->imported_from ?: 'Pasted JSON' }}
                                @else
                                    Built in this panel
                                @endif
                            </dd>
                        </div>
                        @if ($template->wasImported())
                            <div class="min-w-0">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Imported</dt>
                                <dd class="mt-1 text-sm text-slate-900">{{ $template->imported_at?->diffForHumans() }}</dd>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Template UUID</dt>
                            <dd class="mt-1 truncate font-mono text-[13px] text-slate-900" data-tip="{{ $template->uuid }}">{{ $template->uuid }}</dd>
                        </div>
                    </dl>
                </x-card>

                @if ($configFilesJson)
                    <x-card title="Config File Rewrites" icon="edit" class="min-w-0 lg:col-span-2"
                            subtitle="Values the node writes into the game's own config before every boot.">
                        <x-code-pane label="Config Files" :code="$configFilesJson" />
                    </x-card>
                @endif

                @if ($template->file_denylist)
                    <x-card title="File Denylist" icon="ban" class="min-w-0 lg:col-span-2"
                            subtitle="Paths a client can never read, write or download.">
                        <ul class="flex flex-wrap gap-1.5">
                            @foreach ($template->file_denylist as $path)
                                <li><x-badge color="danger">{{ $path }}</x-badge></li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif
            </div>
        </x-tab-pane>

    </x-tab-set>
</x-layouts.app>
