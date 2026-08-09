<?php

namespace App\Http\Resources;

class UserResource extends ApiResource
{
    public function objectName(): string
    {
        return 'user';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid ?? null,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'root_admin' => (bool) $this->root_admin,
            'suspended' => (bool) $this->suspended,
            'two_factor' => $this->hasTwoFactor(),
            'timezone' => $this->timezone,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
