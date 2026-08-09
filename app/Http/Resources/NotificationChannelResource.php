<?php

namespace App\Http\Resources;

class NotificationChannelResource extends ApiResource
{
    public function objectName(): string
    {
        return 'channel';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            // target holds a webhook URL or an address, so it is not returned.
            'events' => $this->events,
            'active' => (bool) $this->is_active,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
        ];
    }
}
