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

            {{-- Put it on their own website, three ways. Only shown once the
                 page is actually public, because every snippet here is a URL
                 that 404s until it is. --}}
            @if ($statusPage->exists && $statusPage->is_public)
                <x-card title="Put This On Your Own Site" icon="link"
                        subtitle="An iframe if you want it to look like this, JSON if you would rather build your own.">
                    <div class="space-y-5">
                        <div>
                            <p class="text-sm font-medium text-slate-900">Embed The Card</p>
                            <p class="mt-0.5 mb-2 text-sm text-slate-500">
                                Drop this anywhere in your page. Add <span class="font-mono text-xs">?theme=dark</span> or
                                <span class="font-mono text-xs">?theme=light</span> to pin it; without one it follows your
                                visitor's own setting.
                            </p>
                            <x-code-pane label="HTML" :code="$embed['iframe']" />
                        </div>

                        <div>
                            <p class="text-sm font-medium text-slate-900">Build Your Own</p>
                            <p class="mt-0.5 mb-2 text-sm text-slate-500">
                                The same facts as JSON, readable from any origin, cached for thirty seconds. Whatever you
                                switched off above is absent here too.
                            </p>
                            <x-code-pane label="JSON" :code="$embed['json']" />
                        </div>

                        <div>
                            <p class="text-sm font-medium text-slate-900">Or Drop In The Widget</p>
                            <p class="mt-0.5 mb-2 text-sm text-slate-500">
                                One script tag, no iframe, no styling of ours on your page. Every element carries a class
                                so you can make it look like your site.
                            </p>
                            <x-code-pane label="HTML" :code="$embed['widget']" tall />
                        </div>
                    </div>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Connection" icon="link">
                <div class="space-y-4">
                    @if ($server->connectAddress())
                        <x-copy-field label="Connect" :value="$server->connectAddress()" />
                        <x-copy-field label="Direct" :value="$server->address()" />
                    @else
                        <x-copy-field label="Game Address" :value="$server->address()" />
                    @endif
                    <x-domains-hint :server="$server" />
                </div>
            </x-card>

            {{-- Shown only to somebody who can actually use it. A username and a
                 host for a login that will be refused is worse than no card. --}}
            @can('check', [$server, 'file.sftp'])
                <x-card title="File Access" icon="folder">
                    @if ($server->node?->connection_mode === 'reverse')
                        {{-- The daemon may well have SFTP running and report it
                             happily. It is still unreachable: nothing on this
                             node accepts an inbound connection, which is the
                             entire reason it is in reverse mode. Printing a
                             host here would be printing one nobody can use. --}}
                        <x-alert type="info" title="This Node Is Not Reachable For SFTP">
                            It connects out to the panel rather than accepting connections, so an SFTP client has
                            nothing to dial. Use the
                            <a href="{{ route('server.files', $server) }}" class="font-medium underline">file manager</a>,
                            which reaches this node the same way the panel does.
                        </x-alert>
                    @elseif ($server->node?->sftp_enabled)
                        <div class="space-y-4">
                            <x-copy-field label="Host" :value="$server->sftpHost()" />
                            <x-copy-field label="Username" :value="$server->sftpUsername(auth()->user())" />

                            @if ($server->node?->sftp_fingerprint)
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-slate-500">Host Key</p>
                                    <p class="mt-1 break-all font-mono text-xs text-slate-700">{{ $server->node->sftp_fingerprint }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Your client shows this the first time you connect. If it matches, it is this node.
                                    </p>
                                </div>
                            @endif

                            <p class="text-xs text-slate-500">
                                Sign in with your account password, the same one you use here. You will land in this
                                server's own folder and cannot move above it.
                            </p>
                        </div>
                    @else
                        <x-alert type="info" title="File Access Is Off For This Node">
                            Use the <a href="{{ route('server.files', $server) }}" class="font-medium underline">file manager</a>
                            instead. An administrator can turn SFTP on for this node.
                        </x-alert>
                    @endif
                </x-card>
            @endcan

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
