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
    {{-- The value line renders for a slot as well as for a label. Keying it on
         the label alone meant every caller that passed only a slot, which is
         how the dashboard shows memory pressure, silently rendered no text at
         all: a node with nothing on it then drew a 0% bar under no number and
         the column looked empty rather than idle. --}}
    @if ($label || ! $slot->isEmpty())
        {{-- Smaller type when there is no label, because that is the in-table
             use and a table column is the one place this has to be narrow. --}}
        <div class="flex items-baseline gap-3 {{ $label ? 'justify-between text-sm' : 'text-xs' }}">
            @if ($label)<span class="font-medium text-slate-700">{{ $label }}</span>@endif
            <span class="tabular whitespace-nowrap text-slate-500">{{ $slot->isEmpty() ? $pct.'%' : $slot }}{{ $suffix }}</span>
        </div>
    @endif
    <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
        <div class="h-full rounded-full {{ $bar }} transition-all" style="width: {{ $pct }}%"></div>
    </div>
</div>
