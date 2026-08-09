<?php

namespace App\Http\Resources;

class WebhookResource extends ApiResource
{
    public function objectName(): string
    {
        return 'webhook';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'events' => $this->events,
            'active' => (bool) $this->is_active,
            'failure_count' => $this->failure_count,
            'last_fired_at' => $this->last_fired_at?->toIso8601String(),
        ];
    }
}
