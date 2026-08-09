<?php

namespace App\Http\Resources;

class NodeResource extends ApiResource
{
    public function objectName(): string
    {
        return 'node';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'location_id' => $this->location_id,
            'fqdn' => $this->fqdn,
            'scheme' => $this->scheme,
            'daemon_port' => $this->daemon_port,
            'sftp_port' => $this->sftp_port,
            'sftp_enabled' => (bool) $this->sftp_enabled,
            'public' => (bool) $this->public,
            'maintenance_mode' => (bool) $this->maintenance_mode,
            'runtimes' => $this->runtimes,
            'online' => $this->isOnline(),
            'memory' => $this->memory,
            'memory_overallocate' => $this->memory_overallocate,
            'disk' => $this->disk,
            'disk_overallocate' => $this->disk_overallocate,
            'cpu' => $this->cpu,
            'cpu_overallocate' => $this->cpu_overallocate,
            'allocated' => [
                'memory' => $this->memoryAllocated(),
                'disk' => $this->diskAllocated(),
                'cpu' => $this->cpuAllocated(),
            ],
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
