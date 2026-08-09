<?php

namespace App\Http\Resources;

class DatabaseHostResource extends ApiResource
{
    public function objectName(): string
    {
        return 'database_host';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'linked_ip' => $this->linked_ip,
            'node_id' => $this->node_id,
            'max_databases' => $this->max_databases,
        ];
    }
}
