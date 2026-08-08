@props([
    'model',
    'name' => null,
    'title' => null,
    'description' => null,
    'icon' => null,
    'switchLabel' => null,
    'value' => 1,
])
{{-- A selectable card that is a real toggle switch, not a checkbox.

     `model` is the Alpine expression that holds the boolean, so the card, the
     switch and the posted value all read from one place and the wizard can
     summarise the choice later. Passing a name renders the hidden input that
     carries it: a checkbox that is off simply is not posted, so "absent" and
     "off" would look identical to the controller.

     The whole card toggles. The switch stops the click bubbling so pressing it
     directly does not toggle twice, and it stays a real button so the keyboard
     reaches it.

     Named node- because the node form owns it. If another form wants the same
     card, generalise it then rather than guessing at the shape now. --}}
@php $label = $switchLabel ?: $title; @endphp
<div @click="{{ $model }} = ! {{ $model }}"
     class="group relative flex h-full cursor-pointer flex-col rounded-xl p-4 ring-1 ring-inset transition"
     :class="{{ $model }}
        ? 'bg-brand-50/60 ring-brand-300 shadow-sm'
        : 'bg-white ring-slate-200 hover:ring-slate-300'">
    @if ($name)<input type="hidden" name="{{ $name }}" :value="{{ $model }} ? '{{ $value }}' : 0">@endif

    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            @isset($heading)
                {{ $heading }}
            @elseif ($title)
                <div class="flex items-center gap-2 min-w-0">
                    @if ($icon)
                        <span class="inline-flex w-7 h-7 shrink-0 items-center justify-center rounded-lg ring-1 transition"
                              :class="{{ $model }} ? 'bg-brand-100 text-brand-700 ring-brand-200' : 'bg-slate-100 text-slate-500 ring-slate-200'">
                            <x-icon :name="$icon" class="w-4 h-4" />
                        </span>
                    @endif
                    <p class="text-sm font-semibold text-slate-900 min-w-0">{{ $title }}</p>
                </div>
            @endif
        </div>

        <button type="button" role="switch"
                :aria-checked="({{ $model }}).toString()"
                @if ($label) aria-label="{{ $label }}" @endif
                @click.stop="{{ $model }} = ! {{ $model }}"
                :class="{{ $model }} ? 'bg-brand-600' : 'bg-slate-300'"
                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-2">
            <span :class="{{ $model }} ? 'translate-x-6' : 'translate-x-1'"
                  class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
        </button>
    </div>

    @if ($description)
        <p class="mt-2 text-sm text-slate-500">{{ $description }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-auto pt-3">{{ $slot }}</div>
    @endif
</div>
