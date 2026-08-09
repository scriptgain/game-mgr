<?php

namespace App\Http\Resources;

class PlayerResource extends ApiResource
{
    public function objectName(): string
    {
        return 'player';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'identifier' => $this->identifier,
            'name' => $this->name,
            'online' => (bool) $this->is_online,
            'banned' => (bool) $this->is_banned,
            'operator' => (bool) $this->is_op,
            'whitelisted' => (bool) $this->is_whitelisted,
            'playtime_seconds' => $this->playtime_seconds,
            'first_seen_at' => $this->first_seen_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
