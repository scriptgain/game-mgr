<?php

namespace App\Support;

/**
 * One setting inside a game config file, as a template declares it.
 *
 * Shaped to be interchangeable with a TemplateVariable so the Config tab can
 * render through the same partial the Startup tab and the admin create screen
 * already use: same id, name, description, env_variable, rules, user_viewable,
 * user_editable, and the same control() derived from the rules. A boolean is a
 * switch here for exactly the reason it is a switch there, and neither one
 * knows about the other.
 *
 * env is the piece with no equivalent on a variable. Several templates rewrite
 * their own config file from the environment on every boot, so a setting that
 * names a variable gets that variable written too, and a customer's change
 * survives the restart it needs in order to take effect at all.
 */
class ConfigSetting
{
    use DerivesControl;

    public function __construct(
        /** Stable form field id: unique across the whole schema. */
        public string $id,
        /** The address inside the file, which is also what the UI shows. */
        public string $env_variable,
        public string $name,
        public ?string $description,
        public ?string $default_value,
        public string $rules,
        public bool $user_viewable,
        public bool $user_editable,
        /** Section heading inside the file's card, or null for ungrouped. */
        public ?string $section = null,
        /** Template variable to keep in step, or null. */
        public ?string $env = null,
    ) {}

    /** The key as the format addresses it. Named for clarity at call sites. */
    public function key(): string
    {
        return $this->env_variable;
    }

    /**
     * Build from the JSON a template carries. Unknown fields are ignored so a
     * newer schema can be read by an older panel without exploding.
     */
    public static function fromArray(array $data, string $id): self
    {
        return new self(
            id: $id,
            env_variable: (string) ($data['key'] ?? ''),
            name: (string) ($data['name'] ?? $data['key'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            default_value: isset($data['default']) ? (string) $data['default'] : null,
            rules: (string) ($data['rules'] ?? 'nullable|string'),
            user_viewable: (bool) ($data['user_viewable'] ?? true),
            user_editable: (bool) ($data['user_editable'] ?? true),
            section: isset($data['section']) ? (string) $data['section'] : null,
            env: isset($data['env']) ? (string) $data['env'] : null,
        );
    }
}
