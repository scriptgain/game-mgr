<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <form method="POST" action="{{ route('server.settings.update', $server) }}">
                @csrf @method('PUT')
                <x-card title="Server Details" icon="info">
                    <div class="space-y-4">
                        <x-field label="Name" required :error="$errors->first('name')">
                            <x-input name="name" value="{{ old('name', $server->name) }}" required />
                        </x-field>
                        <x-field label="Description" hint="Only you and anyone you share this with will see it.">
                            <x-input name="description" value="{{ old('description', $server->description) }}" />
                        </x-field>
                        <x-toggle name="auto_restart" :checked="(bool) old('auto_restart', $server->auto_restart)"
                                  label="Restart After A Crash"
                                  description="The watchdog brings the server back rather than leaving it down until somebody notices." />
                        <x-toggle name="auto_update" :checked="(bool) old('auto_update', $server->auto_update)"
                                  label="Update Game Files Automatically"
                                  description="Worth having on for Steam games that patch constantly, and off if you run version-locked mods." />
                    </div>
                    <x-slot:footer>
                        <div class="flex justify-end"><x-button type="submit" size="sm">Save Settings</x-button></div>
                    </x-slot:footer>
                </x-card>
            </form>

            <form method="POST" action="{{ route('server.settings.status-page', $server) }}">
                @csrf @method('PUT')
                <x-card title="Public Status Page" icon="globe"
                        subtitle="A link you can put in your Discord so nobody has to ask whether the server is up.">
                    <div class="space-y-4">
                        <x-field label="Address" :error="$errors->first('slug')">
                            <x-input name="slug" value="{{ old('slug', $statusPage->slug) }}" required />
                            <p class="text-xs text-slate-500">{{ rtrim(config('app.url'), '/') }}/status/<span class="font-mono">{{ $statusPage->slug }}</span></p>
                        </x-field>
                        <x-field label="Headline">
                            <x-input name="headline" value="{{ old('headline', $statusPage->headline) }}" placeholder="{{ $server->name }} Status" />
                        </x-field>
                        <x-toggle name="is_public" :checked="(bool) old('is_public', $statusPage->is_public)"
                                  label="Publish It" description="Off means the address returns a 404 exactly as if it never existed." />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-toggle name="show_players" :checked="(bool) old('show_players', $statusPage->show_players)" label="Show Player Count" />
                            <x-toggle name="show_address" :checked="(bool) old('show_address', $statusPage->show_address)" label="Show Connect Address" />
                            <x-toggle name="show_uptime" :checked="(bool) old('show_uptime', $statusPage->show_uptime)" label="Show Uptime" />
                            <x-toggle name="show_version" :checked="(bool) old('show_version', $statusPage->show_version)" label="Show Game Version" />
                        </div>
                    </div>
                    <x-slot:footer>
                        <div class="flex items-center justify-between gap-3">
                            @if ($statusPage->exists && $statusPage->is_public)
                                <a href="{{ route('status.show', $statusPage->slug) }}" target="_blank" rel="noopener"
                                   class="text-sm text-brand-700 hover:text-brand-800 inline-flex items-center gap-1.5">
                                    <x-icon name="link" class="w-4 h-4" /> Open It
                                </a>
                            @else
                                <span></span>
                            @endif
                            <x-button type="submit" size="sm">Save Status Page</x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>

        <div class="space-y-6">
            <x-card title="Connection" icon="link">
                <div class="space-y-4">
                    <x-copy-field label="Game Address" :value="$server->address()" />
                    <x-copy-field label="SFTP Host" :value="($server->node?->fqdn ?: 'node').':'.($server->node?->sftp_port ?? 2022)" />
                    <x-copy-field label="SFTP Username" :value="$server->sftpUsername()" />
                    <p class="text-xs text-slate-500">Your SFTP password is your account password.</p>
                </div>
            </x-card>

            <x-card title="Limits" icon="memory">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Memory</dt><dd class="tabular text-slate-900">{{ \App\Support\Format::mib($server->memory) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Disk</dt><dd class="tabular text-slate-900">{{ \App\Support\Format::mib($server->disk) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">CPU</dt><dd class="tabular text-slate-900">{{ $server->cpu }}%</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Databases</dt><dd class="tabular text-slate-900">{{ $server->database_limit ?: 'unlimited' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Backups</dt><dd class="tabular text-slate-900">{{ $server->backup_limit ?: 'unlimited' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Allocations</dt><dd class="tabular text-slate-900">{{ $server->allocation_limit ?: 'unlimited' }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">Only an administrator can change these.</p>
            </x-card>

            @can('check', [$server, 'settings.reinstall'])
                <x-card title="Reinstall" icon="refresh">
                    <p class="text-sm text-slate-600">
                        Runs the template's install script again over this server. Game files are replaced; your world,
                        configuration and plugins stay where they are. Stop the server first.
                    </p>
                    <x-slot:footer>
                        <div class="flex justify-end">
                            <x-confirm-action
                                name="reinstall-server"
                                :action="route('server.settings.reinstall', $server)"
                                tone="warn"
                                title="Reinstall {{ $server->name }}?"
                                message="The install script runs again and replaces the game files. Your world and configuration are kept, but take a backup first if anything on this server matters."
                                confirm="Reinstall"
                                confirm-variant="danger">
                                <button type="button" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-rose-700 bg-white ring-1 ring-inset ring-rose-200 hover:bg-rose-50 hover:ring-rose-400 transition">
                                    <x-icon name="refresh" class="w-4 h-4" /> Reinstall Server
                                </button>
                            </x-confirm-action>
                        </div>
                    </x-slot:footer>
                </x-card>
            @endcan
        </div>
    </div>
</x-layouts.app>
