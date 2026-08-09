<?php

namespace App\Services\Api;

use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Laravel validation rules, as JSON Schema.
 *
 * The API documented its paths and its query parameters and said nothing about
 * what a write endpoint accepts, so anybody integrating had to open the panel's
 * own form and guess. The rules were written down all along; they were just
 * written where a generator could not reach them.
 *
 * This is the translation, and it is deliberately narrow: it covers the rules
 * this codebase actually uses and no more. A rule it does not recognise is
 * carried through into the description rather than dropped, so an undocumented
 * constraint is visible to a reader even when it cannot be expressed as a type.
 *
 * What it will not do is invent certainty. `exists:nodes,id` says a value must
 * match a row, which is not a shape, so it becomes prose. Guessing an enum of
 * live database ids into a static document would be worse than saying nothing.
 */
class RuleSchema
{
    /** Rules that describe the TYPE of a field. */
    private const TYPES = [
        'integer' => 'integer',
        'numeric' => 'number',
        'boolean' => 'boolean',
        'array' => 'array',
        'string' => 'string',
        'file' => 'string',
        'image' => 'string',
    ];

    /** Rules that are really a string format. */
    private const FORMATS = [
        'email' => 'email',
        'url' => 'uri',
        'date' => 'date',
        'ip' => 'ipv4',
        'uuid' => 'uuid',
    ];

    /**
     * A whole rule set as one object schema.
     *
     * @param  array<string,mixed>  $rules
     * @return array<string,mixed>
     */
    public static function object(array $rules): array
    {
        $properties = [];
        $required = [];

        foreach ($rules as $field => $rule) {
            // `permissions.*` describes the ITEMS of `permissions`, not a field
            // of its own. Folded into the parent, which is where a reader
            // expects to find it.
            if (str_contains($field, '.*')) {
                $parent = Str::before($field, '.*');
                $properties[$parent]['type'] = 'array';
                $properties[$parent]['items'] = self::field($rule);

                continue;
            }

            $schema = self::field($rule);
            // A parent declared after its own .* line must not wipe the items.
            $properties[$field] = array_merge($properties[$field] ?? [], $schema);

            if (self::has($rule, 'required')) {
                $required[] = $field;
            }
        }

        return array_filter([
            'type' => 'object',
            'properties' => $properties,
            'required' => array_values(array_unique($required)),
        ], fn ($value) => $value !== []);
    }

    /**
     * One field.
     *
     * @return array<string,mixed>
     */
    public static function field(mixed $rule): array
    {
        $parts = self::parts($rule);
        $schema = [];
        $notes = [];

        foreach ($parts as $part) {
            $name = Str::lower(Str::before($part, ':'));
            $argument = Str::contains($part, ':') ? Str::after($part, ':') : '';

            match (true) {
                isset(self::TYPES[$name]) => $schema['type'] = self::TYPES[$name],
                // A format is always a string, and the type rule is usually
                // left off because `email` already implies it to a human.
                isset(self::FORMATS[$name]) => [$schema['format'], $schema['__implied']] = [self::FORMATS[$name], 'string'],
                // The enum says what the type is: a list of words is a string,
                // and every in: rule in this codebase is a list of words.
                $name === 'in' => [$schema['enum'], $schema['__implied']] = [$values = self::enum($argument), self::enumType($values)],
                $name === 'min' => $schema['__min'] = $argument,
                $name === 'max' => $schema['__max'] = $argument,
                $name === 'between' => [$schema['__min'], $schema['__max']] = array_pad(explode(',', $argument, 2), 2, null),
                $name === 'exists' => self::reference($schema, $notes, $argument),
                $name === 'unique' => $notes[] = 'Must not already be taken',
                $name === 'confirmed' => $notes[] = 'Send it twice, with _confirmation',
                // Laravel's Password object stringifies to the bare word, which
                // says nothing. Its own defaults are the constraint worth
                // stating, and every use in this codebase is Password::min(8).
                $name === 'password' => [$schema['__implied'], $notes[]] = ['string', 'At least '.$argument.' characters'],
                $name === 'regex' => $notes[] = 'Must match '.$argument,
                in_array($name, ['required', 'nullable', 'sometimes', 'present', 'filled'], true) => null,
                default => $notes[] = $part,
            };
        }

        // min and max mean different things depending on the type, and the type
        // rule can appear after them, so they are resolved once at the end.
        $schema = self::applyBounds($schema);

        // A nullable field really can be null, and a reader who sends null to
        // something that cannot take it gets a 422 they did not expect.
        if (self::has($rule, 'nullable') && isset($schema['type'])) {
            $schema['type'] = [$schema['type'], 'null'];
        }

        // Not ucfirst'ed: an unrecognised rule is carried through verbatim, and
        // "Starts_with:gm_" reads like a mistake where "starts_with:gm_" reads
        // like the rule it is.
        if ($notes !== []) {
            $schema['description'] = implode('; ', $notes).'.';
        }

        return $schema;
    }

    /**
     * A foreign key reference.
     *
     * `exists:users,id` is not a shape, so it becomes prose. It does say one
     * thing about the shape though: a reference to an `id` column in this
     * schema is a number, every time. Left as "any" it reads as though the
     * field takes anything, and the generated example hands the reader
     * "<owner_id>" where the API wants 7. The type is only implied, never
     * forced, so an explicit `string` rule alongside it still wins.
     */
    private static function reference(array &$schema, array &$notes, string $argument): void
    {
        [$table, $column] = array_pad(explode(',', $argument, 2), 2, 'id');

        $notes[] = 'Must match an existing '.Str::singular(trim($table));

        if (trim($column) === 'id') {
            $schema['__implied'] = 'integer';
        }
    }

    /** @return array<string,mixed> */
    private static function applyBounds(array $schema): array
    {
        if (! isset($schema['type']) && isset($schema['__implied'])) {
            $schema['type'] = $schema['__implied'];
        }
        unset($schema['__implied']);

        $min = $schema['__min'] ?? null;
        $max = $schema['__max'] ?? null;
        unset($schema['__min'], $schema['__max']);

        $numeric = in_array($schema['type'] ?? 'string', ['integer', 'number'], true);

        if ($min !== null && $min !== '') {
            $schema[$numeric ? 'minimum' : 'minLength'] = $numeric ? +$min : (int) $min;
        }
        if ($max !== null && $max !== '') {
            $schema[$numeric ? 'maximum' : 'maxLength'] = $numeric ? +$max : (int) $max;
        }

        return $schema;
    }

    /**
     * The rule as a list of strings.
     *
     * Rule objects stringify, which is what Laravel does with them internally,
     * so Rule::in and Rule::unique both survive the trip without being special
     * cased here.
     *
     * @return array<int,string>
     */
    private static function parts(mixed $rule): array
    {
        $parts = is_array($rule) ? $rule : explode('|', (string) $rule);

        return array_values(array_filter(array_map(self::stringify(...), $parts)));
    }

    /**
     * One rule as a string.
     *
     * Most Rule objects stringify. Password does not: it is a ValidationRule
     * with no __toString, so the old map dropped it and the API reference said
     * nothing at all about the password field, which is worse than saying
     * "any". Its minimum lives in a protected property, so reading it is a
     * reach into the framework, but the alternative is hardcoding 8 here and
     * quietly lying the day somebody raises it.
     */
    private static function stringify(mixed $part): ?string
    {
        if (is_string($part)) {
            return $part;
        }
        if (! is_object($part)) {
            return null;
        }
        if ($part instanceof PasswordRule) {
            $min = (new \ReflectionProperty($part, 'min'))->getValue($part);

            return 'password:'.$min;
        }

        return method_exists($part, '__toString') ? (string) $part : null;
    }

    /** @return array<int,string> */
    private static function enum(string $argument): array
    {
        return array_values(array_filter(array_map(
            // Rule::in quotes its values; a bare in: rule does not.
            fn ($value) => trim(trim($value), '"'),
            explode(',', $argument),
        ), fn ($value) => $value !== ''));
    }

    /** @param  array<int,string>  $values */
    private static function enumType(array $values): string
    {
        foreach ($values as $value) {
            if (! is_numeric($value)) {
                return 'string';
            }
        }

        return 'integer';
    }

    private static function has(mixed $rule, string $name): bool
    {
        foreach (self::parts($rule) as $part) {
            if (Str::lower(Str::before($part, ':')) === $name) {
                return true;
            }
        }

        return false;
    }
}
