<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Backup extends Model
{
    protected $fillable = [
        'uuid', 'server_id', 'retention_policy_id', 'name', 'ignored_files', 'disk',
        'checksum', 'bytes', 'is_successful', 'is_locked', 'failure_reason', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'ignored_files' => 'array',
            'is_successful' => 'boolean',
            'is_locked' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Backup $b) => $b->uuid ??= (string) Str::uuid());
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicy::class, 'retention_policy_id');
    }

    public function isRunning(): bool
    {
        return $this->completed_at === null && $this->failure_reason === null;
    }

    public function statusLabel(): string
    {
        if ($this->isRunning()) {
            return 'In Progress';
        }

        return $this->is_successful ? 'Complete' : 'Failed';
    }

    public function statusTone(): string
    {
        return match ($this->statusLabel()) {
            'Complete' => 'emerald',
            'In Progress' => 'amber',
            default => 'rose',
        };
    }

    public function diskLabel(): string
    {
        return match ($this->disk) {
            's3' => 'S3',
            'storagemgr' => 'StorageMGR',
            default => 'Node Local',
        };
    }
}
