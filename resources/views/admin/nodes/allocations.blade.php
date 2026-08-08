<x-layouts.app :title="$title">
    <x-page-header :title="$node->name.' Allocations'" icon="network"
                   subtitle="Ports are the scarce resource on a game node, so they are handed out explicitly rather than invented at start time." />

    @include('admin.nodes._tabs', ['node' => $node])

    {{-- Addresses before ports, because which address a server lands on is what
         decides whether its game gets its real port. An address nothing is on
         is treated as dedicated: there is nobody to collide with, so the
         canonical port is free and the planner takes it, no exceptions. --}}
    <x-card title="Addresses" icon="globe" class="mb-6" flush
            subtitle="A server on an address of its own always gets its game's real port. On a shared address only the first one can, and the rest are shifted and told so.">
        @if (empty($ips))
            <x-empty-state icon="network" title="No Addresses Yet"
                           description="Add an IP and a port range below. Everything else on this page follows from it." />
        @else
            <x-table flush>
                <thead><tr><th>Address</th><th>Alias</th><th>Ports</th><th>In Use</th><th>Servers</th><th>Kind</th></tr></thead>
                <tbody>
                    @foreach ($ips as $ip => $info)
                        <tr>
                            <td class="font-mono text-slate-900">{{ $ip }}</td>
                            <td class="text-slate-500">{{ $info['alias'] ?: 'None' }}</td>
                            <td class="tabular text-slate-500">{{ $info['ports'] }}</td>
                            <td class="tabular text-slate-500">{{ $info['used'] }}</td>
                            <td class="tabular text-slate-500">{{ $info['servers'] }}</td>
                            <td>
                                @if ($info['dedicated'])
                                    <x-badge color="success" dot>Dedicated</x-badge>
                                @else
                                    <x-badge color="warn">Shared</x-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        @endif
    </x-card>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Ports" icon="network" flush>
                @if ($allocations->isEmpty())
                    <x-empty-state icon="network" title="No Ports Yet"
                                   description="Add a range before placing servers here. A server with no allocation cannot accept players." />
                @else
                    <x-table flush>
                        <thead><tr><th>Address</th><th>Port</th><th>Purpose</th><th>Assigned To</th><th class="text-right vx-act-1">Actions</th></tr></thead>
                        <tbody>
                            @foreach ($allocations as $allocation)
                                <tr>
                                    <td class="font-mono text-slate-900">{{ $allocation->ip }}</td>
                                    <td class="tabular">{{ $allocation->port }}</td>
                                    <td class="text-slate-500">
                                        @if ($allocation->isAssigned())
                                            {{ $allocation->roleLabel() }} <span class="text-slate-400">{{ $allocation->protocolLabel() }}</span>
                                        @else
                                            <span class="text-slate-400">Free</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($allocation->server)
                                            <a href="{{ route('admin.servers.show', $allocation->server) }}" class="text-brand-700 hover:text-brand-800">{{ $allocation->server->name }}</a>
                                        @else
                                            <span class="text-slate-400">Free</span>
                                        @endif
                                    </td>
                                    <td class="text-right vx-act-1">
                                        @unless ($allocation->server)
                                            <x-delete-button
                                                name="drop-allocation-{{ $allocation->id }}"
                                                :action="route('admin.nodes.allocations.destroy', [$node, $allocation])"
                                                title="Remove {{ $allocation->address() }}?"
                                                message="The port goes back to the pool. Nothing is using it right now."
                                                label="Remove Allocation" />
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table>
                    <x-slot:footer>{{ $allocations->links() }}</x-slot:footer>
                @endif
            </x-card>
        </div>

        <div>
            <form method="POST" action="{{ route('admin.nodes.allocations.store', $node) }}">
                @csrf
                <x-card title="Add A Range" icon="plus">
                    <div class="space-y-4">
                        <x-field label="IP Address" required :error="$errors->first('ip')">
                            <x-input name="ip" value="{{ old('ip') }}" required placeholder="203.0.113.10" />
                        </x-field>
                        <x-field label="Alias" hint="What players actually type, if it differs from the IP.">
                            <x-input name="ip_alias" value="{{ old('ip_alias') }}" placeholder="eu.example.com" />
                        </x-field>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="First Port" required :error="$errors->first('port_start')">
                                <x-input type="number" name="port_start" value="{{ old('port_start', 27015) }}" required />
                            </x-field>
                            <x-field label="Last Port" required :error="$errors->first('port_end')">
                                <x-input type="number" name="port_end" value="{{ old('port_end', 27030) }}" required />
                            </x-field>
                        </div>
                    </div>
                    <x-slot:footer>
                        <div class="flex justify-end"><x-button type="submit" size="sm" icon="plus">Add Ports</x-button></div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
</x-layouts.app>
