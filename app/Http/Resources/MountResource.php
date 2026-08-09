<?php

namespace App\Http\Resources;

class MountResource extends ApiResource
{
    public function objectName(): string
    {
        return 'mount';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'source' => $this->source,
            'target' => $this->target,
            'read_only' => (bool) $this->read_only,
            'user_mountable' => (bool) $this->user_mountable,
        ];
    }
}
