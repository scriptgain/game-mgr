<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'node_id', 'sampled_at', 'cpu', 'memory', 'disk', 'load',
        'server_count', 'running_count',
    ];

    protected function casts(): array
    {
        return ['sampled_at' => 'datetime'];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
