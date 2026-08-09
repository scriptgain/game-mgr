{{-- Moving a server to another machine.
     Every node is listed, including the ones that cannot take it, each with the
     reason. Hiding an unavailable node leaves somebody wondering where it went;
     saying "no room for a server that size" tells them what to fix. --}}
<x-card title="Move To Another Node" icon="network"
        subtitle="The server is offline for the transfer and its address changes when it lands. Its connection name follows by itself.">
    @if ($transferTargets->isEmpty())
        <x-empty-state icon="network" title="Nowhere To Move It"
                       description="This is the only node on the panel." />
    @else
        <div class="space-y-2">
            @foreach ($transferTargets as $target)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ $target['node']->name }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $target['node']->location?->name }}
                            @if ($target['reason'])
                                <span class="text-amber-700">{{ $target['reason'] }}</span>
                            @else
                                <span class="text-emerald-700">Ready to take it.</span>
                            @endif
                        </p>
                    </div>

                    @if ($target['reason'])
                        <x-button variant="secondary" size="sm" disabled>Cannot Move</x-button>
                    @else
                        <x-confirm-action
                            name="transfer-{{ $target['node']->id }}"
                            :action="route('admin.servers.transfer', $server)"
                            :fields="['node_id' => $target['node']->id]"
                            tone="warn"
                            title="Move {{ $server->name }} to {{ $target['node']->name }}?"
                            message="The files are copied across and the old copy removed once the new one is confirmed. The server is offline while that happens, and its address changes, so tell your players. If anything fails it stays exactly where it is."
                            confirm="Move It"
                            working="Moving">
                            <x-button variant="secondary" size="sm" icon="network">Move Here</x-button>
                        </x-confirm-action>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-card>
