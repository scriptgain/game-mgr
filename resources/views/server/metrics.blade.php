<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <x-stat label="Average CPU" :value="$summary['avg_cpu'].'%'" icon="cpu" :trend="'peak '.$summary['max_cpu'].'%'" trend-color="neutral" />
        <x-stat label="Average Memory" :value="\App\Support\Format::mib($summary['avg_mem'])" icon="memory" :trend="'peak '.\App\Support\Format::mib($summary['max_mem'])" trend-color="neutral" />
        <x-stat label="Peak Players" :value="$summary['peak_players']" icon="user-group" :trend="'average '.$summary['avg_players']" trend-color="neutral" />
        <x-stat label="Worst Tick Rate" :value="$summary['worst_tick'] ?: 'n/a'" icon="bolt"
                :trend="$summary['worst_tick'] && $summary['worst_tick'] < 18 ? 'dropped below 18' : 'held up'"
                :trend-color="$summary['worst_tick'] && $summary['worst_tick'] < 18 ? 'danger' : 'success'" />
    </div>

    <x-card title="History"
            subtitle="Kept for {{ config('gamemgr.metric_history_days', 30) }} days. Pterodactyl throws these numbers away the moment you close the tab."
            x-data="metricChart({ url: @js(route('server.metrics.series', [$server, 'range' => $range])), metric: 'cpu' })">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <x-select x-model="metric" class="w-40">
                    <option value="cpu">CPU</option>
                    <option value="memory">Memory</option>
                    <option value="players">Players</option>
                    <option value="disk">Disk</option>
                    <option value="tick_rate">Tick Rate</option>
                </x-select>
                <x-select onchange="window.location = this.value" class="w-44">
                    @foreach ($ranges as $key => $meta)
                        <option value="{{ route('server.metrics', [$server, 'range' => $key]) }}" @selected($key === $range)>{{ $meta['label'] }}</option>
                    @endforeach
                </x-select>
            </div>
        </x-slot:actions>

        <div class="relative h-72">
            <canvas x-ref="canvas" class="w-full h-full"></canvas>
            <div x-show="loading" class="absolute inset-0 flex items-center justify-center text-sm text-slate-400">Loading history</div>
            <div x-show="error" x-cloak class="absolute inset-0 flex items-center justify-center text-sm text-rose-600" x-text="error"></div>
        </div>
    </x-card>
</x-layouts.app>
