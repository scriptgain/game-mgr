<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Both the admin audit trail and the client-facing Activity tab. Entries with a
 * server_id show up on that server's Activity screen; entries without are
 * panel-wide and admin only.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'server_id', 'action', 'subject_type', 'subject_id',
        'description', 'properties', 'ip',
    ];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** Record an audit entry. Best effort, never throws into the caller. */
    public static function record(string $action, string $description, ?Model $subject = null, ?int $serverId = null, array $properties = []): void
    {
        try {
            static::create([
                'user_id' => auth()->id(),
                'server_id' => $serverId ?? ($subject instanceof Server ? $subject->getKey() : null),
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'properties' => $properties ?: null,
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the request.
        }
    }

    /** slate, emerald, amber or rose, for the row dot. */
    public function tone(): string
    {
        return match (true) {
            str_contains($this->action, 'delete'), str_contains($this->action, 'fail'),
            str_contains($this->action, 'ban') => 'rose',
            str_contains($this->action, 'creat'), $this->action === 'login' => 'emerald',
            str_contains($this->action, 'power'), str_contains($this->action, 'restart') => 'amber',
            default => 'slate',
        };
    }
}
