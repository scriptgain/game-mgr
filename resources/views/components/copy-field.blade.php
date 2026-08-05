@props(['value', 'label' => null, 'masked' => false])
{{-- A read-only value with a copy button. Used for connect addresses, tokens
     and enrol commands: anything the user is going to select-and-copy anyway. --}}
<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)<label class="block text-sm font-medium text-slate-700">{{ $label }}</label>@endif
    <div x-data="{ shown: {{ $masked ? 'false' : 'true' }}, copied: false }" class="flex items-stretch gap-2">
        <div class="relative flex-1 min-w-0">
            <input type="text" readonly
                   :value="shown ? @js($value) : '{{ str_repeat('•', 24) }}'"
                   class="block w-full rounded-lg border-0 bg-slate-50 px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 font-mono">
        </div>
        @if ($masked)
            <button type="button" @click="shown = !shown"
                    class="inline-flex items-center justify-center px-3 rounded-lg text-slate-600 bg-white ring-1 ring-inset ring-slate-300 hover:bg-slate-50 hover:ring-slate-400 transition"
                    :aria-label="shown ? 'Hide' : 'Reveal'">
                <x-icon name="eye" class="w-4 h-4" />
            </button>
        @endif
        <button type="button"
                @click="navigator.clipboard.writeText(@js($value)); copied = true; setTimeout(() => copied = false, 1600)"
                class="inline-flex items-center gap-1.5 px-3 rounded-lg text-sm font-medium text-slate-700 bg-white ring-1 ring-inset ring-slate-300 hover:bg-slate-50 hover:ring-slate-400 transition">
            <x-icon name="copy" class="w-4 h-4" x-show="!copied" />
            <x-icon name="check" class="w-4 h-4 text-emerald-600" x-show="copied" x-cloak />
            <span x-text="copied ? 'Copied' : 'Copy'"></span>
        </button>
    </div>
</div>
