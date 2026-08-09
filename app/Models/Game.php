<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A game. Pterodactyl calls this a "Nest", which nobody outside its codebase
 * has ever found intuitive. A Game holds Templates: Minecraft holds Paper,
 * Forge and Vanilla.
 */
class Game extends Model
{
    use Concerns\Auditable;

    protected $fillable = ['uuid', 'name', 'slug', 'category', 'description', 'author', 'icon', 'cover_color'];

    protected static function booted(): void
    {
        static::creating(function (Game $g) {
            $g->uuid ??= (string) Str::uuid();
            $g->slug ??= Str::slug($g->name);
        });
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function servers(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Server::class, Template::class);
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
