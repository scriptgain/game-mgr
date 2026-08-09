<x-layouts.app title="Telemetry">
    <x-page-header title="Telemetry" icon="chart"
                   subtitle="What this install tells ScriptGain about itself, and the exact text of the last thing it sent.">
        <x-slot:actions>
            <x-button variant="secondary" icon="settings" href="{{ route('settings.index') }}">Settings</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-6">
        <x-alert type="info" title="Counts, Never Names">
            How many servers, never which games. Never a hostname, never an address, never an email, never a customer.
            The payload below is the whole of what is ever sent, and it is the same list on every install.
        </x-alert>

        <form method="POST" action="{{ route('settings.telemetry.update') }}">
            @csrf
            @method('PUT')

            <x-card title="Send Anonymous Usage Counts" icon="bolt"
                    subtitle="The panel is free and self hosted, so this is the only way to know how many installs exist and which runtimes are worth maintaining.">
                <div class="space-y-5">
                    <x-toggle name="telemetry_enabled" :checked="$enabled" label="Send Telemetry"
                              description="Off means nothing at all is sent. No heartbeat, no version check, nothing." />

                    <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                        <div class="min-w-0">
                            <dt class="text-slate-500">Endpoint</dt>
                            <dd class="font-mono text-xs text-slate-900 [overflow-wrap:anywhere]">{{ $endpoint }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-slate-500">Frequency</dt>
                            <dd class="text-slate-900">Once a day, at most</dd>
                        </div>
                    </dl>

                    <p class="text-sm text-slate-500">
                        A send that fails is never reported. Whether ScriptGain is reachable is not your problem and
                        will not appear as an error anywhere in this panel.
                    </p>
                </div>

                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-button variant="secondary" href="{{ route('settings.index') }}">Cancel</x-button>
                        <x-button type="submit" icon="check">Save</x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>

        <x-card title="What Would Be Sent" icon="eye"
                subtitle="Built right now, from this install. Read it before you decide.">
            <x-code-pane label="Payload" :code="$payloadJson" />
        </x-card>

        <x-card title="What Was Last Sent" icon="upload"
                subtitle="The exact JSON that left this machine, kept so you can check it against the list above.">
            <x-slot:actions>
                <form method="POST" action="{{ route('settings.telemetry.send') }}">
                    @csrf<x-button type="submit" variant="secondary" size="sm" icon="upload">Send Now</x-button>
                </form>
            </x-slot:actions>

            @if ($lastJson === '')
                <x-empty-state icon="upload" title="Nothing Has Been Sent"
                               description="Either telemetry has never run here yet, or it is off. Send Now shows you exactly what goes." />
            @else
                <div class="space-y-3">
                    <p class="text-sm text-slate-500">
                        Sent {{ \Illuminate\Support\Carbon::parse($lastAt)->diffForHumans() }}
                        <span class="text-slate-400">({{ $lastAt }})</span>
                    </p>
                    <x-code-pane label="Last Payload" :code="$lastJson" />
                </div>
            @endif
        </x-card>
    </div>
</x-layouts.app>
