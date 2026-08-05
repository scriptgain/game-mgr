<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A host path exposed inside a server. Allowlisted by node and by template so a
 * client cannot mount whatever they like, which is the whole reason this is not
 * simply a free-text field on the server.
 */
class Mount extends Model
{
    use Concerns\Auditable;

    protected $fillable = [
        'uuid', 'name', 'description', 'source', 'target', 'read_only', 'user_mountable',
    ];

    protected function casts(): array
    {
        return ['read_only' => 'boolean', 'user_mountable' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Mount $m) {
            $m->uuid ??= (string) Str::uuid();
        });
    }

    public function nodes(): BelongsToMany
    {
        return $this->belongsToMany(Node::class);
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(Template::class);
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class);
    }
}
