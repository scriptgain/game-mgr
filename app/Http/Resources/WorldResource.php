<?php

namespace App\Http\Resources;

class WorldResource extends ApiResource
{
    public function objectName(): string
    {
        return 'world';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'name' => $this->name,
            'path' => $this->path,
            'seed' => $this->seed,
            'level_type' => $this->level_type,
            'active' => (bool) $this->is_active,
            'bytes' => $this->bytes,
            'last_played_at' => $this->last_played_at?->toIso8601String(),
        ];
    }
}
