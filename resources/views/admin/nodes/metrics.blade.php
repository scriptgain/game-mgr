<x-layouts.app :title="$title">
    <x-page-header :title="$node->name.' Metrics'" icon="chart" subtitle="Seven days of node health." />

    @include('admin.nodes._tabs', ['node' => $node])

    @if (! $latest)
        <x-card>
            <x-empty-state icon="chart" title="No Samples Yet"
                           description="The node reports its health on every heartbeat. Give it a few minutes." />
        </x-card>
    @else
        {{-- Bytes, not MiB. Everything the daemon reports about the machine is
             in bytes; only the panel's own limits are in MiB. --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <x-stat label="CPU Now" :value="round($latest->cpu, 1).'%'" icon="cpu"
                    :trend="'peak '.round($summary->peak_cpu, 1).'%'" trend-color="neutral" />
            <x-stat label="Memory Now" :value="\App\Support\Format::bytes($latest->memory)" icon="memory"
                    :trend="'peak '.\App\Support\Format::bytes($summary->peak_memory)" trend-color="neutral" />
            <x-stat label="Disk Used" :value="\App\Support\Format::bytes($latest->disk)" icon="database"
                    :trend="'peak '.\App\Support\Format::bytes($summary->peak_disk)" trend-color="neutral" />
            <x-stat label="Load Average" :value="round($latest->load, 2)" icon="bolt"
                    :trend="'peak '.round($summary->peak_load, 2)" trend-color="neutral" />
        </div>

        <x-card title="Last Seven Days"
                :subtitle="number_format($summary->samples).' '.\Illuminate\Support\Str::plural('sample', $summary->samples).', newest first.'" flush>
            <x-table flush>
                <thead><tr><th>When</th><th>CPU</th><th>Memory</th><th>Disk</th><th>Load</th></tr></thead>
                <tbody>
                    @foreach ($samples as $sample)
                        <tr>
                            <td class="text-slate-500">{{ $sample->sampled_at->format('M j, H:i') }}</td>
                            <td class="tabular">{{ round($sample->cpu, 1) }}%</td>
                            <td class="tabular">{{ \App\Support\Format::bytes($sample->memory) }}</td>
                            <td class="tabular">{{ \App\Support\Format::bytes($sample->disk) }}</td>
                            <td class="tabular">{{ round($sample->load, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
            @if ($samples->hasPages())
                <x-slot:footer>{{ $samples->links() }}</x-slot:footer>
            @endif
        </x-card>
    @endif
</x-layouts.app>
