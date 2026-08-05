<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Support\Cron;
use Illuminate\Console\Command;

/**
 * Fire schedules whose next run has arrived.
 *
 * Tasks chain rather than all firing at once, so a "warn, wait, warn, restart"
 * sequence unfolds over minutes. This command only marks a schedule as due; the
 * queue walks the chain.
 */
class SchedulesDispatchDue extends Command
{
    protected $signature = 'schedules:dispatch-due';

    protected $description = 'Queue any server schedule that is now due';

    public function handle(): int
    {
        $due = Schedule::with('server', 'tasks')
            ->where('is_active', true)
            ->where('is_processing', false)
            ->where(function ($q) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->get();

        $fired = 0;

        foreach ($due as $schedule) {
            // only_when_online exists so a nightly restart does not start a
            // server the owner deliberately stopped.
            if ($schedule->only_when_online && $schedule->server?->power_state !== 'running') {
                $schedule->update(['next_run_at' => $this->nextRun($schedule)]);

                continue;
            }

            $schedule->update([
                'is_processing' => true,
                'last_run_at' => now(),
                'next_run_at' => $this->nextRun($schedule),
            ]);
            $schedule->tasks()->update(['is_queued' => true]);
            $fired++;
        }

        $this->info($fired.' of '.$due->count().' due schedules queued.');

        return self::SUCCESS;
    }

    /**
     * The next moment this schedule is due, honouring all five cron fields.
     *
     * The previous version read only the hour and minute, so a schedule set to
     * Tuesdays fired every day. Anything the matcher cannot parse falls back to
     * an hour from now rather than never running, because a schedule that
     * silently stops is harder to notice than one that runs too often.
     */
    private function nextRun(Schedule $schedule): \Illuminate\Support\Carbon
    {
        $next = Cron::parse($schedule->cron())->nextRun(now());

        return $next ?? now()->addHour()->startOfHour();
    }
}
