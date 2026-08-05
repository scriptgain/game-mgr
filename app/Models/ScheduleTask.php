<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in a schedule. Tasks chain, each running after the previous one plus
 * its offset, so "warn, wait five minutes, warn again, restart" is a single
 * schedule rather than four cron entries that drift apart.
 */
class ScheduleTask extends Model
{
    public const ACTIONS = [
        'power' => 'Power Action',
        'command' => 'Send Command',
        'backup' => 'Take Backup',
        'update' => 'Update Game Files',
        'webhook' => 'Call Webhook',
    ];

    protected $fillable = [
        'schedule_id', 'sequence', 'action', 'payload', 'time_offset',
        'continue_on_failure', 'is_queued',
    ];

    protected function casts(): array
    {
        return ['continue_on_failure' => 'boolean', 'is_queued' => 'boolean'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst((string) $this->action);
    }

    public function describe(): string
    {
        $detail = $this->payload ? ': '.$this->payload : '';

        return $this->actionLabel().$detail;
    }
}
