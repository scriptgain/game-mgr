<?php

namespace App\Support;

use App\Services\Config\ConfigFormat;
use App\Services\Config\Formats;
use Illuminate\Support\Str;

/** One config file a template declares, and the settings inside it. */
class ConfigFile
{
    /** @param  array<int,ConfigSetting>  $settings */
    public function __construct(
        public string $id,
        public string $path,
        public string $format,
        public string $label,
        public ?string $description,
        public array $settings,
    ) {}

    public function parser(): ?ConfigFormat
    {
        return Formats::make($this->format);
    }

    /**
     * Build from one entry of a template's config_schema.
     *
     * The index seeds every field id in the file, so two files may both hold a
     * "difficulty" without their form fields colliding. A short hash of the key
     * rides along because a key like "spawn-limits.monsters" and a key like
     * "spawn.limits.monsters" would otherwise slug to the same thing, and a
     * form field silently overwriting another one is not a bug anybody enjoys
     * finding later.
     */
    public static function fromArray(array $data, int $index): self
    {
        $path = '/'.ltrim((string) ($data['file'] ?? ''), '/');

        $settings = [];
        foreach ($data['settings'] ?? [] as $raw) {
            $key = (string) ($raw['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $id = 'c'.$index.'_'.Str::slug(str_replace(['.', '_'], '-', $key), '_').'_'.substr(sha1($key), 0, 6);
            $settings[] = ConfigSetting::fromArray($raw, $id);
        }

        return new self(
            id: 'f'.$index,
            path: $path,
            format: (string) ($data['format'] ?? 'properties'),
            label: (string) ($data['label'] ?? basename($path)),
            description: isset($data['description']) ? (string) $data['description'] : null,
            settings: $settings,
        );
    }

    /**
     * The settings this user may see, grouped by section in declaration order.
     *
     * @return array<string,array<int,ConfigSetting>>
     */
    public function visibleSections(bool $isAdmin): array
    {
        $out = [];

        foreach ($this->settings as $setting) {
            if (! $isAdmin && ! $setting->user_viewable) {
                continue;
            }
            $out[$setting->section ?? ''][] = $setting;
        }

        return $out;
    }
}
