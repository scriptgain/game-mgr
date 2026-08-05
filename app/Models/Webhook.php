<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Outbound webhook. Payloads are signed with the secret so a receiver can tell
 * a real call from anyone who guessed the URL.
 */
class Webhook extends Model
{
    use Concerns\Auditable;

    protected $fillable = ['name', 'url', 'secret', 'events', 'is_active', 'last_fired_at', 'failure_count'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['events' => 'array', 'is_active' => 'boolean', 'last_fired_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Webhook $w) => $w->secret ??= Str::random(48));
    }

    public function isHealthy(): bool
    {
        return $this->failure_count < 5;
    }
}
