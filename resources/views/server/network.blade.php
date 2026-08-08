<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    {{-- The canonical port rule, made visible. A shifted set is the one thing
         about this page an owner has to be told rather than left to notice: the
         number they hand their players is not the number every guide for the
         game prints, and nothing else on the screen says so. --}}
    @if ($portShift !== 0)
        <div class="mb-6">
            <x-alert type="warn" title="This Server Is Not On {{ $server->template?->name }}'s Usual Port">
                {{ $canonicalPort }} was already taken on {{ $server->allocation?->ip }} by another server, so this one and
                every port it uses moved by {{ $portShift > 0 ? '+'.$portShift : $portShift }}. Players connect to
                <span class="font-mono font-semibold">{{ $server->address() }}</span>, not port {{ $canonicalPort }}.
                A dedicated address would give this server the real port. Ask an administrator to move it to one.
            </x-alert>
        </div>
    @elseif ($canonicalPort)
        <div class="mb-6">
            <x-alert type="success" title="On The Real Port">
                This server holds {{ $server->template?->name }}'s canonical port {{ $canonicalPort }}, so players can
                connect with <span class="font-mono font-semibold">{{ $server->address() }}</span> and every guide written
                for this game applies as written.
            </x-alert>
        </div>
    @endif

    {{-- Only when there is a name to show. With the feature off this page looks
         exactly as it did before it existed. --}}
    @if ($server->connectAddress())
        <x-card title="How Players Connect" icon="globe" class="mb-6"
                subtitle="Two addresses for the same server. The name is easier to hand out; the address below it works with no DNS at all.">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-copy-field label="Connect" :value="$server->connectAddress()" />
                <x-copy-field label="Direct" :value="$server->address()" />
            </div>
            <p class="mt-3 text-xs text-slate-500">
                The name follows the node this server is on. Moving it to another node gives it a new name, and the
                direct address keeps working throughout.
            </p>
        </x-card>
    @endif

    <x-card title="Network" icon="network"
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
                <th>Purpose</th>
                <th>Protocol</th>
                <th>Role</th>
                <th class="text-right vx-act-2">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($allocations as $allocation)
                <tr>
                <td class="w-10"><x-select-toggle :value="$allocation->id" :label="$allocation->address()" /></td>
                {{-- Wraps rather than truncates: a fixed-layout table cut this to
                     "dedicated.gamemgr.local:82…" and the port is the entire point
                     of the row, being the number an owner hands their players.
                     The host is allowed to break anywhere and the port is not, so
                     a long alias never splits the number in half. --}}
                <td class="font-mono text-slate-900 vx-cell-wrap">
                <span class="text-slate-500 [overflow-wrap:anywhere]">{{ $allocation->ip_alias ?: $allocation->ip }}</span><wbr><span class="font-medium">:{{ $allocation->port }}</span>
                </td>
                <td class="text-slate-500">{{ $allocation->roleLabel() }}</td>
                <td><x-badge color="{{ $allocation->protocol === 'both' ? 'info' : 'neutral' }}">{{ $allocation->protocolLabel() }}</x-badge></td>
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
