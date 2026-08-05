<x-layouts.app :title="$title">
    <x-page-header :title="$server->name" icon="server"
                   :subtitle="$server->template?->game?->name.' on '.$server->node?->name">
        <x-slot:actions>
            <x-button href="{{ route('server.console', $server) }}" variant="secondary" size="sm" icon="terminal">Open Console</x-button>
            <x-button href="{{ route('admin.servers.edit', $server) }}" size="sm" icon="edit">Edit</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Overview">
                <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div><dt class="text-slate-500">Owner</dt><dd class="text-slate-900">{{ $server->owner?->name }} <span class="text-slate-400">({{ $server->owner?->email }})</span></dd></div>
                    <div><dt class="text-slate-500">Status</dt><dd><x-status-dot :tone="$server->statusTone()" :label="$server->statusLabel()" /></dd></div>
                    <div><dt class="text-slate-500">Node</dt><dd><a href="{{ route('admin.nodes.show', $server->node) }}" class="text-brand-700 hover:text-brand-800">{{ $server->node?->name }}</a></dd></div>
                    <div><dt class="text-slate-500">Location</dt><dd class="text-slate-900">{{ $server->node?->location?->flag }} {{ $server->node?->location?->name }}</dd></div>
                    <div><dt class="text-slate-500">Template</dt><dd><a href="{{ route('admin.templates.show', $server->template) }}" class="text-brand-700 hover:text-brand-800">{{ $server->template?->name }}</a></dd></div>
                    <div><dt class="text-slate-500">Runtime</dt><dd><x-runtime-badge :runtime="$server->runtime" /></dd></div>
                    <div><dt class="text-slate-500">Address</dt><dd class="font-mono text-xs text-slate-900">{{ $server->address() }}</dd></div>
                    <div><dt class="text-slate-500">Installed</dt><dd class="text-slate-900">{{ $server->installed_at?->diffForHumans() ?? 'not yet' }}</dd></div>
                    <div><dt class="text-slate-500">UUID</dt><dd class="font-mono text-xs text-slate-500 break-all">{{ $server->uuid }}</dd></div>
                </dl>
            </x-card>

            <x-card title="Limits">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-meter label="Memory" :value="$server->cached_memory" :max="$server->memory">
                        {{ \App\Support\Format::mib($server->cached_memory) }} / {{ \App\Support\Format::mib($server->memory) }}
                    </x-meter>
                    <x-meter label="Disk" :value="$server->cached_disk" :max="$server->disk">
                        {{ \App\Support\Format::mib($server->cached_disk) }} / {{ \App\Support\Format::mib($server->disk) }}
                    </x-meter>
                </div>
                <dl class="mt-5 grid gap-4 sm:grid-cols-3 text-sm">
                    <div><dt class="text-slate-500">CPU</dt><dd class="tabular text-slate-900">{{ $server->cpu }}%</dd></div>
                    <div><dt class="text-slate-500">Swap</dt><dd class="tabular text-slate-900">{{ $server->swap }} MiB</dd></div>
                    <div><dt class="text-slate-500">Block IO Weight</dt><dd class="tabular text-slate-900">{{ $server->io }}</dd></div>
                    <div><dt class="text-slate-500">Databases</dt><dd class="tabular text-slate-900">{{ $server->databases->count() }} / {{ $server->database_limit ?: 'unlimited' }}</dd></div>
                    <div><dt class="text-slate-500">Backups</dt><dd class="tabular text-slate-900">{{ $server->backups->count() }} / {{ $server->backup_limit ?: 'unlimited' }}</dd></div>
                    <div><dt class="text-slate-500">Allocations</dt><dd class="tabular text-slate-900">{{ $server->allocation_limit ?: 'unlimited' }}</dd></div>
                </dl>
            </x-card>

            @if ($server->subusers->isNotEmpty())
                <x-card title="Shared With" flush>
                    <x-table flush>
                        <thead><tr><th>User</th><th>Email</th><th>Permissions</th></tr></thead>
                        <tbody>
                            @foreach ($server->subusers as $subuser)
                                <tr>
                                    <td class="font-medium text-slate-900">{{ $subuser->user?->name }}</td>
                                    <td class="text-slate-500">{{ $subuser->user?->email }}</td>
                                    <td class="tabular text-slate-500">{{ count($subuser->permissions ?? []) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Administration">
                <div class="space-y-3">
                    @if ($server->isSuspended())
                        <form method="POST" action="{{ route('admin.servers.unsuspend', $server) }}">
                            @csrf<x-button type="submit" class="w-full" icon="check">Unsuspend</x-button>
                        </form>
                    @else
                        <x-confirm-action
                            name="suspend-server"
                            :action="route('admin.servers.suspend', $server)"
                            tone="warn"
                            title="Suspend {{ $server->name }}?"
                            message="The server stops and the owner loses every control except reading. Files, backups and databases are untouched."
                            confirm="Suspend"
                            confirm-variant="danger"
                            class="w-full">
                            <button type="button" class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-amber-800 bg-white ring-1 ring-inset ring-amber-200 hover:bg-amber-50 hover:ring-amber-400 transition">
                                <x-icon name="ban" class="w-4 h-4" /> Suspend
                            </button>
                        </x-confirm-action>
                    @endif

                    <x-confirm-action
                        name="reinstall-server-admin"
                        :action="route('admin.servers.reinstall', $server)"
                        tone="warn"
                        title="Reinstall {{ $server->name }}?"
                        message="The install script runs again over this server. Game files are replaced; the data directory is kept."
                        confirm="Reinstall"
                        class="w-full">
                        <button type="button" class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-slate-700 bg-white ring-1 ring-inset ring-slate-300 hover:bg-slate-50 hover:ring-slate-400 transition">
                            <x-icon name="refresh" class="w-4 h-4" /> Reinstall
                        </button>
                    </x-confirm-action>

                    <x-confirm-action
                        name="delete-server"
                        :action="route('admin.servers.destroy', $server)"
                        method="DELETE"
                        tone="danger"
                        title="Delete {{ $server->name }}?"
                        message="The server record, its backups and its databases are removed and its ports are freed. There is no undo."
                        confirm="Delete Server"
                        confirm-variant="danger"
                        class="w-full">
                        <button type="button" class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-rose-700 bg-white ring-1 ring-inset ring-rose-200 hover:bg-rose-50 hover:ring-rose-400 transition">
                            <x-icon name="trash" class="w-4 h-4" /> Delete Server
                        </button>
                    </x-confirm-action>
                </div>
            </x-card>

            <x-card title="Recent Backups" flush>
                @if ($server->backups->isEmpty())
                    <p class="px-5 py-4 text-sm text-slate-500">No backups taken.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($server->backups->take(5) as $backup)
                            <li class="px-5 py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm text-slate-800 truncate">{{ $backup->name }}</p>
                                    <p class="text-xs text-slate-400">{{ \App\Support\Format::bytes($backup->bytes) }} &middot; {{ $backup->completed_at?->diffForHumans() }}</p>
                                </div>
                                <x-status-dot :tone="$backup->statusTone()" :label="$backup->statusLabel()" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
