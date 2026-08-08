{{-- One setting, rendered as the control its own rules describe.

     $variable  a TemplateVariable, or anything that quacks like one: the Config
                tab passes an App\Support\ConfigSetting, which carries the same
                id, name, description, env_variable, rules and control().

     Optional, with the admin create screen's behaviour as the default:
       $owner     template id. Every block but the chosen template's is
                  disabled, so only the live template's values post. Omit it
                  and nothing is ever disabled.
       $group     form field array name. Defaults to "variables".
       $value     current value. Defaults to old() then the declared default.
       $locked    render read only, whatever the rules say, for a setting an
                  administrator has taken away from the customer.

     Shape comes from control(), which reads the rules the server will validate
     against: an in: list is a choice, integer|between: is a bounded number,
     true/false is a switch. The control therefore cannot offer a value the
     server would then reject. --}}
@php
    $control = $variable->control();
    $id = $variable->id;
    $group = $group ?? 'variables';
    $field = $group.'['.$id.']';
    $value = (string) ($value ?? old($group.'.'.$id, $variable->default_value));
    $error = $errors->first($group.'.'.$id);
    $sleeping = isset($owner) ? "templateId !== '".$owner."'" : 'false';
    $locked = $locked ?? false;

    // A locked setting is shown as the value it holds and nothing else. The
    // fixed block already does exactly that, so there is one read only
    // presentation rather than a disabled copy of every control.
    if ($locked) {
        $control['type'] = 'fixed';
    }

    $wide = in_array($control['type'], ['switch', 'choice', 'select', 'textarea', 'fixed'], true);
@endphp

<div class="min-w-0 {{ $wide ? 'sm:col-span-2' : '' }}">
    @if ($control['type'] === 'switch')
        @php
            $on = $control['on'];
            $offValue = $control['off'];
        @endphp
        <div x-data="{ v: @js($value === $on ? $on : $offValue) }" class="flex items-start gap-3">
            <input type="hidden" name="{{ $field }}" x-model="v"
                   data-env="{{ $variable->env_variable }}" x-bind:disabled="{{ $sleeping }}">
            <button type="button" role="switch" :aria-checked="(v === @js($on)).toString()"
                    @click="v = v === @js($on) ? @js($offValue) : @js($on)"
                    :class="v === @js($on) ? 'bg-brand-600' : 'bg-slate-300'"
                    class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-2">
                <span :class="v === @js($on) ? 'translate-x-6' : 'translate-x-1'"
                      class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
            </button>
            <span class="min-w-0 text-sm">
                <span class="font-medium text-slate-900">{{ $variable->name }}</span>
                @if ($variable->description)
                    <span class="block text-slate-500">{{ $variable->description }}</span>
                @endif
                <span class="mt-1 block font-mono text-xs text-slate-400 break-words">
                    {{ $variable->env_variable }} = <span x-text="v">{{ $value }}</span>
                </span>
                @if ($error)<span class="mt-1 block text-sm text-rose-600">{{ $error }}</span>@endif
            </span>
        </div>

    @elseif ($control['type'] === 'fixed')
        <div class="rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-inset ring-slate-200">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <input type="hidden" name="{{ $field }}" value="{{ $value }}"
                       data-env="{{ $variable->env_variable }}" x-bind:disabled="{{ $sleeping }}">
                <span class="min-w-0 text-sm text-slate-700">
                    {{ $variable->name }}
                    <span class="ms-1 font-mono text-xs text-slate-400">{{ $variable->env_variable }}</span>
                </span>
                <span class="inline-flex items-center gap-1.5 text-sm">
                    <x-icon name="lock" class="w-3.5 h-3.5 text-slate-400" />
                    <span class="font-mono text-slate-900 break-words">{{ $value === '' ? 'not set' : $value }}</span>
                </span>
            </div>
            @if ($variable->description)
                <p class="mt-1 text-sm text-slate-500">{{ $variable->description }}</p>
            @endif
        </div>

    @else
        {{-- Not x-field: the environment variable name belongs beside its label,
             where it reads as a subtitle, rather than under the control where it
             would push the error message away from the box that caused it. --}}
        <div class="space-y-1.5">
            <div class="flex items-baseline justify-between gap-3">
                <label for="var-{{ $id }}" class="min-w-0 text-sm font-medium text-slate-700">
                    {{ $variable->name }}
                    @if ($variable->isRequired())<span class="text-rose-500">*</span>@endif
                </label>
                <span class="shrink-0 truncate font-mono text-[11px] text-slate-400">{{ $variable->env_variable }}</span>
            </div>

            @if ($control['type'] === 'choice')
                <div class="flex flex-wrap gap-1 rounded-lg bg-slate-100 p-1" role="group" aria-label="{{ $variable->name }}">
                    @foreach ($control['options'] as $option)
                        <label class="min-w-0 flex-1 basis-24">
                            <input type="radio" name="{{ $field }}" value="{{ $option }}" @checked($value === $option)
                                   data-env="{{ $variable->env_variable }}" x-bind:disabled="{{ $sleeping }}"
                                   class="peer sr-only">
                            <span class="block cursor-pointer truncate rounded-md px-2.5 py-1.5 text-center text-sm font-medium text-slate-600 transition
                                         hover:text-slate-900 peer-checked:bg-white peer-checked:text-brand-700 peer-checked:shadow-sm
                                         peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/60">
                                {{ \Illuminate\Support\Str::headline($option) }}
                            </span>
                        </label>
                    @endforeach
                </div>

            @elseif ($control['type'] === 'select')
                <div class="sm:max-w-sm">
                    <x-select :id="'var-'.$id" :name="$field" :data-env="$variable->env_variable"
                              x-bind:disabled="{{ $sleeping }}">
                        @foreach ($control['options'] as $option)
                            <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                        @endforeach
                    </x-select>
                </div>

            @elseif ($control['type'] === 'number')
                @php
                    $min = $control['min'];
                    $max = $control['max'];
                    $step = $control['step'];
                    $slider = $min !== null && $max !== null && ($max - $min) / $step <= 10000;
                    $seed = $value === '' || ! is_numeric($value) ? '' : $value + 0;
                @endphp
                <div x-data="{ v: @js($seed) }" class="flex items-center gap-3">
                    @if ($slider)
                        <input type="range" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" x-model.number="v"
                               aria-label="{{ $variable->name }}"
                               class="h-2 min-w-0 flex-1 cursor-pointer appearance-none rounded-full bg-slate-200 accent-brand-600">
                    @endif
                    {{-- value as well as x-model: the server rendered number is
                         then correct before Alpine boots, and correct at all if
                         the CDN never arrives. x-model writes back the same
                         number a tick later, so nothing flickers. --}}
                    <input type="number" id="var-{{ $id }}" name="{{ $field }}" x-model.number="v" step="{{ $step }}"
                           value="{{ $value }}"
                           data-env="{{ $variable->env_variable }}" x-bind:disabled="{{ $sleeping }}"
                           @if ($min !== null) min="{{ $min }}" @endif
                           @if ($max !== null) max="{{ $max }}" @endif
                           @if ($variable->isRequired()) required @endif
                           class="{{ $slider ? 'w-24 shrink-0' : 'w-full sm:max-w-40' }} block rounded-lg border-0 bg-white px-3 py-2 text-sm tabular text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                </div>

            @elseif ($control['type'] === 'textarea')
                <textarea id="var-{{ $id }}" name="{{ $field }}" rows="2"
                          data-env="{{ $variable->env_variable }}" x-bind:disabled="{{ $sleeping }}"
                          @if ($control['maxlength']) maxlength="{{ $control['maxlength'] }}" @endif
                          @if ($variable->isRequired()) required @endif
                          class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ $value }}</textarea>

            @elseif ($control['type'] === 'secret')
                <div class="flex items-center gap-2">
                    <x-input :id="'var-'.$id" :name="$field" :value="$value" class="font-mono"
                             :data-env="$variable->env_variable" x-bind:disabled="{{ $sleeping }}"
                             :required="$variable->isRequired()"
                             :maxlength="$control['maxlength'] ?: 64"
                             :minlength="$control['minlength']"
                             :placeholder="$variable->isRequired() ? null : 'Blank for none'" />
                    {{-- Plain interpolation, not @js: a Blade directive inside a
                         COMPONENT attribute is passed through as literal text,
                         so @js() here would reach the browser uncompiled. --}}
                    <x-button type="button" variant="secondary" size="sm" class="shrink-0"
                              @click="generateSecret('{{ $field }}')">Generate</x-button>
                </div>

            @else
                <x-input :id="'var-'.$id" :name="$field" :value="$value"
                         :data-env="$variable->env_variable" x-bind:disabled="{{ $sleeping }}"
                         :required="$variable->isRequired()"
                         :maxlength="$control['maxlength']" />
            @endif

            @if ($error)
                <p class="text-sm text-rose-600">{{ $error }}</p>
            @elseif ($variable->description)
                <p class="text-sm text-slate-500">{{ $variable->description }}</p>
            @endif
        </div>
    @endif
</div>
