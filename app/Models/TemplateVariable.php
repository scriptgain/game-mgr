<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One environment variable a template exposes. user_editable is the flag that
 * decides whether it shows up on the client Startup tab or stays admin only.
 */
class TemplateVariable extends Model
{
    protected $fillable = [
        'template_id', 'name', 'description', 'env_variable', 'default_value',
        'user_viewable', 'user_editable', 'rules', 'sort',
    ];

    protected function casts(): array
    {
        return ['user_viewable' => 'boolean', 'user_editable' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /** The validation rules, as an array Laravel can consume directly. */
    public function ruleArray(): array
    {
        return array_values(array_filter(explode('|', (string) $this->rules)));
    }

    public function isRequired(): bool
    {
        return in_array('required', $this->ruleArray(), true);
    }

    /**
     * What shape of control this variable deserves.
     *
     * The rules already say everything a form needs to know: an in: list is a
     * fixed set of choices, integer|between: is a number with a floor and a
     * ceiling, and a two value true/false list is a switch. Deriving the
     * control from the rules means a template author never types "Max Players
     * is a slider from 1 to 200" anywhere, and the control can never disagree
     * with what the server will accept.
     *
     * Returns: type, plus whichever of options/min/max/maxlength/on/off apply.
     * type is one of fixed, switch, choice, select, number, text, secret,
     * textarea.
     */
    public function control(): array
    {
        $rules = $this->ruleArray();
        $numeric = in_array('integer', $rules, true) || in_array('numeric', $rules, true);

        $out = ['type' => 'text', 'options' => [], 'min' => null, 'max' => null,
            'minlength' => null, 'maxlength' => null, 'on' => null, 'off' => null];
        $low = $high = null;

        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'in:')) {
                $out['options'] = array_values(array_filter(array_map('trim', explode(',', substr($rule, 3))), fn ($v) => $v !== ''));
            } elseif (str_starts_with($rule, 'between:')) {
                $parts = explode(',', substr($rule, 8));
                $low = isset($parts[0]) ? (int) $parts[0] : null;
                $high = isset($parts[1]) ? (int) $parts[1] : null;
            } elseif (str_starts_with($rule, 'max:')) {
                $high = (int) substr($rule, 4);
            } elseif (str_starts_with($rule, 'min:')) {
                $low = (int) substr($rule, 4);
            }
        }

        // min: and max: mean a value range on a number and a character count on
        // a string. Getting that backwards puts maxlength="200" on a slider.
        if ($numeric) {
            $out['min'] = $low;
            $out['max'] = $high;
        } else {
            $out['minlength'] = $low;
            $out['maxlength'] = $high;
        }

        if ($out['options']) {
            $lower = array_map('mb_strtolower', $out['options']);
            $distinct = array_values(array_unique($lower));

            if (count($distinct) === 1) {
                // in:TRUE,true is one value written twice. The template allows
                // exactly one thing, so offering a control is a lie.
                $out['type'] = 'fixed';
            } elseif (count($out['options']) === 2 && ! array_diff($lower, ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'])) {
                $out['type'] = 'switch';
                $onIndex = 0;
                foreach (['true', 'yes', 'on', '1'] as $truthy) {
                    $found = array_search($truthy, $lower, true);
                    if ($found !== false) {
                        $onIndex = $found;

                        break;
                    }
                }
                $out['on'] = $out['options'][$onIndex];
                $out['off'] = $out['options'][$onIndex === 0 ? 1 : 0];
            } else {
                // Long labels wrap badly in a segmented control, so past four
                // choices, or once a label is wordy, it becomes a select.
                $wordy = max(array_map('mb_strlen', $out['options'])) > 14;
                $out['type'] = (count($out['options']) > 4 || $wordy) ? 'select' : 'choice';
            }
        } elseif ($numeric) {
            $out['type'] = 'number';
        } elseif (str_contains(mb_strtolower($this->env_variable.' '.$this->name), 'password')) {
            $out['type'] = 'secret';
        } elseif (! in_array('url', $rules, true) && (int) $out['maxlength'] >= 100) {
            $out['type'] = 'textarea';
        }

        return $out;
    }
}
