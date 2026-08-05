<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A place nodes live. "EU Frankfurt", "home lab", "the cupboard under the
 * stairs". Grouping nodes is what makes auto placement and capacity views
 * mean anything.
 */
class Location extends Model
{
    use Concerns\Auditable;

    protected $fillable = ['short', 'name', 'description', 'flag'];

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }

    public function servers(): HasManyThrough
    {
        return $this->hasManyThrough(Server::class, Node::class);
    }
}
