<?php

namespace App\Http\Resources;

class ApiTokenResource extends ApiResource
{
    public function objectName(): string
    {
        return 'api_token';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'scope' => $this->scope,
            'allowed_ips' => $this->allowed_ips,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
