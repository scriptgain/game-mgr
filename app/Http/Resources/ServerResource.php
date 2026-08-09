<?php

namespace App\Http\Resources;

/**
 * A server as the application (admin) scope sees it: everything, including who
 * owns it and which node it is on.
 */
class ServerResource extends ApiResource
{
    public function objectName(): string
    {
        return 'server';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'identifier' => $this->uuid_short,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'suspended' => $this->isSuspended(),
            'power_state' => $this->power_state,
            'owner_id' => $this->owner_id,
            'node_id' => $this->node_id,
            'template_id' => $this->template_id,
            'runtime' => $this->runtime,
            'limits' => [
                'memory' => $this->memory,
                'swap' => $this->swap,
                'disk' => $this->disk,
                'io' => $this->io,
                'cpu' => $this->cpu,
            ],
            'feature_limits' => [
                'databases' => $this->database_limit,
                'allocations' => $this->allocation_limit,
                'backups' => $this->backup_limit,
            ],
            'address' => $this->address(),
            'connect_name' => $this->connectName(),
            'sftp' => [
                'host' => $this->sftpHost(),
                'username' => $this->sftpUsername(),
            ],
            'installed_at' => $this->installed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function relations(): array
    {
        return [
            'owner' => $this->wants('owner') && $this->owner
                ? (new UserResource($this->owner))->toArray(request()) : null,
            'node' => $this->wants('node') && $this->node
                ? (new NodeResource($this->node))->toArray(request()) : null,
            'allocations' => $this->wants('allocations')
                ? ApiResource::list($this->allocations, AllocationResource::class) : null,
        ];
    }
}
