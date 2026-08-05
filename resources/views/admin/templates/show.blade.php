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

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="How It Runs">
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <x-runtime-badge :runtime="$template->runtime" />
                        <span class="text-sm text-slate-500">{{ $template->runtimeLabel() }}</span>
                    </div>
                    @if ($template->description)
                        <p class="text-sm text-slate-600">{{ $template->description }}</p>
                    @endif
                    <div>
                        <p class="text-sm font-medium text-slate-700 mb-1.5">Startup Command</p>
                        <pre class="console-pane vx-scroll p-3 text-xs whitespace-pre-wrap break-words">{{ $template->startup ?: 'Not set' }}</pre>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 text-sm">
                        <div><p class="text-slate-500">Stop Command</p><p class="font-mono text-slate-900">{{ $template->stopCommand() }}</p></div>
                        <div><p class="text-slate-500">Ready When Output Contains</p><p class="font-mono text-slate-900 truncate">{{ $template->doneMarker() ?: 'not set' }}</p></div>
                    </div>
                </div>
            </x-card>

            @if ($template->script_install)
                <x-card title="Install Script">
                    <pre class="console-pane vx-scroll p-3 text-xs max-h-96 overflow-y-auto whitespace-pre-wrap break-words">{{ $template->script_install }}</pre>
                </x-card>
            @endif

            <x-card title="Servers Built From This" flush>
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
        </div>

        <div class="space-y-6">
            <x-card title="Variables" flush>
                <x-slot:actions>
                    <x-button href="{{ route('admin.templates.variables', $template) }}" variant="secondary" size="sm" icon="bolt">Manage</x-button>
                </x-slot:actions>
                @if ($template->variables->isEmpty())
                    <p class="px-5 py-4 text-sm text-slate-500">This template exposes no variables.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($template->variables as $variable)
                            <li class="px-5 py-3">
                                <p class="text-sm font-medium text-slate-900">{{ $variable->name }}</p>
                                <p class="font-mono text-xs text-slate-400">{{ $variable->env_variable }}</p>
                                <div class="mt-1 flex items-center gap-1.5">
                                    @if ($variable->user_editable)<x-badge color="success">Client Editable</x-badge>@endif
                                    @unless ($variable->user_viewable)<x-badge color="neutral">Hidden</x-badge>@endunless
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @if ($template->runtime === 'docker' && $template->docker_images)
                <x-card title="Docker Images">
                    <ul class="space-y-2 text-sm">
                        @foreach ($template->docker_images as $label => $image)
                            <li>
                                <span class="text-slate-500">{{ $label }}</span>
                                <span class="block font-mono text-xs text-slate-900 break-all">{{ $image }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($template->steam_app_id)
                <x-card title="Steam">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">App ID</dt><dd class="tabular text-slate-900">{{ $template->steam_app_id }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Login</dt><dd class="text-slate-900">{{ $template->steam_anonymous ? 'Anonymous' : 'Account required' }}</dd></div>
                    </dl>
                </x-card>
            @endif

            @if ($template->wasImported())
                <x-card title="Imported">
                    <p class="text-sm text-slate-600">
                        From <span class="font-mono text-xs">{{ $template->imported_from ?: 'pasted JSON' }}</span>,
                        {{ $template->imported_at?->diffForHumans() }}.
                    </p>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
