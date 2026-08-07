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
    <x-badge :color="$color" :title="$label" {{ $attributes }}>
        <x-icon :name="$icon" class="w-3.5 h-3.5" />
        <span class="sr-only">{{ $label }}</span>
    </x-badge>
@else
    <x-badge :color="$color" {{ $attributes }}>
        <x-icon :name="$icon" class="w-3.5 h-3.5" /> {{ $label }}
    </x-badge>
@endif
