<?php

namespace App\Models;

use App\Support\DerivesControl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One environment variable a template exposes. user_editable is the flag that
 * decides whether it shows up on the client Startup tab or stays admin only.
 *
 * ruleArray(), isRequired() and control() come from DerivesControl, which a
 * config file setting shares so both render through the same partial.
 */
class TemplateVariable extends Model
{
    use DerivesControl;

    protected $fillable = [
        'template_id', 'name', 'description', 'env_variable', 'default_value',
        'user_viewable', 'user_editable', 'rules', 'sort',
    ];

    /**
     * The default the database used to hold.
     *
     * `rules` became TEXT so community definitions with enormous `in:` lists fit,
     * and MySQL will not take a DEFAULT on TEXT. Keeping it here means every
     * caller that omits the field behaves exactly as before.
     */
    protected $attributes = ['rules' => 'nullable|string'];

    protected function casts(): array
    {
        return ['user_viewable' => 'boolean', 'user_editable' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
