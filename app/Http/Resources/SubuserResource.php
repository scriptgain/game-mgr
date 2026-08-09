<?php

namespace App\Http\Resources;

class SubuserResource extends ApiResource
{
    public function objectName(): string
    {
        return 'subuser';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'user_id' => $this->user_id,
            'email' => $this->user?->email,
            'name' => $this->user?->name,
            'permissions' => $this->permissions ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
