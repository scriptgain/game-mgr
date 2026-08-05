<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerVariable extends Model
{
    protected $fillable = ['server_id', 'template_variable_id', 'value'];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function variable(): BelongsTo
    {
        return $this->belongsTo(TemplateVariable::class, 'template_variable_id');
    }
}
