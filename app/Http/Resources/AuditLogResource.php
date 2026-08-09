<?php

namespace App\Http\Resources;

class AuditLogResource extends ApiResource
{
    public function objectName(): string
    {
        return 'activity';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'user_id' => $this->user_id,
            'server_id' => $this->server_id,
            'properties' => $this->properties,
            'ip' => $this->ip,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
