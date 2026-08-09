<?php

namespace App\Http\Resources;

class AlertResource extends ApiResource
{
    public function objectName(): string
    {
        return 'alert';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'node_id' => $this->node_id,
            'watchdog_rule_id' => $this->watchdog_rule_id,
            'severity' => $this->severity,
            'title' => $this->title,
            'detail' => $this->detail,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
