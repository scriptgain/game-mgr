<?php

namespace App\Http\Resources;

class WatchdogRuleResource extends ApiResource
{
    public function objectName(): string
    {
        return 'watchdog_rule';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'name' => $this->name,
            'trigger' => $this->trigger,
            'pattern' => $this->pattern,
            'threshold' => $this->threshold,
            'grace_seconds' => $this->grace_seconds,
            'action' => $this->action,
            'channels' => $this->channels,
            'active' => (bool) $this->is_active,
            'last_fired_at' => $this->last_fired_at?->toIso8601String(),
        ];
    }
}
