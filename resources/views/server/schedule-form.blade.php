<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    @php $editing = $schedule->exists; @endphp

    <form method="POST" action="{{ $editing ? route('server.schedules.update', [$server, $schedule]) : route('server.schedules.store', $server) }}">
        @csrf
        @if ($editing)@method('PUT')@endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Schedule" icon="clock">
                    <div class="space-y-4">
                        <x-field label="Name" required :error="$errors->first('name')">
                            <x-input name="name" value="{{ old('name', $schedule->name) }}" required placeholder="Nightly Restart" />
                        </x-field>

                        <div>
                            <p class="text-sm font-medium text-slate-700 mb-1.5">When It Runs</p>
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                                @foreach ([
                                    'cron_minute' => 'Minute',
                                    'cron_hour' => 'Hour',
                                    'cron_day_of_month' => 'Day',
                                    'cron_month' => 'Month',
                                    'cron_day_of_week' => 'Weekday',
                                ] as $field => $label)
                                    <x-field :label="$label">
                                        <x-input name="{{ $field }}" value="{{ old($field, $schedule->$field ?? '*') }}" class="font-mono text-center" />
                                    </x-field>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-slate-500">
                                Standard cron fields. Leave a field as * to mean "every". 0 5 * * * is 05:00 daily.
                            </p>
                        </div>
                    </div>
                </x-card>

                <x-card title="Tasks" icon="play" subtitle="They run in order. The offset is how long to wait after the previous one finishes.">
                    <div class="space-y-4">
                        @php
                            $existing = $schedule->exists ? $schedule->tasks : collect();
                            $rows = max(3, $existing->count() + 1);
                        @endphp
                        @for ($i = 0; $i < $rows; $i++)
                            @php $task = $existing[$i] ?? null; @endphp
                            <div class="grid gap-2 sm:grid-cols-12 items-start rounded-lg bg-slate-50 p-3 ring-1 ring-inset ring-slate-200">
                                <div class="sm:col-span-4">
                                    <x-field label="{{ $i === 0 ? 'Action' : '' }}">
                                        <x-select name="tasks[{{ $i }}][action]">
                                            <option value="">Nothing</option>
                                            @foreach (\App\Models\ScheduleTask::ACTIONS as $value => $label)
                                                <option value="{{ $value }}" @selected($task?->action === $value)>{{ $label }}</option>
                                            @endforeach
                                        </x-select>
                                    </x-field>
                                </div>
                                <div class="sm:col-span-5">
                                    <x-field label="{{ $i === 0 ? 'Payload' : '' }}">
                                        <x-input name="tasks[{{ $i }}][payload]" value="{{ $task?->payload }}" placeholder="say Restarting in 5 minutes" />
                                    </x-field>
                                </div>
                                <div class="sm:col-span-3">
                                    <x-field label="{{ $i === 0 ? 'Wait (seconds)' : '' }}">
                                        <x-input type="number" min="0" name="tasks[{{ $i }}][time_offset]" value="{{ $task?->time_offset ?? 0 }}" />
                                    </x-field>
                                </div>
                            </div>
                        @endfor
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Behaviour" icon="settings">
                    <div class="space-y-4">
                        <x-toggle name="is_active" :checked="(bool) old('is_active', $schedule->is_active ?? true)"
                                  label="Active" description="Paused schedules keep their tasks but never fire." />
                        <x-toggle name="only_when_online" :checked="(bool) old('only_when_online', $schedule->only_when_online ?? false)"
                                  label="Only When Online" description="Stops a nightly restart from starting a server you deliberately stopped." />
                    </div>
                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-2">
                            <x-button href="{{ route('server.schedules', $server) }}" variant="secondary" size="sm">Cancel</x-button>
                            <x-button type="submit" size="sm">{{ $editing ? 'Save Schedule' : 'Create Schedule' }}</x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </div>
        </div>
    </form>
</x-layouts.app>
