<?php

namespace App\Http\Resources;

class FileEntryResource extends ApiResource
{
    public function objectName(): string
    {
        return 'file';
    }

    public function fields(): array
    {
        return [
            'name' => $this['name'] ?? null,
            'directory' => (bool) ($this['directory'] ?? false),
            'symlink' => (bool) ($this['symlink'] ?? false),
            'size' => $this['size'] ?? 0,
            'mode' => $this['mode'] ?? null,
            'mime' => $this['mime_type'] ?? null,
            'modified_at' => $this['modified_at'] ?? null,
        ];
    }
}
