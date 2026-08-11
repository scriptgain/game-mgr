@props(['game' => null, 'class' => 'h-11 w-11 rounded-lg', 'iconClass' => 'w-5 h-5'])
{{-- A game's cover: its real art when we have it, a generated tile when we do not.

     One component because both the games listing and the create wizard show
     the same thing, and they had drifted into two treatments of it. The
     fallback is not a placeholder image: a deterministic colour from the
     game's own name plus its glyph reads as deliberate, where a grey box with
     a broken-image icon reads as a fault. --}}
@php
    $art = $game?->artwork_path;
    // Hue from the name, so a game keeps the same colour everywhere it appears
    // and two games next to each other are reliably different.
    $hue = $game ? crc32($game->slug ?: $game->name) % 360 : 265;
    $tint = $game?->cover_color;
@endphp

@if ($art)
    <span {{ $attributes->merge(['class' => 'relative block shrink-0 overflow-hidden ring-1 ring-inset ring-black/10 '.$class]) }}>
        {{-- Decorative: the game's name is always rendered as text beside it,
             so alt text here would only repeat it to a screen reader. --}}
        <img src="{{ Storage::disk('public')->url($art) }}" alt=""
             loading="lazy" decoding="async"
             class="h-full w-full object-cover">
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center text-white ring-1 ring-inset ring-black/10 '.$class]) }}
          style="background: {{ $tint ? $tint : 'hsl('.$hue.' 55% 42%)' }};">
        <x-icon :name="$game?->icon ?: 'controller'" :class="$iconClass" />
    </span>
@endif
