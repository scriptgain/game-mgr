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

    /**
     * MiB as GiB where that is clearer. Limits are stored in MiB throughout.
     *
     * Only for LIMITS. Anything the node daemon reports about the machine, so
     * reported_memory, reported_disk, and every node_metrics row, is in BYTES
     * and wants bytes() instead. Passing bytes here inflates the number by
     * 1,048,576 and still renders happily, which is exactly how a node page
     * came to claim 793,120 GiB of memory.
     */
    public static function mib(int|float|null $mib, int $precision = 1): string
    {
        $mib = (float) ($mib ?? 0);

        return $mib >= 1024
            ? number_format($mib / 1024, $precision).' GiB'
            : number_format($mib).' MiB';
    }

    /**
     * A used-of-capacity pair in ONE unit, e.g. "0 / 12 GiB".
     *
     * Formatting each side separately gives "0 MiB of 12.0 GiB", which carries
     * two units for one fact and is wide enough to wrap in a table column. The
     * unit is chosen from the capacity, since that is the side that does not
     * move.
     */
    public static function mibPair(int|float|null $used, int|float|null $capacity, int $precision = 1): string
    {
        $used = (float) ($used ?? 0);
        $capacity = (float) ($capacity ?? 0);

        $asGib = $capacity >= 1024;
        $unit = $asGib ? 'GiB' : 'MiB';
        $scale = $asGib ? 1024 : 1;

        // Past 10 the decimal is noise in a table column: 76.8 GiB of capacity
        // and 77 GiB of capacity are the same fact, and one of them wraps.
        $trim = static fn (float $v) => (fmod($v, 1) === 0.0 || $v >= 10)
            ? number_format($v)
            : number_format($v, $precision);

        return $trim($used / $scale).' / '.$trim($capacity / $scale).' '.$unit;
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
