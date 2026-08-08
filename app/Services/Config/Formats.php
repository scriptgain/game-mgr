<?php

namespace App\Services\Config;

/** Names a template's config schema may use, and the parser behind each. */
class Formats
{
    public const ALL = [
        'properties' => 'Java Properties',
        'ini' => 'INI',
        'palworld' => 'Palworld INI',
        'yaml' => 'YAML',
    ];

    public static function make(string $format): ?ConfigFormat
    {
        return match ($format) {
            'properties' => new PropertiesFormat,
            'ini' => new IniFormat,
            'palworld' => new PalworldIniFormat,
            'yaml', 'yml' => new YamlFormat,
            default => null,
        };
    }
}
