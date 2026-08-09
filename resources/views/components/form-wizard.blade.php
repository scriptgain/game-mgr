@props([
    // [['label' => 'Identity', 'hint' => 'What it is called', 'icon' => 'book'], ...]
    'steps',
    // Free navigation when editing, forward-only when creating.
    'editing' => false,
    // Which step to open on. The form passes the step holding the first
    // validation error, so a failed save reopens what actually failed.
    'start' => 1,
    'component' => 'formWizard',
])
@php
    $total = count($steps);
@endphp

{{-- Steps hide with x-show, so without this the whole form is visible for the
     instant before Alpine starts and the page jumps. --}}
<style>
    [x-cloak] { display: none !important; }
    @media (prefers-reduced-motion: reduce) {
        .gm-step, .gm-rail { transition-duration: 0.01ms !important; animation-duration: 0.01ms !important; }
    }
</style>

<div x-data="{{ $component }}({ total: {{ $total }}, step: {{ (int) $start }}, editing: {{ $editing ? 'true' : 'false' }} })"
     @keydown.enter="onEnter($event)"
     class="grid gap-6 lg:grid-cols-4 items-start">

    <div class="lg:col-span-3 space-y-6">
        {{ $slot }}
    </div>

    <div class="lg:col-span-1">
        <div class="gm-rail rounded-xl bg-white p-4 ring-1 ring-inset ring-slate-200 shadow-sm lg:sticky lg:top-6">
            <div class="mb-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-brand-500 transition-all duration-300" :style="progress()"></div>
            </div>

            <ol class="space-y-0.5">
                @foreach ($steps as $i => $step)
                    @php $n = $i + 1; @endphp
                    <li>
                        {{-- Disabled rather than hidden: knowing a step exists and
                             is not reachable yet is more use than not knowing it
                             is there at all. --}}
                        <button type="button" @click="go({{ $n }})" :disabled="! unlocked({{ $n }})"
                                class="flex w-full items-start gap-2.5 rounded-lg border border-transparent px-2.5 py-2 text-left transition"
                                :class="step === {{ $n }}
                                    ? 'bg-brand-50 border-brand-200'
                                    : (unlocked({{ $n }})
                                        ? 'hover:border-slate-200 hover:bg-slate-50 cursor-pointer'
                                        : 'cursor-not-allowed')">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold ring-1 ring-inset transition"
                                  :class="step === {{ $n }}
                                      ? 'bg-brand-600 text-white ring-brand-600'
                                      : (step > {{ $n }}
                                          ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                          : 'bg-white text-slate-400 ring-slate-200')">
                                <span x-show="step <= {{ $n }}">{{ $n }}</span>
                                <span x-show="step > {{ $n }}" x-cloak>&check;</span>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium"
                                      :class="step === {{ $n }} ? 'text-brand-700' : (unlocked({{ $n }}) ? 'text-slate-700' : 'text-slate-400')">{{ $step['label'] }}</span>
                                @if (! empty($step['hint']))
                                    <span class="block truncate text-xs"
                                          :class="unlocked({{ $n }}) ? 'text-slate-400' : 'text-slate-300'">{{ $step['hint'] }}</span>
                                @endif
                            </span>
                        </button>
                    </li>
                @endforeach
            </ol>

            {{-- Editing means every step is reachable, so the save must be too,
                 without walking to the end to find it. --}}
            <div class="mt-3 border-t border-slate-100 pt-3" x-show="editing" x-cloak>
                {{ $save ?? '' }}
            </div>
        </div>
    </div>
</div>
