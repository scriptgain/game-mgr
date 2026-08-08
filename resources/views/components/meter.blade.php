@props([
    'value' => 0,
    'max' => 100,
    'label' => null,
    'suffix' => '',
    'tone' => null,
    // Optional Alpine expressions, for a meter that has to move without a page
    // reload. `live` returns a percentage, `liveText` the value line, `liveTone`
    // a bar colour class. The server-rendered percentage stays as the first
    // frame, so the bar is never blank while Alpine boots.
    'live' => null,
    'liveText' => null,
    'liveTone' => null,
])
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
    // A live tone binding adds a class rather than replacing one, so the static
    // colour has to go or the winner would be whichever Tailwind emitted last.
    $staticBar = $liveTone ? '' : $bar;
@endphp
<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    {{-- The value line renders for a slot as well as for a label. Keying it on
         the label alone meant every caller that passed only a slot, which is
         how the dashboard shows memory pressure, silently rendered no text at
         all: a node with nothing on it then drew a 0% bar under no number and
         the column looked empty rather than idle. --}}
    @if ($label || ! $slot->isEmpty() || $liveText)
        {{-- Smaller type when there is no label, because that is the in-table
             use and a table column is the one place this has to be narrow. --}}
        <div class="flex items-baseline gap-3 {{ $label ? 'justify-between text-sm' : 'text-xs' }}">
            @if ($label)<span class="font-medium text-slate-700">{{ $label }}</span>@endif
            <span class="tabular whitespace-nowrap text-slate-500"
                  @if ($liveText) x-text="{{ $liveText }}" @endif>{{ $slot->isEmpty() ? $pct.'%' : $slot }}{{ $suffix }}</span>
        </div>
    @endif
    <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
        <div class="h-full rounded-full {{ $staticBar }} transition-all"
             @if ($liveTone) :class="{{ $liveTone }}" @endif
             style="width: {{ $pct }}%"
             @if ($live) :style="'width: ' + ({{ $live }}) + '%'" @endif></div>
    </div>
</div>
