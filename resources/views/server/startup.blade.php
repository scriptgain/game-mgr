<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <form method="POST" action="{{ route('server.startup.update', $server) }}">
        @csrf @method('PUT')

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <x-card title="Startup Variables"
                        subtitle="These are baked into the startup command. Most need a restart before they take effect.">
                    @if ($variables->isEmpty())
                        <x-empty-state icon="bolt" title="Nothing To Configure"
                                       description="This template exposes no variables you are allowed to change." />
                    @else
                        {{-- A Minecraft template draws its type, version and
                             build through the MCJars picker and drops them from
                             the list below. When MCJars is unreachable the
                             picker owns nothing, the partial prints a note, and
                             all three stay as the text boxes they always were. --}}
                        @php
                            $owned = $picker && $mc['available'] ? $picker->ownedVariableIds() : [];
                        @endphp

                        @if ($picker)
                            <div class="mb-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                                @include('admin.servers._minecraft', [
                                    'picker' => $picker,
                                    'mc' => $mc,
                                    'group' => 'variables',
                                ])
                            </div>
                        @endif

                        <div class="space-y-5">
                            @foreach ($variables as $variable)
                                @continue(in_array($variable->id, $owned, true))
                                @php
                                    $locked = ! $isAdmin && ! $variable->user_editable;
                                    $value = old('variables.'.$variable->id, $values[$variable->id] ?? $variable->default_value);
                                @endphp
                                <x-field :label="$variable->name" :hint="$variable->description"
                                         :error="$errors->first('variables.'.$variable->id)">
                                    <x-input name="variables[{{ $variable->id }}]" value="{{ $value }}" :disabled="$locked" />
                                    <p class="text-xs text-slate-400 font-mono">
                                        {{ $variable->env_variable }}
                                        @if ($locked) &middot; set by an administrator @endif
                                        @unless ($variable->user_viewable) &middot; hidden from clients @endunless
                                    </p>
                                </x-field>
                            @endforeach
                        </div>
                    @endif

                    <x-slot:footer>
                        <div class="flex items-center justify-end">
                            <x-button type="submit" size="sm">Save Variables</x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Startup Command">
                    @if ($isAdmin)
                        <div class="space-y-4">
                            <x-field label="Docker Image" hint="Only used by the Docker runtime.">
                                <x-input name="image" value="{{ old('image', $server->image) }}" />
                            </x-field>
                            <x-field label="Command">
                                <textarea name="startup" rows="4"
                                          class="block w-full rounded-lg border-0 bg-white px-3 py-2 text-sm font-mono text-slate-900 ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('startup', $server->startup) }}</textarea>
                            </x-field>
                        </div>
                    @else
                        <pre class="console-pane vx-scroll p-3 overflow-x-auto text-xs whitespace-pre-wrap break-words">{{ $server->startup }}</pre>
                        <p class="mt-2 text-xs text-slate-500">Only an administrator can change the command itself.</p>
                    @endif
                </x-card>

                <x-card title="Runtime">
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500">Runtime</span>
                            <x-runtime-badge :runtime="$server->runtime" />
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500">Template</span>
                            <span class="text-slate-900 truncate">{{ $server->template?->name }}</span>
                        </div>
                        @if ($server->template?->steam_app_id)
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-slate-500">Steam App</span>
                                <span class="text-slate-900 tabular">{{ $server->template->steam_app_id }}</span>
                            </div>
                        @endif
                        @if ($server->template?->lgsm_shortname)
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-slate-500">LinuxGSM</span>
                                <span class="text-slate-900 font-mono text-xs">{{ $server->template->lgsm_shortname }}</span>
                            </div>
                        @endif
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
