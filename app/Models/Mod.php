<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An installed mod or plugin, with enough provenance to offer an update.
 * Sourcing from Modrinth, CurseForge, SpigotMC and the Steam Workshop is what
 * turns "upload a jar and hope" into a managed list.
 */
class Mod extends Model
{
    public const SOURCES = [
        'modrinth' => 'Modrinth',
        'curseforge' => 'CurseForge',
        'spigot' => 'SpigotMC',
        'workshop' => 'Steam Workshop',
        'manual' => 'Uploaded',
    ];

    protected $fillable = [
        'server_id', 'source', 'remote_id', 'name', 'slug', 'author', 'summary',
        'version', 'latest_version', 'path', 'bytes', 'verified', 'enabled', 'installed_at', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'verified' => 'boolean',
            'installed_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst((string) $this->source);
    }

    public function hasUpdate(): bool
    {
        return $this->latest_version && $this->latest_version !== $this->version;
    }
}
