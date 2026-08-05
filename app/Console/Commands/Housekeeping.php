<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\NodeMetric;
use App\Models\PlayerEvent;
use App\Models\ServerMetric;
use Illuminate\Console\Command;

/**
 * Nightly trim. Metric history is the table that grows without bound if nobody
 * watches it: one sample per server per minute is half a million rows a year
 * for a single server.
 */
class Housekeeping extends Command
{
    protected $signature = 'gamemgr:housekeeping';

    protected $description = 'Trim metric history, old audit rows and expired backups';

    public function handle(): int
    {
        $metricDays = (int) config('gamemgr.metric_history_days', 30);
        $auditDays = (int) config('gamemgr.audit_log_days', 180);

        $m = ServerMetric::where('sampled_at', '<', now()->subDays($metricDays))->delete();
        $n = NodeMetric::where('sampled_at', '<', now()->subDays($metricDays))->delete();
        $p = PlayerEvent::where('occurred_at', '<', now()->subDays($metricDays))->delete();
        $a = AuditLog::where('created_at', '<', now()->subDays($auditDays))->delete();

        // A backup row whose job never finished is not "in progress" a week
        // later, it is a failure nobody noticed.
        $stuck = Backup::whereNull('completed_at')
            ->whereNull('failure_reason')
            ->where('created_at', '<', now()->subHours(6))
            ->update(['failure_reason' => 'Abandoned: the node never reported a result.', 'completed_at' => now()]);

        $this->info("Trimmed {$m} server metrics, {$n} node metrics, {$p} player events, {$a} audit rows. Marked {$stuck} stuck backups as failed.");

        return self::SUCCESS;
    }
}
