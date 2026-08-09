<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Grouping timestamps into buckets, on whichever database this install runs.
 *
 * Metrics are sampled far more often than any chart wants to draw, so both the
 * dashboard and the per-server graphs group rows by hour or by day in SQL
 * rather than dragging every sample into PHP. The function that does that is
 * spelled differently by every engine, and the panel supports more than one:
 * config/database.php defaults to sqlite while .env.example says mysql.
 *
 * Both call sites used MySQL's DATE_FORMAT directly, so on a default sqlite
 * install the dashboard and the metrics endpoint answered with a 500 saying
 * "no such function: DATE_FORMAT". Nothing in the test suite reached either
 * one, which is why it survived.
 */
class TimeBucket
{
    /** Bucket sizes, named rather than passed as a format string. */
    public const MINUTE = 'minute';

    public const HOUR = 'hour';

    public const DAY = 'day';

    /**
     * A SQL expression that truncates a timestamp column to a bucket.
     *
     * Returns an expression rather than a bound parameter because it names a
     * column, and a column name is not something a placeholder can carry.
     * The column is therefore never taken from user input; callers pass a
     * literal, and this refuses anything that is not a plain identifier.
     */
    public static function expression(string $column, string $size = self::HOUR): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException('A bucket column has to be a plain column name.');
        }

        return match (static::driver()) {
            'sqlite' => "strftime('".static::sqliteFormat($size)."', $column)",
            'pgsql' => "to_char($column, '".static::postgresFormat($size)."')",
            default => "DATE_FORMAT($column, '".static::mysqlFormat($size)."')",
        };
    }

    /** Which bucket a range of hours deserves. */
    public static function sizeForHours(int $hours): string
    {
        return match (true) {
            $hours <= 6 => self::MINUTE,
            $hours <= 168 => self::HOUR,
            default => self::DAY,
        };
    }

    protected static function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * Every engine below produces the same string for the same instant, so a
     * chart looks identical whichever database is underneath and a label never
     * has to be reformatted per driver.
     */
    protected static function mysqlFormat(string $size): string
    {
        return match ($size) {
            self::MINUTE => '%Y-%m-%d %H:%i:00',
            self::DAY => '%Y-%m-%d 00:00:00',
            default => '%Y-%m-%d %H:00:00',
        };
    }

    protected static function sqliteFormat(string $size): string
    {
        return match ($size) {
            self::MINUTE => '%Y-%m-%d %H:%M:00',
            self::DAY => '%Y-%m-%d 00:00:00',
            default => '%Y-%m-%d %H:00:00',
        };
    }

    protected static function postgresFormat(string $size): string
    {
        return match ($size) {
            self::MINUTE => 'YYYY-MM-DD HH24:MI:00',
            self::DAY => 'YYYY-MM-DD 00:00:00',
            default => 'YYYY-MM-DD HH24:00:00',
        };
    }
}
