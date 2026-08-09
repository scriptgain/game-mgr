<?php

namespace App\Http\Resources;

class ModResource extends ApiResource
{
    public function objectName(): string
    {
        return 'mod';
    }

    public function fields(): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'author' => $this->author,
            'source' => $this->source,
            'remote_id' => $this->remote_id,
            'version' => $this->version,
            'latest_version' => $this->latest_version,
            'enabled' => (bool) $this->enabled,
            'path' => $this->path,
            'bytes' => $this->bytes,
        ];
    }
}
