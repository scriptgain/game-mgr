@props(['runtime' => 'docker', 'compact' => false])
{{-- Which of the three runtimes a template or server uses. This distinction is
     the product's whole point, so it gets a consistent chip rather than being
     buried in a details panel.

     compact drops the words and keeps the icon. A node with all three runtimes
     is three chips, and in a fixed-layout table column that is wider than the
     column will ever be: it used to spill across Servers and into Memory. The
     name stays reachable as a native title, which no overflow can clip. --}}
@php
    $map = [
        'docker' => ['Docker', 'info', 'cube'],
        'steamcmd' => ['SteamCMD', 'warn', 'download'],
        'linuxgsm' => ['LinuxGSM', 'success', 'terminal'],
    ];
    [$label, $color, $icon] = $map[$runtime] ?? [ucfirst((string) $runtime), 'neutral', 'cube'];
@endphp
@if ($compact)
    {{-- Purpose built rather than x-badge with an override class: both would be
         px-* utilities of equal specificity, so which one wins depends on their
         order in the generated stylesheet, not on the order they are written
         here. Three chips have to fit a table column, so the padding cannot be
         left to chance. --}}
    @php
        $tones = [
            'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'warn' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'neutral' => 'bg-slate-50 text-slate-600 ring-slate-200',
        ];
    @endphp
    <span title="{{ $label }}"
          {{ $attributes->merge(['class' => 'vx-badge inline-flex items-center justify-center rounded-full px-1.5 py-0.5 ring-1 ring-inset '.($tones[$color] ?? $tones['neutral'])]) }}>
        <x-icon :name="$icon" class="w-3.5 h-3.5" />
        <span class="sr-only">{{ $label }}</span>
    </span>
@else
    <x-badge :color="$color" {{ $attributes }}>
        <x-icon :name="$icon" class="w-3.5 h-3.5" /> {{ $label }}
    </x-badge>
@endif
