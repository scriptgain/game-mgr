@props(['value' => 0, 'max' => 100, 'label' => null, 'suffix' => '', 'tone' => null])
{{-- A labelled progress bar. Colour is derived from how full it is unless a
     tone is forced, so "nearly out of disk" reads as a problem without anyone
     having to remember to pass a colour. --}}
@php
    $max = max(1, (float) $max);
    $pct = min(100, round((float) $value / $max * 100, 1));
    $tone = $tone ?? match (true) {
        $pct >= 90 => 'rose',
        $pct >= 75 => 'amber',
        default => 'brand',
    };
    $bar = ['brand' => 'bg-brand-500', 'amber' => 'bg-amber-500', 'rose' => 'bg-rose-500', 'emerald' => 'bg-emerald-500'][$tone];
@endphp
<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <div class="flex items-baseline justify-between gap-3 text-sm">
            <span class="font-medium text-slate-700">{{ $label }}</span>
            <span class="tabular text-slate-500">{{ $slot->isEmpty() ? $pct.'%' : $slot }}{{ $suffix }}</span>
        </div>
    @endif
    <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
        <div class="h-full rounded-full {{ $bar }} transition-all" style="width: {{ $pct }}%"></div>
    </div>
</div>
