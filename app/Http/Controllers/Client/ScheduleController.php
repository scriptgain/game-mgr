<?php

namespace App\Http\Controllers\Client;

use App\Models\Schedule;
use App\Models\ScheduleTask;
use App\Models\Server;
use App\Support\Cron;
use Illuminate\Http\Request;

/**
 * Schedules with chained tasks. "Warn, wait five minutes, warn again, restart"
 * is one schedule, not four cron entries that drift apart over a month.
 */
class ScheduleController extends ServerController
{
    public function index(Server $server)
    {
        $this->guard($server, 'schedule.read');

        return view('server.schedules', [
            'title' => $server->name.' Schedules',
            'server' => $server->load('node'),
            'schedules' => $server->schedules()->with('tasks')->orderBy('name')->get(),
        ]);
    }

    public function create(Server $server)
    {
        $this->guard($server, 'schedule.create');

        return view('server.schedule-form', [
            'title' => 'New Schedule',
            'server' => $server->load('node'),
            'schedule' => new Schedule(['cron_minute' => '0', 'cron_hour' => '4', 'cron_day_of_month' => '*', 'cron_month' => '*', 'cron_day_of_week' => '*', 'is_active' => true]),
        ]);
    }

    public function store(Request $request, Server $server)
    {
        $this->guard($server, 'schedule.create');

        $data = $this->validated($request);
        $tasks = $data['tasks'];
        unset($data['tasks']);

        $schedule = $server->schedules()->create($data);
        $this->syncTasks($schedule, $tasks);

        $this->log($server, 'schedule.create', 'Created schedule "'.$schedule->name.'"');

        return redirect()->route('server.schedules', $server)->with('status', 'Schedule created.');
    }

    public function edit(Server $server, Schedule $schedule)
    {
        $this->guard($server, 'schedule.read');
        abort_unless($schedule->server_id === $server->id, 404);

        return view('server.schedule-form', [
            'title' => 'Edit '.$schedule->name,
            'server' => $server->load('node'),
            'schedule' => $schedule->load('tasks'),
        ]);
    }

    public function update(Request $request, Server $server, Schedule $schedule)
    {
        $this->guard($server, 'schedule.update');
        abort_unless($schedule->server_id === $server->id, 404);

        $data = $this->validated($request);
        $tasks = $data['tasks'];
        unset($data['tasks']);

        $schedule->update($data);
        $schedule->tasks()->delete();
        $this->syncTasks($schedule, $tasks);

        $this->log($server, 'schedule.update', 'Updated schedule "'.$schedule->name.'"');

        return redirect()->route('server.schedules', $server)->with('status', 'Schedule updated.');
    }

    public function run(Server $server, Schedule $schedule)
    {
        $this->guard($server, 'schedule.update');
        abort_unless($schedule->server_id === $server->id, 404);

        $schedule->update(['is_processing' => true, 'last_run_at' => now()]);
        $this->log($server, 'schedule.run', 'Ran schedule "'.$schedule->name.'" by hand');

        return back()->with('status', 'Schedule queued. Its first task starts within a minute.');
    }

    public function destroy(Server $server, Schedule $schedule)
    {
        $this->guard($server, 'schedule.delete');
        abort_unless($schedule->server_id === $server->id, 404);

        $name = $schedule->name;
        $schedule->delete();
        $this->log($server, 'schedule.delete', 'Deleted schedule "'.$name.'"');

        return back()->with('status', 'Schedule deleted.');
    }

    // ------------------------------------------------------------ internals

    private function syncTasks(Schedule $schedule, array $tasks): void
    {
        $sequence = 1;
        foreach ($tasks as $task) {
            if (blank($task['action'] ?? null)) {
                continue;
            }
            ScheduleTask::create([
                'schedule_id' => $schedule->id,
                'sequence' => $sequence++,
                'action' => $task['action'],
                'payload' => $task['payload'] ?? null,
                'time_offset' => (int) ($task['time_offset'] ?? 0),
                'continue_on_failure' => (bool) ($task['continue_on_failure'] ?? false),
            ]);
        }
    }

    private function validated(Request $request): array
    {
        $cronField = ['required', 'string', 'max:32', 'regex:/^[\d\*\/,\-]+$/'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'cron_minute' => $cronField,
            'cron_hour' => $cronField,
            'cron_day_of_month' => $cronField,
            'cron_month' => $cronField,
            'cron_day_of_week' => $cronField,
            'is_active' => ['nullable', 'boolean'],
            'only_when_online' => ['nullable', 'boolean'],
            'tasks' => ['nullable', 'array', 'max:12'],
            'tasks.*.action' => ['nullable', 'in:power,command,backup,update,webhook'],
            'tasks.*.payload' => ['nullable', 'string', 'max:1000'],
            'tasks.*.time_offset' => ['nullable', 'integer', 'between:0,86400'],
            'tasks.*.continue_on_failure' => ['nullable', 'boolean'],
        ]);

        // Validated as a whole expression, not field by field: the regex on
        // each field cannot tell that "0 5 32 * *" never matches anything.
        $expression = implode(' ', [
            $data['cron_minute'], $data['cron_hour'], $data['cron_day_of_month'],
            $data['cron_month'], $data['cron_day_of_week'],
        ]);
        if (! Cron::isValid($expression) || Cron::parse($expression)->nextRun() === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cron_minute' => 'That schedule never comes round. Check the day and month fields.',
            ]);
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['only_when_online'] = (bool) ($data['only_when_online'] ?? false);
        $data['tasks'] = $data['tasks'] ?? [];

        return $data;
    }
}
