<?php

namespace App\Http\Resources;

class ServerDatabaseResource extends ApiResource
{
    public function objectName(): string
    {
        return 'database';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'database' => $this->database,
            'username' => $this->username,
            'database_host_id' => $this->database_host_id,
            'remote' => $this->remote,
            'bytes' => $this->bytes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
