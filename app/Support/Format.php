<?php

namespace App\Support;

/**
 * Small display helpers. Kept here rather than as Blade directives so the same
 * formatting is available from controllers, commands and API responses.
 */
class Format
{
    /** Bytes as something a human reads, e.g. 1.4 GiB. */
    public static function bytes(int|float|null $bytes, int $precision = 1): string
    {
        $bytes = (float) ($bytes ?? 0);
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        // Whole numbers do not need a decimal place; 4 KiB reads better than
        // 4.0 KiB, and the column stays narrow.
        return ($power === 0 || fmod($value, 1) === 0.0)
            ? number_format($value).' '.$units[$power]
            : number_format($value, $precision).' '.$units[$power];
    }

    /** MiB as GiB where that is clearer. Limits are stored in MiB throughout. */
    public static function mib(int|float|null $mib, int $precision = 1): string
    {
        $mib = (float) ($mib ?? 0);

        return $mib >= 1024
            ? number_format($mib / 1024, $precision).' GiB'
            : number_format($mib).' MiB';
    }

    /** Seconds as a short duration, e.g. "3d 4h" or "12m". */
    public static function duration(int|float|null $seconds): string
    {
        $seconds = (int) ($seconds ?? 0);
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return $days.'d '.$hours.'h';
        }
        if ($hours > 0) {
            return $hours.'h '.$minutes.'m';
        }

        return $minutes.'m';
    }
}
