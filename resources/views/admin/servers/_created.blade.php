@php
    // Only after a create. Everything here comes from the reservation that just
    // happened, so there is nothing to fetch and nothing to get stale.
    $created = session('created_server');
@endphp

@if ($created)
    <div class="mb-6 overflow-hidden rounded-xl bg-white ring-1 ring-emerald-200 shadow-sm">
        <div class="flex items-center gap-2.5 border-b border-emerald-100 bg-emerald-50/60 px-5 py-3">
            <x-icon name="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" />
            <p class="font-semibold text-emerald-900">Server Created</p>
            <span class="ml-auto text-xs text-emerald-700">Installing on {{ $created['node'] }}</span>
        </div>

        <div class="grid gap-x-8 gap-y-4 px-5 py-4 sm:grid-cols-2">
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Players Connect To</p>
                <p class="mt-1 font-mono text-lg text-slate-900">{{ $created['address'] }}</p>
                @if ($created['dedicated'])
                    <p class="mt-1 text-xs text-slate-500">On its own address, so the port is the game's own number.</p>
                @endif
            </div>

            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    {{ count($created['ports']) === 1 ? 'Port Reserved' : 'Ports Reserved' }}
                </p>
                <ul class="mt-1 space-y-0.5">
                    @foreach ($created['ports'] as $port)
                        <li class="flex items-baseline gap-2 text-sm">
                            <span class="font-mono text-slate-900">{{ $port['port'] }}/{{ $port['protocol'] }}</span>
                            <span class="text-slate-500">{{ implode(' + ', $port['roles']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- A shifted set is the one thing here somebody has to act on: the
             number a player types is not the number every guide for this game
             will tell them. It gets its own row rather than a clause. --}}
        @if ($created['canonical'] === false)
            <div class="border-t border-amber-100 bg-amber-50/60 px-5 py-3">
                <p class="text-sm text-amber-900">
                    <span class="font-medium">The usual port was taken.</span>
                    {{ $created['canonical_port'] }} was already in use on {{ $created['ip'] }}, so the whole set moved
                    by {{ $created['shift'] > 0 ? '+' : '' }}{{ $created['shift'] }}. Tell players
                    <span class="font-mono">{{ $created['address'] }}</span>, not
                    <span class="font-mono">{{ $created['canonical_port'] }}</span>.
                </p>
            </div>
        @endif

        @if (! empty($created['notes']))
            <div class="border-t border-slate-100 px-5 py-3">
                @foreach ($created['notes'] as $note)
                    <p class="text-xs text-slate-500">{{ $note }}</p>
                @endforeach
            </div>
        @endif
    </div>
@endif
