<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="shield"
                   subtitle="A watchdog rule watches for one condition and does one thing about it, so a server that dies at three in the morning is back before anybody notices." />

    {{-- No max-w here. The layout already sets the page width from
         config('gamemgr.max_width'), and a second cap inside it renders a
         narrow column stranded in the middle of a wide screen. --}}
    <form method="POST" action="{{ $rule->exists ? route('admin.watchdog.update', $rule) : route('admin.watchdog.store') }}"
          x-data="{ trigger: @js(old('trigger', $rule->trigger ?? 'crash')) }">
        @csrf
        @if ($rule->exists)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3 items-start">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="The Rule">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Name" required :error="$errors->first('name')">
                                <x-input name="name" value="{{ old('name', $rule->name) }}" required placeholder="Restart On Crash" />
                            </x-field>
                            <x-field label="Applies To" :error="$errors->first('server_id')"
                                     hint="Leave on every server unless this is specific to one.">
                                <x-select name="server_id">
                                    <option value="">Every Server</option>
                                    @foreach ($servers as $server)
                                        <option value="{{ $server->id }}" @selected(old('server_id', $rule->server_id) == $server->id)>{{ $server->name }}</option>
                                    @endforeach
                                </x-select>
                            </x-field>
                        </div>
                    </div>
                </x-card>

                <x-card title="What To Watch For" subtitle="One condition. Build a second rule rather than trying to make one cover two things.">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Trigger" required :error="$errors->first('trigger')">
                                <x-select name="trigger" x-model="trigger">
                                    @foreach (\App\Models\WatchdogRule::TRIGGERS as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </x-select>
                            </x-field>

                            <x-field label="Grace Period (seconds)" required :error="$errors->first('grace_seconds')"
                                     hint="How long the condition must hold before acting, and the minimum gap between firings.">
                                <x-input type="number" name="grace_seconds" value="{{ old('grace_seconds', $rule->grace_seconds ?? 60) }}" required />
                            </x-field>
                        </div>

                        <div x-show="trigger === 'log_pattern'" x-cloak>
                            <x-field label="Pattern" hint="A regular expression matched against each console line." :error="$errors->first('pattern')">
                                <x-input name="pattern" value="{{ old('pattern', $rule->pattern) }}" class="font-mono text-xs"
                                         placeholder="(Chunk file at .* is missing|corrupt)" />
                            </x-field>
                        </div>

                        <div x-show="['memory', 'tick_rate'].includes(trigger)" x-cloak>
                            <x-field label="Threshold" :error="$errors->first('threshold')"
                                     hint="Memory is a percentage of the limit. Tick rate is the floor before it counts.">
                                <x-input type="number" name="threshold" value="{{ old('threshold', $rule->threshold ?? 0) }}" class="sm:w-40" />
                            </x-field>
                        </div>
                    </div>
                </x-card>

                <x-card title="What To Do About It">
                    <div class="space-y-5">
                        {{-- Half width via the grid, not a max-w on the select itself:
                             x-select draws its chevron against a full width wrapper, so
                             shrinking the select alone leaves the chevron stranded. --}}
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field label="Action" required :error="$errors->first('action')"
                                     hint="Alert Only is the safe starting point: watch it fire for a week before letting it restart anything.">
                                <x-select name="action">
                                    @foreach (\App\Models\WatchdogRule::ACTIONS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('action', $rule->action) === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-select>
                            </x-field>
                        </div>

                        @if ($channels->isNotEmpty())
                            <div>
                                <p class="text-sm font-medium text-slate-700 mb-3">Tell These Channels</p>
                                @php $selected = (array) old('channels', $rule->channels ?? []); @endphp
                                <div class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                                    @foreach ($channels as $channel)
                                        <x-check-switch name="channels[]" :value="$channel->id"
                                                        :checked="in_array($channel->id, $selected)">{{ $channel->name }} ({{ $channel->typeLabel() }})</x-check-switch>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Status">
                    <div class="space-y-5">
                        <x-toggle name="is_active" :checked="(bool) old('is_active', $rule->is_active ?? true)"
                                  label="Active" description="Paused rules are kept but never evaluated." />
                        @if ($rule->exists)
                            <p class="text-sm text-slate-500">
                                Last fired {{ $rule->last_fired_at?->diffForHumans() ?? 'never' }}.
                            </p>
                        @endif
                    </div>
                </x-card>

                <x-card>
                    <div class="flex flex-col gap-2">
                        <x-button type="submit" class="w-full">{{ $rule->exists ? 'Save Rule' : 'Create Rule' }}</x-button>
                        <x-button href="{{ route('admin.watchdog.index') }}" variant="secondary" class="w-full">Cancel</x-button>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
