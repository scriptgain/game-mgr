{{-- The Minecraft server type, version and build picker.

     Rendered instead of the plain text boxes for the three or four variables
     the template's `mcjars` document names, and only for templates that carry
     one. Everything else on the template still renders through
     _variable.blade.php exactly as before.

     Required:
       $picker   App\Support\McJarsPicker for this template
       $mc       $picker->payload(...), the array the browser drives itself from

     Optional, matching _variable.blade.php:
       $group    form field array name. Defaults to "variables".
       $owner    template id. Every block but the chosen template's is disabled,
                 so only the live template's values post.

     When MCJars could not be reached, $mc['available'] is false and this
     renders a note and nothing else. The caller then leaves the type, version
     and build variables in its own loop, where they render as the plain text
     boxes the panel shipped before any of this existed. A third party being
     down costs an operator a dropdown, never a server. --}}
@php
    $group = $group ?? 'variables';
    $owner = $owner ?? null;
    $sleeping = $owner !== null ? "templateId !== '".$owner."'" : 'false';
    $island = 'mcjars-data-'.$picker->template->id.'-'.$group;
@endphp

@if (! $mc['available'])
    <div class="min-w-0 sm:col-span-2">
        <x-alert type="info" title="Live Version List Unavailable">
            The MCJars catalogue did not answer, so the server type and version stay plain text boxes for now.
            Whatever is typed is handed straight to the container, which resolves it itself, so nothing is blocked.
        </x-alert>
    </div>
@else
    <div class="min-w-0 sm:col-span-2" x-data="minecraftPicker('{{ $island }}')">
        {{-- The hidden inputs are the only named fields here. Every visible
             control writes into the same Alpine state they are bound to, so the
             posted payload is identical in shape to the one the plain text
             boxes sent: variables[<id>] = value, nothing new for the controller
             to learn.

             value as well as x-model, the same pairing _variable.blade.php uses
             on its number control. The server rendered value is then right
             before Alpine boots, and right at all if the CDN never arrives:
             without it, a page that failed to load Alpine would post an empty
             VERSION and fail validation on a field with no control to fix it. --}}
        <input type="hidden" name="{{ $group }}[{{ $picker->typeVariable->id }}]" x-model="type"
               value="{{ $mc['type'] }}"
               data-env="{{ $picker->typeVariable->env_variable }}" x-bind:disabled="{{ $sleeping }}">
        <input type="hidden" name="{{ $group }}[{{ $picker->versionVariable->id }}]" x-model="version"
               value="{{ $mc['version'] }}"
               data-env="{{ $picker->versionVariable->env_variable }}" x-bind:disabled="{{ $sleeping }}">
        {{-- Every build variable keeps posting, not just the active type's.
             They are separate fields on the template with their own rules, and
             blanking the Forge version because somebody switched to Fabric
             would fail validation on a box nobody can see. --}}
        @foreach ($picker->buildVariables as $buildVariable)
            @if ($buildVariable)
                <input type="hidden" name="{{ $group }}[{{ $buildVariable->id }}]"
                       x-model="buildValues['{{ $buildVariable->id }}']"
                       value="{{ $mc['builds'][$buildVariable->id] ?? '' }}"
                       data-env="{{ $buildVariable->env_variable }}" x-bind:disabled="{{ $sleeping }}">
            @endif
        @endforeach

        <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-inset ring-slate-200 transition hover:ring-slate-300">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h4 class="text-sm font-semibold text-slate-900">Server Software</h4>
                <p class="text-xs text-slate-500">
                    Live from <span class="font-medium text-slate-600">MCJars</span>
                </p>
            </div>
            <p class="mt-1 text-sm text-slate-500">
                The container downloads whatever is chosen here on its next start. Nothing is fetched by the panel.
            </p>

            <div class="mt-4 grid grid-cols-1 gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                {{-- ------------------------------------------------- type --}}
                <div class="min-w-0">
                    <div class="flex items-baseline justify-between gap-3">
                        <label for="mc-type-{{ $island }}" class="min-w-0 text-sm font-medium text-slate-700">Server Type</label>
                        <span class="shrink-0 truncate font-mono text-[11px] text-slate-400">{{ $picker->typeVariable->env_variable }}</span>
                    </div>
                    <div class="mt-1.5">
                        <x-select :id="'mc-type-'.$island" x-model="type">
                            @foreach ($mc['types'] as $option)
                                <option value="{{ $option['code'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500" x-text="typeNote()"></p>
                </div>

                {{-- ---------------------------------------------- version --}}
                <div class="min-w-0">
                    <div class="flex items-baseline justify-between gap-3">
                        <label for="mc-version-{{ $island }}" class="min-w-0 text-sm font-medium text-slate-700">Minecraft Version</label>
                        <span class="shrink-0 truncate font-mono text-[11px] text-slate-400">{{ $picker->versionVariable->env_variable }}</span>
                    </div>
                    <div class="mt-1.5">
                        {{-- A select while the list is known, a text box the
                             moment it is not. Same field either way. --}}
                        <div x-show="versionsUsable()">
                            <x-select :id="'mc-version-'.$island" x-model="version"
                                      x-bind:disabled="loadingVersions">
                                <template x-for="row in visibleVersions()" :key="row.id">
                                    <option :value="row.id" x-text="versionLabel(row)"></option>
                                </template>
                            </x-select>
                        </div>
                        <div x-show="!versionsUsable()" x-cloak>
                            <input type="text" x-model="version" maxlength="40"
                                   placeholder="LATEST"
                                   class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs" :class="versionsFailed ? 'text-amber-600' : 'text-slate-500'"
                       x-text="versionNote()"></p>
                </div>

                {{-- ------------------------------------------------ build --}}
                <div class="min-w-0" x-show="hasBuild()" x-cloak>
                    <div class="flex items-baseline justify-between gap-3">
                        <label for="mc-build-{{ $island }}" class="min-w-0 text-sm font-medium text-slate-700"
                               x-text="buildLabel()">Build</label>
                        <span class="shrink-0 truncate font-mono text-[11px] text-slate-400" x-text="buildEnv()"></span>
                    </div>
                    <div class="mt-1.5">
                        {{-- "Newest" is a real, static option with a real empty
                             value: bound through x-for it would keep no value
                             attribute and post its own label instead. The rest
                             is one x-for over one list, because a fixed option
                             plus an x-if plus an x-for meant the pinned build
                             was removed the moment the fetched list arrived,
                             and a select loses its value when its options go. --}}
                        <x-select :id="'mc-build-'.$island" x-model="build"
                                  @focus="loadBuilds()" @mousedown="loadBuilds()">
                            <option value="">Newest Build</option>
                            <template x-for="row in buildChoices()" :key="row.value">
                                <option :value="row.value" x-text="buildOptionLabel(row)"></option>
                            </template>
                        </x-select>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500" x-text="buildNote()"></p>
                </div>
            </div>

            {{-- Snapshots are off by default and behind a switch, never a bare
                 checkbox. Most people want the release list; the handful who
                 are testing a pre-release still get there in one click. --}}
            <div class="section-divider mt-4 pt-3" x-show="hasSnapshots()" x-cloak>
                <div class="flex items-start gap-3">
                    <button type="button" role="switch" :aria-checked="snapshots.toString()"
                            @click="snapshots = !snapshots"
                            :class="snapshots ? 'bg-brand-600' : 'bg-slate-300'"
                            class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-2">
                        <span :class="snapshots ? 'translate-x-6' : 'translate-x-1'"
                              class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"></span>
                    </button>
                    <span class="min-w-0 text-sm">
                        <span class="font-medium text-slate-900">Show Snapshots And Pre-Releases</span>
                        <span class="block text-slate-500">
                            Off by default. Snapshot builds are for testing and are not expected to survive an upgrade.
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- A JSON island rather than a giant x-data attribute: the escaping is
         somebody else's problem and the markup stays readable. --}}
    <script type="application/json" id="{{ $island }}">@json($mc)</script>
@endif
