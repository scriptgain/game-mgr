<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Two scopes. An application token drives the admin REST API (nodes, templates,
 * every server). A client token only reaches the servers its owner can already
 * see. Same split as Pterodactyl, because tooling written against that split
 * ports across.
 */
class ApiToken extends Model
{
    protected $fillable = ['user_id', 'name', 'token', 'scope', 'allowed_ips', 'last_used_at', 'expires_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'allowed_ips' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function scopeLabel(): string
    {
        return $this->scope === 'application' ? 'Application' : 'Client';
    }
}
