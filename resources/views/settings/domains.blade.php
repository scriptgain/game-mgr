@php
    use App\Services\Dns\DnsConfig;

    $enabled = DnsConfig::enabled();
    $zone = old('domains_zone', DnsConfig::zone());
    $provider = old('domains_provider', DnsConfig::providerName());
    $exampleNode = $nodes->firstWhere('dns_label', '!=', null);
    $exampleLabel = $exampleNode?->dns_label ?: 'lax1';
    $exampleZone = $zone !== '' ? $zone : 'play.example.com';
@endphp
<x-layouts.app title="Domains">
    <x-page-header title="Domains" icon="globe"
                   subtitle="Give every server a name players can type, in a zone this panel owns.">
        <x-slot:actions>
            <x-button variant="secondary" icon="settings" href="{{ route('settings.index') }}">Settings</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="mb-5 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-800 ring-1 ring-brand-200">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200">{{ session('error') }}</div>
    @endif

    <div class="space-y-6">
        <x-alert type="info" title="The IP Address Never Goes Away">
            A name is an extra address, not a replacement. Every server keeps showing its direct
            <span class="font-mono">ip:port</span> everywhere it does today, and that address depends on no DNS at all.
            If anything on this page is misconfigured or the provider is down, every server stays reachable exactly as
            it is now.
        </x-alert>

        @if (DnsConfig::enabled() && ! DnsConfig::ready())
            <x-alert type="warn" title="Names Are On But Nothing Can Write Them">
                {{ DnsConfig::zone() === '' ? 'No zone is set, so there is nothing to build names under.' : 'Cloudflare is selected but no API token is stored, so records cannot be created or repaired.' }}
                Every server keeps its direct address in the meantime.
            </x-alert>
        @endif

        <form method="POST" action="{{ route('settings.domains.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-card title="Connection Names" icon="globe"
                    subtitle="One wildcard record per node covers every server on it, so creating a server makes no DNS call at all.">
                <div class="space-y-5">
                    <x-toggle name="domains_enabled" :checked="$enabled" label="Enable Connection Names"
                              description="Off means the panel shows the direct address only, exactly as it did before this feature existed." />

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <x-field label="Zone" for="domains_zone" required :error="$errors->first('domains_zone')"
                                 hint="The suffix names are built under. Point its nameservers at the provider below.">
                            <x-input id="domains_zone" name="domains_zone" :value="$zone" placeholder="play.example.com" />
                        </x-field>

                        <x-field label="Provider" for="domains_provider" :error="$errors->first('domains_provider')"
                                 hint="None writes nothing anywhere and shows you the records to create by hand.">
                            <x-select id="domains_provider" name="domains_provider">
                                @foreach (DnsConfig::PROVIDERS as $key => $label)
                                    <option value="{{ $key }}" @selected($provider === $key)>{{ $label }}</option>
                                @endforeach
                            </x-select>
                        </x-field>
                    </div>

                    <div class="rounded-lg bg-slate-50 px-4 py-3 ring-1 ring-inset ring-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">What A Server Gets</p>
                        <p class="mt-1 font-mono text-sm text-slate-900 [overflow-wrap:anywhere]">alpha.{{ $exampleLabel }}.{{ $exampleZone }}:8211</p>
                        <p class="mt-1 text-sm text-slate-500">
                            Answered by one wildcard record on the node,
                            <span class="font-mono text-xs">*.{{ $exampleLabel }}.{{ $exampleZone }}</span>. Nothing is created
                            or deleted when a server is.
                        </p>
                    </div>
                </div>
            </x-card>

            <x-card title="Cloudflare" icon="cloud"
                    subtitle="An API token with Zone.DNS edit on the zone. Stored encrypted, and never written to a config file.">
                <div class="space-y-5">
                    <x-alert type="warn" title="Every Record Is Grey Clouded, Always">
                        Game traffic is raw UDP and TCP and cannot pass through Cloudflare's proxy. An orange-clouded
                        record does not slow a game server down, it silently breaks it. The panel sends
                        <span class="font-mono">proxied: false</span> on every write and refuses to do anything else, and it
                        reports a record as wrong if it finds one proxied.
                    </x-alert>

                    <x-field label="API Token" for="domains_api_token" :error="$errors->first('domains_api_token')"
                             hint="{{ $hasToken ? 'A token is stored. Leave this blank to keep it.' : 'Cloudflare, My Profile, API Tokens, Edit zone DNS.' }}">
                        <x-input id="domains_api_token" name="domains_api_token" type="password"
                                 autocomplete="new-password" data-lpignore="true"
                                 placeholder="{{ $hasToken ? 'Stored. Type a new token to replace it.' : 'v1.0-...' }}" />
                    </x-field>

                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-sm text-slate-500">
                            The zone id is never asked for. The panel finds it from the zone name and caches it.
                        </p>
                        @if ($hasToken)
                            <x-confirm-action name="clear-dns-token" tone="danger"
                                              :action="route('settings.domains.token.clear')" method="DELETE"
                                              title="Delete The Stored Token?"
                                              message="Records already created stay where they are. The panel will stop being able to create or repair them until a new token is saved."
                                              confirm="Delete Token" confirm-variant="danger" confirm-icon="trash">
                                <x-button type="button" variant="danger-soft" size="sm" icon="trash">Delete Stored Token</x-button>
                            </x-confirm-action>
                        @endif
                    </div>
                </div>
            </x-card>

            <div class="flex items-center justify-end gap-2">
                <x-button variant="secondary" href="{{ route('settings.index') }}">Cancel</x-button>
                <x-button type="submit" icon="check">Save</x-button>
            </div>
        </form>

        <x-card title="Nodes" icon="server" flush
                subtitle="One wildcard record per node. A node with no label hands out no names, and its servers keep their direct address.">
            <x-slot:actions>
                <form method="POST" action="{{ route('settings.domains.sync') }}">
                    @csrf<x-button type="submit" variant="secondary" size="sm" icon="sync">Sync Now</x-button>
                </form>
            </x-slot:actions>

            @if ($nodes->isEmpty())
                <x-empty-state icon="server" title="No Nodes Yet"
                               description="Names are built on a node's label, so there is nothing to name until there is a node." />
            @else
                <x-table flush>
                    <thead>
                        <tr><th>Node</th><th>Label</th><th>Wildcard</th><th>Points At</th><th>State</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($nodes as $node)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.nodes.show', $node) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $node->name }}</a>
                                </td>
                                <td class="vx-cell-wrap font-mono text-xs text-slate-700">
                                    {{ $node->dns_label ?: 'Not Set' }}
                                </td>
                                <td class="vx-cell-wrap font-mono text-xs text-slate-700">
                                    {{ $node->wildcardName() ?: 'No name on this node' }}
                                </td>
                                <td class="vx-cell-wrap font-mono text-xs text-slate-700">
                                    {{ $node->dnsTargetIp() ?: 'No address known' }}
                                </td>
                                <td>
                                    <x-status-dot :tone="$node->wildcardTone()" :label="$node->wildcardStatusLabel()" />
                                    @if ($node->wildcard_error)
                                        <span class="block text-xs text-slate-500 [overflow-wrap:anywhere]">{{ $node->wildcard_error }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif
        </x-card>
    </div>
</x-layouts.app>
