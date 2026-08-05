<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    protected $fillable = [
        'server_id', 'name', 'cron_minute', 'cron_hour', 'cron_day_of_month',
        'cron_month', 'cron_day_of_week', 'is_active', 'only_when_online',
        'is_processing', 'last_run_at', 'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'only_when_online' => 'boolean',
            'is_processing' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ScheduleTask::class)->orderBy('sequence');
    }

    public function cron(): string
    {
        return implode(' ', [
            $this->cron_minute, $this->cron_hour, $this->cron_day_of_month,
            $this->cron_month, $this->cron_day_of_week,
        ]);
    }

    /** A cron expression nobody wants to read, said in words. */
    public function humanCron(): string
    {
        return match (true) {
            $this->cron() === '0 * * * *' => 'Every Hour',
            $this->cron() === '*/5 * * * *' => 'Every 5 Minutes',
            $this->cron() === '*/15 * * * *' => 'Every 15 Minutes',
            $this->cron() === '*/30 * * * *' => 'Every 30 Minutes',
            $this->cron() === '0 0 * * *' => 'Daily At Midnight',
            $this->cron() === '0 4 * * *' => 'Daily At 04:00',
            $this->cron() === '0 0 * * 0' => 'Weekly On Sunday',
            $this->cron_minute === '0' && ctype_digit((string) $this->cron_hour)
                && $this->cron_day_of_month === '*' && $this->cron_day_of_week === '*'
                => 'Daily At '.str_pad((string) $this->cron_hour, 2, '0', STR_PAD_LEFT).':00',
            default => $this->cron(),
        };
    }
}
