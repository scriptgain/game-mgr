<x-layouts.app :title="$title">
    <x-page-header :title="$title" icon="shield" />

    <form method="POST" action="{{ $rule->exists ? route('admin.watchdog.update', $rule) : route('admin.watchdog.store') }}"
          class="max-w-3xl" x-data="{ trigger: @js(old('trigger', $rule->trigger ?? 'crash')) }">
        @csrf
        @if ($rule->exists)@method('PUT')@endif

        <x-card title="Rule">
            <div class="space-y-4">
                <x-field label="Name" required :error="$errors->first('name')">
                    <x-input name="name" value="{{ old('name', $rule->name) }}" required placeholder="Restart On Crash" />
                </x-field>

                <x-field label="Applies To" hint="Leave on every server unless this is specific to one.">
                    <x-select name="server_id">
                        <option value="">Every Server</option>
                        @foreach ($servers as $server)
                            <option value="{{ $server->id }}" @selected(old('server_id', $rule->server_id) == $server->id)>{{ $server->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field label="Trigger" required>
                    <x-select name="trigger" x-model="trigger">
                        @foreach (\App\Models\WatchdogRule::TRIGGERS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <div x-show="trigger === 'log_pattern'" x-cloak>
                    <x-field label="Pattern" hint="A regular expression matched against each console line." :error="$errors->first('pattern')">
                        <x-input name="pattern" value="{{ old('pattern', $rule->pattern) }}" class="font-mono text-xs"
                                 placeholder="(Chunk file at .* is missing|corrupt)" />
                    </x-field>
                </div>

                <div x-show="['memory', 'tick_rate'].includes(trigger)" x-cloak>
                    <x-field label="Threshold" hint="Memory is a percentage of the limit. Tick rate is the floor before it counts.">
                        <x-input type="number" name="threshold" value="{{ old('threshold', $rule->threshold ?? 0) }}" class="sm:w-40" />
                    </x-field>
                </div>

                <x-field label="Grace Period (seconds)" required
                         hint="How long the condition must hold before acting, and the minimum gap between firings.">
                    <x-input type="number" name="grace_seconds" value="{{ old('grace_seconds', $rule->grace_seconds ?? 60) }}" required class="sm:w-40" />
                </x-field>

                <x-field label="Action" required>
                    <x-select name="action">
                        @foreach (\App\Models\WatchdogRule::ACTIONS as $value => $label)
                            <option value="{{ $value }}" @selected(old('action', $rule->action) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                @if ($channels->isNotEmpty())
                    <div>
                        <p class="text-sm font-medium text-slate-700 mb-2">Tell These Channels</p>
                        @php $selected = (array) old('channels', $rule->channels ?? []); @endphp
                        <div class="space-y-2">
                            @foreach ($channels as $channel)
                                <label class="flex items-center gap-2.5 cursor-pointer">
                                    <input type="checkbox" name="channels[]" value="{{ $channel->id }}"
                                           @checked(in_array($channel->id, $selected))
                                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    <span class="text-sm text-slate-700">{{ $channel->name }} <span class="text-slate-400">({{ $channel->typeLabel() }})</span></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <x-toggle name="is_active" :checked="(bool) old('is_active', $rule->is_active ?? true)"
                          label="Active" description="Paused rules are kept but never evaluated." />
            </div>

            <x-slot:footer>
                <div class="flex items-center justify-end gap-2">
                    <x-button href="{{ route('admin.watchdog.index') }}" variant="secondary" size="sm">Cancel</x-button>
                    <x-button type="submit" size="sm">{{ $rule->exists ? 'Save Rule' : 'Create Rule' }}</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
</x-layouts.app>
