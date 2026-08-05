@props(['tone' => 'slate', 'label' => null, 'pulse' => false])
{{-- Status dot plus label. One component so "running" is the same green
     everywhere, rather than each table inventing its own. --}}
@php
    $map = [
        'emerald' => ['bg-emerald-500', 'text-emerald-700'],
        'amber' => ['bg-amber-500', 'text-amber-700'],
        'rose' => ['bg-rose-500', 'text-rose-700'],
        'sky' => ['bg-sky-500', 'text-sky-700'],
        'slate' => ['bg-slate-400', 'text-slate-600'],
    ];
    [$dot, $text] = $map[$tone] ?? $map['slate'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-sm font-medium '.$text]) }}>
    <span class="relative flex h-2 w-2 shrink-0">
        @if ($pulse)
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $dot }} opacity-70"></span>
        @endif
        <span class="relative inline-flex h-2 w-2 rounded-full {{ $dot }}"></span>
    </span>
    {{ $label ?? $slot }}
</span>
