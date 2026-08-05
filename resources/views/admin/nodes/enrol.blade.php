<x-layouts.app :title="$title">
    <x-page-header :title="'Enrol '.$node->name" icon="key"
                   subtitle="Run one command on the machine. No config files to edit, no keys to copy by hand." />

    @include('admin.nodes._tabs', ['node' => $node])

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Install Command">
                <p class="text-sm text-slate-600 mb-4">
                    Paste this into a root shell on the machine. It installs the daemon, registers it against this panel,
                    and reports back what the box actually has.
                </p>
                <x-copy-field :value="$command" />
                <div class="mt-4">
                    <x-alert type="warn" title="This Token Is Single Use">
                        It expires {{ $node->enrol_token_expires_at?->diffForHumans() ?? 'shortly' }} and can only enrol
                        one machine. All it buys the daemon is its long-lived credential, so a token that leaks onto a
                        support ticket is not a compromise. Generate a fresh one if in doubt.
                    </x-alert>
                </div>
                <x-slot:footer>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs text-slate-500">
                            {{ $node->enrolled_at ? 'Enrolled '.$node->enrolled_at->diffForHumans() : 'Not enrolled yet' }}
                        </span>
                        <form method="POST" action="{{ route('admin.nodes.enrol.regenerate', $node) }}">
                            @csrf<x-button type="submit" variant="secondary" size="sm" icon="refresh">New Token</x-button>
                        </form>
                    </div>
                </x-slot:footer>
            </x-card>

            <x-card title="Doing It By Hand">
                <p class="text-sm text-slate-600">
                    If you would rather not pipe a script into a shell, the daemon is a single static binary with no
                    dependencies. Download it, drop it at <span class="font-mono text-xs">/usr/local/bin/gamemgr-node</span>,
                    and run it with these environment variables:
                </p>
                <pre class="console-pane vx-scroll mt-3 p-3 text-xs overflow-x-auto">NODE_PANEL_URL={{ rtrim(config('app.url'), '/') }}
NODE_ENROL_TOKEN={{ $node->enrol_token }}
NODE_LISTEN=:{{ $node->daemon_port }}
NODE_ROOT={{ $node->daemon_base }}</pre>
                <p class="mt-3 text-sm text-slate-600">
                    It exchanges the enrol token for its real credential on first boot, writes that to its config, and
                    starts answering.
                </p>
            </x-card>
        </div>

        <div>
            <x-card title="What It Needs">
                <ul class="space-y-3 text-sm text-slate-600">
                    <li class="flex gap-2.5"><x-icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" /> A 64-bit Linux machine. Anything from a 2 GiB VPS upward.</li>
                    <li class="flex gap-2.5"><x-icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" /> Docker, if you want to run containerised templates.</li>
                    <li class="flex gap-2.5"><x-icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" /> steamcmd on PATH for the native SteamCMD runtime.</li>
                    <li class="flex gap-2.5"><x-icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" /> tmux for LinuxGSM, which uses it to hold the console.</li>
                    <li class="flex gap-2.5"><x-icon name="check" class="w-4 h-4 mt-0.5 text-emerald-600 shrink-0" /> Outbound HTTPS to this panel. Inbound is only needed in direct mode.</li>
                </ul>
            </x-card>
        </div>
    </div>
</x-layouts.app>
