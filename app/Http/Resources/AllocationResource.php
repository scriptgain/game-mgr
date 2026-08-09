<?php

namespace App\Http\Resources;

class AllocationResource extends ApiResource
{
    public function objectName(): string
    {
        return 'allocation';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'node_id' => $this->node_id,
            'server_id' => $this->server_id,
            'ip' => $this->ip,
            'alias' => $this->ip_alias,
            'port' => $this->port,
            'protocol' => $this->protocol,
            'role' => $this->role,
            'assigned' => $this->server_id !== null,
        ];
    }
}
