@props(['action', 'icon' => null, 'tone' => 'default', 'confirm' => null, 'confirmTitle' => 'Are You Sure?'])
{{-- One button in the mass-action bar.

     Without `confirm` it submits straight away, which is right for reversible
     things like acknowledging or enabling. With `confirm` it opens a modal
     first, because a bulk delete is the single easiest way to destroy a lot of
     somebody's work in one click. Never a native confirm(). --}}
@php
    $tones = [
        'default' => 'bg-white/10 text-white hover:bg-white/20 ring-1 ring-inset ring-white/15',
        'brand' => 'bg-brand-600 text-white hover:bg-brand-500 shadow-sm',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-500 shadow-sm',
    ];
    $classes = 'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition whitespace-nowrap '
        .($tones[$tone] ?? $tones['default']);
    $modal = 'bulk-'.$action.'-'.\Illuminate\Support\Str::random(6);
@endphp

@if ($confirm)
    <button type="button" @click="confirming = @js($modal); $dispatch('open-modal', @js($modal))" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="w-4 h-4 shrink-0" />@endif
        {{ $slot }}
    </button>

    <x-modal :name="$modal" :title="$confirmTitle" icon="warning" :tone="$tone === 'danger' ? 'danger' : 'warn'" maxWidth="max-w-md">
        <p><span x-text="count"></span> selected. {{ $confirm }}</p>
        <x-slot:footer>
            <x-button variant="secondary" size="sm" x-on:click="$dispatch('close-modal', @js($modal))">Cancel</x-button>
            <x-button :variant="$tone === 'danger' ? 'danger' : 'primary'" size="sm" :icon="$icon"
                      x-on:click="$dispatch('close-modal', @js($modal)); submitAs(@js($action))">{{ $slot }}</x-button>
        </x-slot:footer>
    </x-modal>
@else
    <button type="button" @click="submitAs(@js($action))" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="w-4 h-4 shrink-0" />@endif
        {{ $slot }}
    </button>
@endif
