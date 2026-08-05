<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <x-card title="Network"
            subtitle="{{ $allocations->count() }} of {{ $server->allocation_limit ?: 'unlimited' }} allocations used. The primary address is the one players connect to."
            flush>
        <x-slot:actions>
            @can('check', [$server, 'allocation.create'])
                <form method="POST" action="{{ route('server.network.store', $server) }}">
                    @csrf<x-button type="submit" size="sm" icon="plus">Add Allocation</x-button>
                </form>
            @endcan
        </x-slot:actions>

        @if ($allocations->isEmpty())
            <x-empty-state icon="network" title="No Ports Allocated"
                           description="A server needs at least one address before it can accept players." />
        @else
            <x-mass-actions :action="route('server.bulk', [$server, 'allocations'])" label="allocation">
            <x-slot:table>
                <x-table flush>
                <thead>
                <tr><th class="w-10"><x-select-toggle all /></th>
                <th>Address</th>
                <th>IP</th>
                <th>Port</th>
                <th>Role</th>
                <th class="text-right vx-act-2">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($allocations as $allocation)
                <tr>
                <td class="w-10"><x-select-toggle :value="$allocation->id" :label="$allocation->address()" /></td>
                <td class="font-mono text-slate-900">{{ $allocation->address() }}</td>
                <td class="font-mono text-xs text-slate-500">{{ $allocation->ip }}</td>
                <td class="tabular text-slate-500">{{ $allocation->port }}</td>
                <td>
                @if ($server->allocation_id === $allocation->id)
                <x-badge color="success" dot>Primary</x-badge>
                @else
                <x-badge color="neutral">Additional</x-badge>
                @endif
                </td>
                <td class="text-right vx-act-2">
                <div class="inline-flex items-center gap-1">
                @can('check', [$server, 'allocation.update'])
                @if ($server->allocation_id !== $allocation->id)
                <form method="POST" action="{{ route('server.network.primary', [$server, $allocation]) }}">
                @csrf<x-icon-button type="submit" icon="star" title="Make This The Primary Address" />
                </form>
                @endif
                @endcan
                @can('check', [$server, 'allocation.delete'])
                @if ($server->allocation_id !== $allocation->id)
                <x-delete-button
                name="release-allocation-{{ $allocation->id }}"
                :action="route('server.network.destroy', [$server, $allocation])"
                title="Release {{ $allocation->address() }}?"
                message="The port goes back to the node's pool. Anything pointed at it stops connecting."
                confirm="Release"
                label="Release Allocation" />
                @endif
                @endcan
                </div>
                </td>
                </tr>
                @endforeach
                </tbody>
                </x-table>
            </x-slot:table>

            <x-mass-action action="release" icon="trash" tone="danger" confirm="The ports go back to the node pool. Anything pointed at them stops connecting. The primary address is skipped." confirm-title="Release These Ports?">Release</x-mass-action>
        </x-mass-actions>
        @endif
    </x-card>
</x-layouts.app>
