<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    @php
        $tabs = [];
        foreach ($files as $file) {
            $tabs[] = [
                'id' => $file->id,
                'label' => $file->label,
                'icon' => $state[$file->id]['exists'] ? 'settings' : 'warning',
            ];
        }
        $firstTab = $files[0]->id;
        $running = $server->power_state !== 'offline';
        // A strip holding one tab is a chip that does nothing. Most games have
        // exactly one config file, so that is the common case, not the edge.
        $manyFiles = count($files) > 1;
    @endphp

    <div x-data="configEditor({{ $server->configNeedsRestart() ? 'true' : 'false' }})">

    <form method="POST" action="{{ route('server.config.update', $server) }}">
        @csrf @method('PUT')

        {{-- The whole point of this tab is that it does not lie about when a
             change becomes real. A game reads these files at boot and never
             again, so a saved value and a running value are two different
             things until somebody restarts. --}}
        @if ($running)
            <div class="mb-6">
                <x-alert type="warn" title="Changes Apply on Restart">
                    <p>
                        This server is running, and {{ $server->template?->game?->name ?? 'this game' }} only reads these
                        files when it starts. Saving here changes the file on disk; the running server carries on with
                        whatever it read at boot until you restart it.
                    </p>
                    @if ($server->configNeedsRestart())
                        <p class="mt-2 font-semibold">
                            Configuration has been saved since this server last started, so what is on screen is not
                            what it is running.
                        </p>
                    @endif
                    @if ($canRestart && ! $server->isSuspended())
                        {{-- Confirms like every other power control. This one
                             dropped everybody playing with a single click and no
                             question, from a settings page. --}}
                        <div class="mt-3">
                            <x-confirm-action
                                name="restart-from-config"
                                :action="route('server.power', $server)"
                                method="POST"
                                tone="warn"
                                title="Restart The Server?"
                                message="Everyone playing right now will be disconnected. The world is saved first, so nothing is lost, but players will have to rejoin. This is what makes the configuration above take effect."
                                confirm="Restart It"
                                :fields="['action' => 'restart']">
                                <x-button type="button" variant="secondary" size="sm">Restart Now</x-button>
                            </x-confirm-action>
                        </div>
                    @endif
                    <p class="mt-2" x-show="touched" x-cloak>
                        You have unsaved changes on this page.
                    </p>
                </x-alert>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="min-w-0 lg:col-span-2">
                @if ($manyFiles)
                    <x-tab-set :tabs="$tabs" :active="$firstTab" label="Configuration files">
                        @foreach ($files as $file)
                            <x-tab-pane :id="$file->id">
                                @include('server._config-file', ['file' => $file, 'info' => $state[$file->id]])
                            </x-tab-pane>
                        @endforeach
                    </x-tab-set>
                @else
                    <div class="space-y-6">
                        @include('server._config-file', ['file' => $files[0], 'info' => $state[$files[0]->id]])
                    </div>
                @endif

                @if ($canEdit)
                    <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                        <p class="mr-auto text-sm text-slate-500">
                            Only the settings you changed are written. Comments and anything else in the file are left
                            exactly as they are.
                        </p>
                        <x-button type="submit" size="sm">Save Configuration</x-button>
                    </div>
                @else
                    <div class="mt-6">
                        <x-alert type="info">
                            You can see this configuration but not change it. Ask the server owner for the
                            Change Game Configuration permission.
                        </x-alert>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <x-card title="Files" icon="folder">
                    <div class="space-y-3 text-sm">
                        @foreach ($files as $file)
                            <div class="min-w-0">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="min-w-0 truncate text-slate-900">{{ $file->label }}</span>
                                    <x-status-dot :tone="$state[$file->id]['exists'] ? 'emerald' : 'amber'"
                                                  :label="$state[$file->id]['exists'] ? 'Present' : 'Not Written'" />
                                </div>
                                <p class="mt-0.5 truncate font-mono text-xs text-slate-400">{{ $file->path }}</p>
                            </div>
                        @endforeach
                    </div>
                    <x-slot:footer>
                        <a href="{{ route('server.files', $server) }}"
                           class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-700 hover:text-brand-800">
                            <x-icon name="folder" class="w-4 h-4" /> Open the File Manager
                        </a>
                    </x-slot:footer>
                </x-card>

                <x-card title="How This Works" icon="info">
                    <div class="space-y-3 text-sm text-slate-600">
                        <p>
                            These are the game's own configuration files, read straight off the server every time you
                            open this page. A change you make by hand in the file manager shows up here immediately.
                        </p>
                        <p>
                            Saving rewrites only the lines you changed. Comments, ordering and any setting this panel
                            has never heard of are carried through untouched.
                        </p>
                        <p>
                            Some settings are also kept on the Startup tab, because this template rewrites its config
                            from the environment on every boot. Those are saved in both places so a restart does not
                            undo your change.
                        </p>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
    </div>
</x-layouts.app>
