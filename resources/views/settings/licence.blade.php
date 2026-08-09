<x-layouts.app :title="$title">
    <x-page-header title="Licence" icon="key"
                   subtitle="Which edition this panel runs as, and what that includes.">
        <x-slot:actions>
            @if ($hasKey)
                <form method="POST" action="{{ route('settings.licence.recheck') }}">
                    @csrf<x-button type="submit" variant="secondary" size="sm" icon="refresh">Check Again</x-button>
                </form>
            @endif
            <x-button variant="secondary" icon="settings" href="{{ route('settings.general.edit') }}">Settings</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="This Install" icon="shield">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-sm font-semibold text-brand-700 ring-1 ring-inset ring-brand-200">
                        {{ \App\Support\Edition::label($current) }} edition
                    </span>
                    <x-status-dot
                        :tone="match ($status['state']) {
                            'valid' => 'success',
                            'grace' => 'warn',
                            'unlicensed' => 'muted',
                            default => 'danger',
                        }"
                        :label="match ($status['state']) {
                            'valid' => 'Licence active',
                            'grace' => 'Running on the last good check',
                            'invalid' => 'Licence not valid',
                            'unverified' => 'Cannot verify',
                            default => 'No licence key',
                        }" />
                </div>

                <p class="mt-3 text-sm text-slate-600">{{ $status['message'] }}</p>

                @if ($status['state'] === 'unlicensed')
                    <div class="mt-4">
                        <x-alert type="info" title="Self-Hosted Is Free, And That Is Not A Trial">
                            Every feature, every game, as many servers and nodes as your machines will carry. Nothing
                            here expires, nothing is withheld, and there is no key to buy. A limit on a panel you run
                            on your own hardware would only be a line in a config file you own anyway.
                            <span class="mt-2 block">
                                The plans on the right are for the hosted version, where we run the panel and you bring
                                the machines. A key here is only for support and is entirely optional.
                            </span>
                        </x-alert>
                    </div>
                @endif

                <dl class="mt-5 grid gap-3 sm:grid-cols-3 text-sm">
                    <div>
                        <dt class="text-slate-500">Servers</dt>
                        <dd class="text-slate-900 tabular">
                            {{ $usage['servers'] }}{{ \App\Support\Edition::limit('servers') ? ' of '.\App\Support\Edition::limit('servers') : '' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Nodes</dt>
                        <dd class="text-slate-900 tabular">{{ $usage['nodes'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Last Checked</dt>
                        <dd class="text-slate-900">{{ \Illuminate\Support\Carbon::parse($status['checked_at'])->diffForHumans() }}</dd>
                    </div>
                </dl>

                <p class="mt-4 text-xs text-slate-500">
                    A licence problem never stops a server. Every game already running stays up, and any limit only
                    ever applies to creating the next one.
                </p>
            </x-card>

            <form method="POST" action="{{ route('settings.licence.update') }}">
                @csrf
                @method('PUT')
                <x-card title="Licence Key" icon="key"
                        subtitle="From your account at scriptgain.com. Leave it empty to run the free edition.">
                    <x-field label="Key" for="licence_key" :error="$errors->first('licence_key')"
                             hint="Stored encrypted. It is never written to the audit log.">
                        <x-input id="licence_key" name="licence_key" value="{{ $hasKey ? str_repeat('•', 24) : '' }}"
                                 placeholder="GM-XXXX-XXXX-XXXX-XXXX" autocomplete="off" />
                    </x-field>
                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2">
                            <x-button type="submit" icon="check">Save Key</x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>

        <div class="space-y-6">
            <x-card title="Hosted Plans" icon="cloud" flush
                    subtitle="For the version we run. Self-hosted has none of these limits.">
                <div class="divide-y divide-slate-100">
                    @foreach ($editions as $name => $tier)
                        @continue(empty($tier['hosted']))
                        <div class="px-5 py-4 {{ $name === $current ? 'bg-brand-50/40' : '' }}">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-slate-900">{{ $tier['label'] }}</p>
                                @if ($name === $current && ! empty($tier['hosted']))
                                    <span class="rounded-full bg-brand-600 px-2 py-0.5 text-xs font-medium text-white">Current</span>
                                @endif
                            </div>
                            <dl class="mt-2 space-y-1 text-xs text-slate-600">
                                <div class="flex justify-between gap-3">
                                    <dt>Servers</dt><dd class="tabular">{{ $tier['servers'] ?? 'unlimited' }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt>Nodes</dt><dd class="tabular">unlimited</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt>Games</dt><dd class="text-right">All of them</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt>Support</dt><dd class="text-right">{{ $tier['support'] ?? 'Community' }}</dd>
                                </div>
                            </dl>
                            @if (! empty($tier['features']))
                                <ul class="mt-2 space-y-1">
                                    @foreach ($tier['features'] as $feature)
                                        <li class="flex items-start gap-1.5 text-xs text-slate-600">
                                            <x-icon name="check" class="mt-0.5 h-3 w-3 shrink-0 text-emerald-600" />
                                            <span>{{ $features[$feature] ?? $feature }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>
        </div>
    </div>
</x-layouts.app>
