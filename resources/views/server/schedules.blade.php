<x-layouts.app :title="$title">
    @include('server._shell', ['server' => $server])

    <x-card title="Schedules"
            subtitle="Tasks chain, so warn, wait, warn again, restart is one schedule rather than four cron entries that drift apart."
            flush>
        <x-slot:actions>
            @can('check', [$server, 'schedule.create'])
                <x-button href="{{ route('server.schedules.create', $server) }}" size="sm" icon="plus">New Schedule</x-button>
            @endcan
        </x-slot:actions>

        @if ($schedules->isEmpty())
            <x-empty-state icon="clock" title="No Schedules"
                           description="A nightly restart and a daily backup are what most servers want. Set them once and forget them.">
                <x-slot:action>
                    @can('check', [$server, 'schedule.create'])
                        <x-button href="{{ route('server.schedules.create', $server) }}" icon="plus">New Schedule</x-button>
                    @endcan
                </x-slot:action>
            </x-empty-state>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($schedules as $schedule)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-medium text-slate-900">{{ $schedule->name }}</span>
                                    @if ($schedule->is_active)
                                        <x-badge color="success" dot>Active</x-badge>
                                    @else
                                        <x-badge color="neutral" dot>Paused</x-badge>
                                    @endif
                                    @if ($schedule->only_when_online)
                                        <x-badge color="neutral">Only When Online</x-badge>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $schedule->humanCron() }}
                                    <span class="font-mono text-xs text-slate-400">({{ $schedule->cron() }})</span>
                                </p>
                                <p class="text-xs text-slate-400">
                                    Last run {{ $schedule->last_run_at?->diffForHumans() ?? 'never' }},
                                    next {{ $schedule->next_run_at?->diffForHumans() ?? 'not scheduled' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                @can('check', [$server, 'schedule.update'])
                                    <form method="POST" action="{{ route('server.schedules.run', [$server, $schedule]) }}">
                                        @csrf<x-icon-button type="submit" icon="play" title="Run It Now" />
                                    </form>
                                    <x-icon-button href="{{ route('server.schedules.edit', [$server, $schedule]) }}" icon="edit" title="Edit Schedule" />
                                @endcan
                                @can('check', [$server, 'schedule.delete'])
                                    <x-delete-button
                                        name="delete-schedule-{{ $schedule->id }}"
                                        :action="route('server.schedules.destroy', [$server, $schedule])"
                                        title="Delete {{ $schedule->name }}?"
                                        message="The schedule and all of its tasks are removed. Anything it was keeping on top of stops happening."
                                        label="Delete Schedule" />
                                @endcan
                            </div>
                        </div>

                        @if ($schedule->tasks->isNotEmpty())
                            <ol class="mt-3 space-y-1.5">
                                @foreach ($schedule->tasks as $task)
                                    <li class="flex items-center gap-3 text-sm">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 text-xs font-semibold text-slate-600 shrink-0">{{ $task->sequence }}</span>
                                        <span class="text-slate-700 min-w-0 truncate">{{ $task->describe() }}</span>
                                        @if ($task->time_offset > 0)
                                            <span class="text-xs text-slate-400 shrink-0">after {{ \App\Support\Format::duration($task->time_offset) }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
</x-layouts.app>
