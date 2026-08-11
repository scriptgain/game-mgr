@php
    /** One template's settings, rendered on demand.
     *
     * This used to be a loop over EVERY template with x-show hiding all but
     * one. At nine templates that was merely wasteful; at two hundred and
     * fifty nine it was a 5.7 MB page that took 1.6 seconds to send, which is
     * the first thing anybody would hit after importing the catalogue and
     * would read as the import having broken the panel.
     *
     * Rendered by the same Blade either way, so the markup, the MCJars picker
     * and the locked-defaults section stay in one place.
     */
@endphp
@php
    // A Minecraft template draws its type, version
    // and build through the MCJars picker instead
    // of as three text boxes, so those variables
    // come out of the generic loop. When MCJars is
    // unreachable the picker owns nothing and they
    // go back to being text boxes, which is exactly
    // what this screen did before.
    $picker = $minecraft[$template->id]['picker'] ?? null;
    $mc = $minecraft[$template->id]['payload'] ?? null;
    $owned = $picker && $mc['available'] ? $picker->ownedVariableIds() : [];

    $editable = $template->variables
        ->filter(fn ($v) => $v->user_editable && ! in_array($v->id, $owned, true));
    // NOT $locked. _variable.blade.php reads a
    // $locked flag to decide whether to render a
    // setting read only, and @include shares the
    // including scope, so a collection called
    // $locked here made every control on this step
    // a padlock and a value.
    $lockedVars = $template->variables
        ->reject(fn ($v) => $v->user_editable || in_array($v->id, $owned, true));
@endphp
{{-- Only this template's inputs are on the page, so there is nothing to
     hide and nothing to disable. x-show stays because a swap in flight
     briefly leaves the previous template's block mounted, and $loop is
     gone with the loop that used to provide it. --}}
<div data-vars="{{ $template->id }}" x-show="templateId === '{{ $template->id }}'">
    @if ($template->variables->isEmpty())
        <x-empty-state icon="bolt" title="Nothing To Configure"
                       description="This template exposes no settings. Move straight on to the review." />
    @else
        <div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
            @if ($picker)
                @include('admin.servers._minecraft', [
                    'picker' => $picker,
                    'mc' => $mc,
                    'owner' => $template->id,
                    'group' => 'variables',
                ])
            @endif
            @foreach ($editable as $variable)
                @include('admin.servers._variable', ['variable' => $variable, 'owner' => $template->id])
            @endforeach
        </div>

        @if ($lockedVars->isNotEmpty())
            {{-- Open state lives in the wizard, not here: a failed submit
                 has to be able to reveal whatever it could not focus. --}}
            <div class="section-divider mt-6 pt-5">
                <button type="button" @click="showLocked = !showLocked"
                        class="inline-flex items-center gap-1.5 rounded-lg text-sm font-medium text-slate-600 transition hover:text-slate-900">
                    <x-icon name="chevron-down" class="w-4 h-4 transition-transform"
                            ::class="showLocked && 'rotate-180'" />
                    <span x-text="showLocked ? 'Hide Template Defaults' : 'Show Template Defaults'">Show Template Defaults</span>
                    <x-badge>{{ $lockedVars->count() }}</x-badge>
                </button>
                <p class="mt-1.5 text-sm text-slate-500">
                    Settings the template keeps to itself. The owner never sees them, and they are
                    usually right as they are.
                </p>
                <div x-show="showLocked" x-cloak class="mt-4 grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                    @foreach ($lockedVars as $variable)
                        @include('admin.servers._variable', ['variable' => $variable, 'owner' => $template->id])
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
