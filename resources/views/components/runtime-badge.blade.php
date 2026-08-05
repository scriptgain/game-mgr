@props(['runtime' => 'docker'])
{{-- Which of the three runtimes a template or server uses. This distinction is
     the product's whole point, so it gets a consistent chip rather than being
     buried in a details panel. --}}
@php
    $map = [
        'docker' => ['Docker', 'info', 'cube'],
        'steamcmd' => ['SteamCMD', 'warn', 'download'],
        'linuxgsm' => ['LinuxGSM', 'success', 'terminal'],
    ];
    [$label, $color, $icon] = $map[$runtime] ?? [ucfirst((string) $runtime), 'neutral', 'cube'];
@endphp
<x-badge :color="$color" {{ $attributes }}>
    <x-icon :name="$icon" class="w-3.5 h-3.5" /> {{ $label }}
</x-badge>
