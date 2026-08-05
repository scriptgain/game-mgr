<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Retention is a policy, not a flat count cap. A backup survives if any rule
 * still wants it. Pterodactyl only offers "keep N", which is why people who
 * want a monthly archive end up scripting it themselves.
 */
class RetentionPolicy extends Model
{
    use Concerns\Auditable;

    protected $fillable = [
        'name', 'keep_last', 'keep_daily', 'keep_weekly', 'keep_monthly', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    public function summary(): string
    {
        return implode(', ', array_filter([
            $this->keep_last ? "last {$this->keep_last}" : null,
            $this->keep_daily ? "{$this->keep_daily} daily" : null,
            $this->keep_weekly ? "{$this->keep_weekly} weekly" : null,
            $this->keep_monthly ? "{$this->keep_monthly} monthly" : null,
        ])) ?: 'Keep Everything';
    }
}
