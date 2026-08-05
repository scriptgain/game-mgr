<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A MySQL or MariaDB server that per-server game databases are handed out of.
 * The admin registers it once with a privileged account; clients never see
 * these credentials, only the ones created for them.
 */
class DatabaseHost extends Model
{
    use Concerns\Auditable;

    protected $fillable = [
        'name', 'host', 'port', 'username', 'password', 'linked_ip', 'node_id', 'max_databases',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'encrypted'];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function databases(): HasMany
    {
        return $this->hasMany(ServerDatabase::class);
    }

    public function isFull(): bool
    {
        return $this->max_databases > 0 && $this->databases()->count() >= $this->max_databases;
    }
}
