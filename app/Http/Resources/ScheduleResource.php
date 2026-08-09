<?php

namespace App\Http\Resources;

class ScheduleResource extends ApiResource
{
    public function objectName(): string
    {
        return 'schedule';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'name' => $this->name,
            // The five cron fields are stored separately and shown together,
            // because a caller wants an expression and the scheduler wants parts.
            'cron' => [
                'minute' => $this->cron_minute,
                'hour' => $this->cron_hour,
                'day_of_month' => $this->cron_day_of_month,
                'month' => $this->cron_month,
                'day_of_week' => $this->cron_day_of_week,
                'expression' => implode(' ', [
                    $this->cron_minute, $this->cron_hour, $this->cron_day_of_month,
                    $this->cron_month, $this->cron_day_of_week,
                ]),
            ],
            'active' => (bool) $this->is_active,
            'only_when_online' => (bool) $this->only_when_online,
            'processing' => (bool) $this->is_processing,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn ($t) => [
                'id' => $t->id, 'action' => $t->action, 'payload' => $t->payload,
            ])),
        ];
    }
}
