<?php

namespace App\Http\Resources;

class BackupResource extends ApiResource
{
    public function objectName(): string
    {
        return 'backup';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'server_id' => $this->server_id,
            'name' => $this->name,
            'bytes' => $this->bytes,
            'checksum' => $this->checksum,
            'locked' => (bool) $this->is_locked,
            'successful' => (bool) $this->is_successful,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
