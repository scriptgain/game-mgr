<?php

namespace App\Http\Resources;

/**
 * A server as its own customer sees it.
 *
 * Deliberately narrower than the application view. A customer has no business
 * knowing which node they are on, who else is on it, or the internal ids used
 * to address it: none of that helps them and all of it helps somebody mapping
 * the fleet.
 */
class ServerClientResource extends ApiResource
{
    public function objectName(): string
    {
        return 'server';
    }

    public function fields(): array
    {
        return [
            'identifier' => $this->uuid_short,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'state' => $this->statusLabel(),
            'power_state' => $this->power_state,
            'suspended' => $this->isSuspended(),
            'installing' => $this->isInstalling(),
            'address' => $this->address(),
            'connect_name' => $this->connectName(),
            'limits' => [
                'memory' => $this->memory,
                'disk' => $this->disk,
                'cpu' => $this->cpu,
            ],
            'feature_limits' => [
                'databases' => $this->database_limit,
                'allocations' => $this->allocation_limit,
                'backups' => $this->backup_limit,
            ],
            'players' => [
                'online' => $this->cached_players,
                'max' => $this->cached_max_players,
            ],
            'sftp' => [
                'host' => $this->sftpHost(),
                'username' => $this->sftpUsername(auth()->user()),
            ],
        ];
    }
}
