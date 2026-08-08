<x-layouts.app title="General">
    <x-page-header title="General" icon="settings"
                   subtitle="Panel-wide defaults. Everything here is stored in the database, so no shell access is needed to change it." />

    <form method="POST" action="{{ route('settings.general.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Regional And Display" icon="globe" subtitle="How dates, times and lists appear across the panel.">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-field label="Timezone" for="timezone" required :error="$errors->first('timezone')"
                                 hint="Schedules fire on this zone. Arizona is America/Phoenix.">
                            <x-select id="timezone" name="timezone">
                                @foreach ($timezones as $tz)
                                    <option value="{{ $tz }}" @selected($v['timezone'] === $tz)>{{ $tz }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                        <x-field label="Server Clock" hint="Live time in the selected zone.">
                            <x-input type="text" value="{{ $now->format('g:i A T') }}" readonly class="bg-slate-50" />
                        </x-field>
                        <x-field label="Date Format" for="date_format" :error="$errors->first('date_format')">
                            <x-select id="date_format" name="date_format">
                                @foreach (['M j, Y' => $now->format('M j, Y'), 'Y-m-d' => $now->format('Y-m-d'), 'd/m/Y' => $now->format('d/m/Y'), 'm/d/Y' => $now->format('m/d/Y'), 'j M Y' => $now->format('j M Y')] as $fmt => $example)
                                    <option value="{{ $fmt }}" @selected($v['date_format'] === $fmt)>{{ $example }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                        <x-field label="Time Format" for="time_format" :error="$errors->first('time_format')">
                            <x-select id="time_format" name="time_format">
                                <option value="g:i A" @selected($v['time_format'] === 'g:i A')>12 Hour ({{ $now->format('g:i A') }})</option>
                                <option value="H:i" @selected($v['time_format'] === 'H:i')>24 Hour ({{ $now->format('H:i') }})</option>
                            </x-select>
                        </x-field>
                        <x-field label="Rows Per Page" for="rows_per_page" :error="$errors->first('rows_per_page')"
                                 hint="Pagination size for tables, between 10 and 200.">
                            <x-input type="number" id="rows_per_page" name="rows_per_page" min="10" max="200" value="{{ $v['rows_per_page'] }}" />
                        </x-field>
                    </div>
                </x-card>

                <x-card title="New Server Defaults" icon="server" subtitle="Prefilled on the create form. Per-server limits still win.">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <x-field label="Memory (MiB)" for="default_memory" :error="$errors->first('default_memory')">
                            <x-input type="number" id="default_memory" name="default_memory" value="{{ $v['default_memory'] }}" />
                        </x-field>
                        <x-field label="Disk (MiB)" for="default_disk" :error="$errors->first('default_disk')">
                            <x-input type="number" id="default_disk" name="default_disk" value="{{ $v['default_disk'] }}" />
                        </x-field>
                        <x-field label="CPU (%)" for="default_cpu" :error="$errors->first('default_cpu')"
                                 hint="100 is one core.">
                            <x-input type="number" id="default_cpu" name="default_cpu" value="{{ $v['default_cpu'] }}" />
                        </x-field>
                    </div>
                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <x-toggle name="allow_client_server_create" :checked="$v['allow_client_server_create'] === '1'"
                                  label="Clients May Create Their Own Servers"
                                  description="Off is the safe default. On is what you want once ordering and billing are wired up." />
                    </div>
                </x-card>

                <x-card title="Nodes" icon="cpu" subtitle="How the panel decides a node has gone away.">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-field label="Mark Offline After (seconds)" for="node_offline_after" :error="$errors->first('node_offline_after')"
                                 hint="Time without a heartbeat before a node reads offline and its servers are parked.">
                            <x-input type="number" id="node_offline_after" name="node_offline_after" min="30" max="3600" value="{{ $v['node_offline_after'] }}" />
                        </x-field>
                    </div>
                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <x-toggle name="node_fake" :checked="$v['node_fake'] === '1'"
                                  label="Answer Unreachable Nodes With Synthetic Data"
                                  description="Useful for a demo or a development stack. Dangerous in production: a dead node will look alive." />
                        @if ($v['node_fake'] === '1')
                            <div class="mt-3">
                                <x-alert type="warn">
                                    This is on. Screens will show plausible numbers for nodes that are not actually
                                    answering. Turn it off before anyone relies on what they see here.
                                </x-alert>
                            </div>
                        @endif
                    </div>
                </x-card>

                <x-card title="History And Housekeeping" icon="trash" subtitle="Metric history is the table that grows without bound if nobody watches it.">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-field label="Keep Metric History (days)" for="metric_history_days" :error="$errors->first('metric_history_days')"
                                 hint="One sample per server per minute is roughly half a million rows a year, per server.">
                            <x-input type="number" id="metric_history_days" name="metric_history_days" min="1" max="730" value="{{ $v['metric_history_days'] }}" />
                        </x-field>
                        <x-field label="Keep Audit Log (days)" for="audit_log_days" :error="$errors->first('audit_log_days')"
                                 hint="0 keeps everything forever.">
                            <x-input type="number" id="audit_log_days" name="audit_log_days" min="0" max="3650" value="{{ $v['audit_log_days'] }}" />
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Security" icon="shield">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-field label="Session Timeout (minutes)" for="session_timeout_minutes" :error="$errors->first('session_timeout_minutes')">
                            <x-input type="number" id="session_timeout_minutes" name="session_timeout_minutes" min="5" max="43200" value="{{ $v['session_timeout_minutes'] }}" />
                        </x-field>
                        <x-field label="Force Password Change After (days)" for="force_password_days" :error="$errors->first('force_password_days')"
                                 hint="0 never forces one.">
                            <x-input type="number" id="force_password_days" name="force_password_days" min="0" max="3650" value="{{ $v['force_password_days'] }}" />
                        </x-field>
                    </div>
                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <x-toggle name="require_2fa" :checked="$v['require_2fa'] === '1'"
                                  label="Require Two-Factor For Admins"
                                  description="Admin accounts without it are pushed to set it up before they can do anything else." />
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="System" icon="settings">
                    <dl class="space-y-2.5 text-sm">
                        @foreach ($info as $label => $value)
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">{{ $label }}</dt>
                                <dd class="text-slate-900 truncate">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>

                <x-card title="This Install" icon="info">
                    <dl class="space-y-2.5 text-sm">
                        @foreach ($counts as $label => $value)
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">{{ $label }}</dt>
                                <dd class="tabular text-slate-900">{{ number_format($value) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <x-slot:footer>
                        <div class="flex justify-end"><x-button type="submit" size="sm">Save Settings</x-button></div>
                    </x-slot:footer>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
