@props([
    'n',
    'title' => null,
    'icon' => null,
    'subtitle' => null,
    // The last step shows Save instead of Continue.
    'last' => false,
    'first' => false,
])

{{-- x-cloak matters here. Without it every step renders visible for the moment
     before Alpine starts, and a six step form flashes as one enormous page. --}}
<div class="gm-step" x-ref="step{{ $n }}" data-step="{{ $n }}" x-show="step === {{ $n }}" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0">
    <x-card :title="$title" :icon="$icon" :subtitle="$subtitle">
        {{ $slot }}

        <x-slot:footer>
            <div class="flex items-center justify-between gap-2">
                <div>
                    @unless ($first)
                        <x-button type="button" variant="secondary" @click="back()">Back</x-button>
                    @endunless
                </div>
                <div class="flex items-center gap-2">
                    {{ $actions ?? '' }}
                    @if ($last)
                        <x-button type="submit" icon="check">{{ $submit ?? 'Save' }}</x-button>
                    @else
                        <x-button type="button" @click="next()">Continue</x-button>
                    @endif
                </div>
            </div>
        </x-slot:footer>
    </x-card>
</div>
