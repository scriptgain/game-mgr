<?php

namespace Tests\Unit;

use App\Services\Api\RuleSchema;
use Illuminate\Validation\Rule;
use PHPUnit\Framework\TestCase;

/**
 * Validation rules translated into JSON Schema, which is what lets the API
 * reference describe a request body instead of admitting it cannot.
 *
 * The translation is narrow on purpose: it covers the rules this codebase uses.
 * What it must never do is invent certainty, so the tests below care as much
 * about what it declines to say as about what it says.
 */
class RuleSchemaTest extends TestCase
{
    public function test_types_and_bounds(): void
    {
        $this->assertSame(
            ['type' => 'string', 'maxLength' => 120],
            RuleSchema::field(['required', 'string', 'max:120']),
        );

        // min and max mean length for a string and value for a number, and the
        // type rule can appear after them, so they resolve last.
        $this->assertSame(
            ['type' => 'integer', 'minimum' => 0, 'maximum' => 500],
            RuleSchema::field(['min:0', 'max:500', 'integer']),
        );

        $this->assertSame(
            ['type' => 'integer', 'minimum' => 1, 'maximum' => 65535],
            RuleSchema::field(['required', 'integer', 'between:1,65535']),
        );
    }

    public function test_nullable_really_means_null_is_accepted(): void
    {
        $this->assertSame(
            ['type' => ['integer', 'null']],
            RuleSchema::field(['nullable', 'integer']),
        );
    }

    public function test_formats_and_enums(): void
    {
        $this->assertSame('email', RuleSchema::field(['required', 'email'])['format']);
        $this->assertSame(['direct', 'reverse'], RuleSchema::field(['in:direct,reverse'])['enum']);
        // Rule::in quotes its values. Both spellings must land the same way.
        $this->assertSame(['start', 'stop'], RuleSchema::field(['string', Rule::in(['start', 'stop'])])['enum']);
    }

    /**
     * exists: is a fact about the database, not a shape. Turning live ids into
     * an enum in a static document would be worse than saying nothing, so it
     * becomes prose a human can act on.
     */
    public function test_database_rules_become_prose_not_schema(): void
    {
        $schema = RuleSchema::field(['required', 'exists:nodes,id']);

        $this->assertArrayNotHasKey('enum', $schema);
        $this->assertStringContainsString('Must match an existing node', $schema['description']);
    }

    /** An unrecognised rule is surfaced, never silently dropped. */
    public function test_an_unknown_rule_survives_into_the_description(): void
    {
        $schema = RuleSchema::field(['required', 'starts_with:gm_']);

        $this->assertStringContainsString('starts_with:gm_', $schema['description']);
    }

    public function test_an_object_lists_only_the_required_fields(): void
    {
        $schema = RuleSchema::object([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'memory' => ['required', 'integer', 'min:0'],
        ]);

        $this->assertSame('object', $schema['type']);
        $this->assertSame(['name', 'memory'], $schema['required']);
        $this->assertArrayHasKey('description', $schema['properties']);
    }

    /** `permissions.*` describes the items, not a field called "permissions.*". */
    public function test_a_wildcard_folds_into_its_parent_array(): void
    {
        $schema = RuleSchema::object([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::in(['file.read', 'file.write'])],
        ]);

        $this->assertArrayNotHasKey('permissions.*', $schema['properties']);
        $this->assertSame('array', $schema['properties']['permissions']['type']);
        $this->assertSame(['file.read', 'file.write'], $schema['properties']['permissions']['items']['enum']);
        $this->assertContains('permissions', $schema['required']);
    }

    /**
     * A reference to an id column is a number, and saying "any" sends the
     * reader an example with a string where the API wants an integer.
     */
    public function test_an_exists_rule_on_an_id_column_implies_an_integer(): void
    {
        $schema = RuleSchema::field(['required', 'exists:users,id']);

        $this->assertSame('integer', $schema['type']);
        $this->assertSame('Must match an existing user.', $schema['description']);
    }

    /** Only implied. An explicit type alongside it still wins. */
    public function test_an_explicit_type_beats_the_implied_one(): void
    {
        $schema = RuleSchema::field(['required', 'string', 'exists:templates,slug']);

        $this->assertSame('string', $schema['type']);
        $this->assertStringContainsString('existing template', $schema['description']);
    }

    /**
     * Password has no __toString, so it used to vanish and the password field
     * came out as an empty schema. Pinned, because the failure was silent.
     */
    public function test_a_password_rule_survives_and_carries_its_minimum(): void
    {
        $schema = RuleSchema::field(['required', \Illuminate\Validation\Rules\Password::min(12)]);

        $this->assertSame('string', $schema['type']);
        $this->assertSame('At least 12 characters.', $schema['description']);
    }

    /** An email is a string, whether or not the rules bother to say so. */
    public function test_a_format_implies_a_string(): void
    {
        $schema = RuleSchema::field(['required', 'email', 'max:255']);

        $this->assertSame('string', $schema['type']);
        $this->assertSame('email', $schema['format']);
        $this->assertSame(255, $schema['maxLength']);
    }
}
