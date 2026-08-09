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

    /**
     * Find a token by the plaintext a caller presented.
     *
     * Only the sha256 is stored, so this hashes and compares rather than
     * looking the plaintext up: a leaked database gives somebody the hashes,
     * and a hash is not a credential unless this method accepts one.
     *
     * Returns null for an expired token as well as an unknown one. Callers get
     * one answer, "no", because telling an unauthenticated stranger the
     * difference between "wrong" and "expired" tells them a token exists.
     */
    public static function findByPlaintext(?string $plain): ?self
    {
        if (! is_string($plain) || $plain === '') {
            return null;
        }

        $token = static::where('token', hash('sha256', $plain))->first();

        if (! $token || $token->isExpired()) {
            return null;
        }

        return $token;
    }

    /**
     * May this token be used from this address?
     *
     * An empty or absent list means anywhere, which is what somebody who never
     * filled the field in meant. Reading it as "nowhere" would lock out every
     * token ever created before the field existed.
     */
    public function allowsAddress(?string $ip): bool
    {
        $allowed = array_filter((array) ($this->allowed_ips ?? []));

        if ($allowed === []) {
            return true;
        }

        return in_array($ip, $allowed, true);
    }

    /**
     * Record use, but not on every single call.
     *
     * This is a write on a read path. A token polled once a second would
     * otherwise mean a write a second, forever, to store a timestamp nobody
     * reads at that resolution.
     */
    public function touchUsage(): void
    {
        if ($this->last_used_at && $this->last_used_at->diffInSeconds(now()) < 60) {
            return;
        }

        $this->forceFill(['last_used_at' => now()])->saveQuietly();
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
