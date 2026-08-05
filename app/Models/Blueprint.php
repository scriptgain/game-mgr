<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved server configuration, cloneable in one click. Pterodactyl makes you
 * retype every limit and variable for each new server, which is exactly the
 * work a host repeats hundreds of times.
 */
class Blueprint extends Model
{
    use Concerns\Auditable;

    protected $fillable = [
        'name', 'description', 'template_id', 'limits', 'feature_limits',
        'environment', 'created_by',
    ];

    protected function casts(): array
    {
        return ['limits' => 'array', 'feature_limits' => 'array', 'environment' => 'array'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function summary(): string
    {
        $l = $this->limits ?? [];

        return sprintf(
            '%s RAM, %s disk, %s%% CPU',
            $this->human($l['memory'] ?? 0),
            $this->human($l['disk'] ?? 0),
            $l['cpu'] ?? 0,
        );
    }

    private function human(int|float|string $mib): string
    {
        $mib = (int) $mib;

        return $mib >= 1024 ? round($mib / 1024, 1).' GiB' : $mib.' MiB';
    }
}
