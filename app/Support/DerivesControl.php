<?php

namespace App\Support;

/**
 * Turns a Laravel validation rule string into the shape of form control it
 * describes.
 *
 * This lived on TemplateVariable and is now shared, because a config file
 * setting wants exactly the same treatment: an in: list is a fixed set of
 * choices, numeric|between: is a bounded number, and a two value true/false
 * list is a switch. Deriving the control from the rules that the server will
 * validate against means a control can never offer a value the save would then
 * reject, and nobody has to write "Max Players is a slider from 1 to 200"
 * anywhere at all.
 *
 * The using class must expose: a `rules` string, a `name`, and an
 * `env_variable` (for a config setting that is the config key).
 */
trait DerivesControl
{
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
     * Returns: type, plus whichever of options/min/max/step/maxlength/on/off
     * apply. type is one of fixed, switch, choice, select, number, text,
     * secret, textarea.
     */
    public function control(): array
    {
        $rules = $this->ruleArray();
        $numeric = in_array('integer', $rules, true) || in_array('numeric', $rules, true);

        $out = ['type' => 'text', 'options' => [], 'min' => null, 'max' => null, 'step' => 1,
            'minlength' => null, 'maxlength' => null, 'on' => null, 'off' => null];
        $low = $high = null;

        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'in:')) {
                $out['options'] = array_values(array_filter(array_map('trim', explode(',', substr($rule, 3))), fn ($v) => $v !== ''));
            } elseif (str_starts_with($rule, 'between:')) {
                $parts = explode(',', substr($rule, 8));
                $low = isset($parts[0]) ? $this->bound($parts[0]) : null;
                $high = isset($parts[1]) ? $this->bound($parts[1]) : null;
            } elseif (str_starts_with($rule, 'max:')) {
                $high = $this->bound(substr($rule, 4));
            } elseif (str_starts_with($rule, 'min:')) {
                $low = $this->bound(substr($rule, 4));
            }
        }

        // min: and max: mean a value range on a number and a character count on
        // a string. Getting that backwards puts maxlength="200" on a slider.
        if ($numeric) {
            $out['min'] = $low;
            $out['max'] = $high;
            // A rate that runs 0.1 to 20 is not a whole number control. Step
            // follows the bounds, so an integer rule keeps the step of 1 it
            // always had and only a fractional bound loosens it.
            $fractional = ($low !== null && fmod((float) $low, 1.0) !== 0.0)
                || ($high !== null && fmod((float) $high, 1.0) !== 0.0)
                || (! in_array('integer', $rules, true) && in_array('numeric', $rules, true));
            $out['step'] = $fractional ? 0.1 : 1;
        } else {
            $out['minlength'] = $low === null ? null : (int) $low;
            $out['maxlength'] = $high === null ? null : (int) $high;
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

    /** A rule bound, kept as an int when it is one so nothing gains a ".0". */
    private function bound(string $raw): int|float|null
    {
        $raw = trim($raw);

        if (! is_numeric($raw)) {
            return null;
        }

        $number = $raw + 0;

        return is_float($number) && fmod($number, 1.0) === 0.0 ? (int) $number : $number;
    }
}
