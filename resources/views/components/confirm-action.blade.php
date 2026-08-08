@props([
    'name',
    'action',
    'method' => 'POST',
    'title' => 'Are You Sure?',
    'message' => '',
    'confirm' => 'Confirm',
    'confirmIcon' => null,
    'confirmVariant' => 'primary',
    'tone' => 'default',
    // Extra payload for the confirm form, as name => value. Lets one endpoint
    // back several buttons (e.g. which repository, which kind of task) without
    // the caller hand-rolling a form outside the modal.
    'fields' => [],
    // Shown in place of the label once the action is on its way.
    'working' => 'Working',
])
@php
    // match, not an array lookup with ??. The null coalesce binds to the whole
    // concatenation rather than to the array access, so an unknown variant
    // threw "Undefined array key" instead of falling back, and took every page
    // carrying a confirm button with it.
    $confirmTone = match ($confirmVariant) {
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700',
        'secondary' => 'bg-white text-slate-700 ring-1 ring-inset ring-slate-200 hover:bg-slate-50',
        default => 'bg-brand-600 text-white hover:bg-brand-700',
    };
    $confirmClasses = 'inline-flex items-center justify-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium shadow-sm transition disabled:opacity-60 disabled:cursor-not-allowed '.$confirmTone;
@endphp
{{-- Wraps a trigger (passed as the default slot) so any action goes through a
     modal confirm instead of firing immediately. --}}
<span x-data @click="$dispatch('open-modal', '{{ $name }}')" class="inline-flex">{{ $slot }}</span>

<x-modal :name="$name" :title="$title" :icon="$tone === 'danger' ? 'warning' : 'info'" :tone="$tone" maxWidth="max-w-md">
    {{ $message }}
    <x-slot:footer>
        {{-- busy lives on the footer so Cancel goes quiet too: offering a way
             out of something already sent is a lie, and a second click on
             Confirm would send the action twice. Stopping a game server waits
             for it to save and can legitimately take half a minute, so a
             button that just sits there reads as broken. --}}
        <div x-data="{ busy: false }" class="flex items-center gap-2">
            <x-button variant="secondary" size="sm"
                      x-on:click="$dispatch('close-modal', '{{ $name }}')"
                      ::disabled="busy">Cancel</x-button>
            <form method="POST" action="{{ $action }}" @submit="busy = true">
                @csrf
                @if ($method !== 'POST')@method($method)@endif
                @foreach ($fields as $fieldName => $fieldValue)
                    <input type="hidden" name="{{ $fieldName }}" value="{{ $fieldValue }}">
                @endforeach
                {{-- x-bind:disabled, not ::disabled. The double colon is a
                     Blade escape meaning "pass a literal : to a COMPONENT", and
                     on a plain element it survives verbatim, so Alpine never
                     sees a binding and the button stays clickable. That is why
                     Cancel, an x-button, disabled correctly and this did not. --}}
                <button type="submit" x-bind:disabled="busy"
                        class="{{ $confirmClasses }}">
                    {{-- style, not only x-show. x-show does nothing until Alpine
                         has evaluated it, so the spinner painted inside the
                         button before anyone had clicked anything. Starting
                         hidden in the markup means the first frame is right
                         whatever the script does. --}}
                    <span x-show="busy" x-cloak style="display: none" class="inline-flex">
                        {{-- Plain CSS animation, not a Tailwind utility: this
                             markup is inside a component a purged build may
                             never see with the class present. --}}
                        <svg class="gm-spin h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity="0.25" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                    </span>
                    @if ($confirmIcon)
                        <span x-show="! busy"><x-icon :name="$confirmIcon" class="w-4 h-4" /></span>
                    @endif
                    <span x-text="busy ? '{{ $working }}' : '{{ $confirm }}'">{{ $confirm }}</span>
                </button>
            </form>
        </div>
    </x-slot:footer>
</x-modal>
