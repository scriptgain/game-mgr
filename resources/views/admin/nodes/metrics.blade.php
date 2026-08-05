<x-layouts.app :title="$title">
    <x-page-header :title="$node->name.' Metrics'" icon="chart" subtitle="Seven days of node health." />

    @include('admin.nodes._tabs', ['node' => $node])

    @if ($series->isEmpty())
        <x-card>
            <x-empty-state icon="chart" title="No Samples Yet"
                           description="The node reports its health on every heartbeat. Give it a few minutes." />
        </x-card>
    @else
        @php
            $latest = $series->last();
            $peakCpu = round($series->max('cpu'), 1);
            $peakMem = (int) $series->max('memory');
        @endphp
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <x-stat label="CPU Now" :value="round($latest->cpu, 1).'%'" icon="cpu" :trend="'peak '.$peakCpu.'%'" trend-color="neutral" />
            <x-stat label="Memory Now" :value="\App\Support\Format::mib($latest->memory)" icon="memory" :trend="'peak '.\App\Support\Format::mib($peakMem)" trend-color="neutral" />
            <x-stat label="Disk Used" :value="\App\Support\Format::mib($latest->disk)" icon="database" />
            <x-stat label="Load Average" :value="round($latest->load, 2)" icon="bolt" />
        </div>

        <x-card title="Last Seven Days" flush>
            <x-table flush>
                <thead><tr><th>When</th><th>CPU</th><th>Memory</th><th>Disk</th><th>Load</th></tr></thead>
                <tbody>
                    @foreach ($series->reverse()->take(48) as $sample)
                        <tr>
                            <td class="text-slate-500">{{ $sample->sampled_at->format('M j, H:i') }}</td>
                            <td class="tabular">{{ round($sample->cpu, 1) }}%</td>
                            <td class="tabular">{{ \App\Support\Format::mib($sample->memory) }}</td>
                            <td class="tabular">{{ \App\Support\Format::mib($sample->disk) }}</td>
                            <td class="tabular">{{ round($sample->load, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>
    @endif
</x-layouts.app>
