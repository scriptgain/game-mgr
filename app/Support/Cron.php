<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * A five field cron expression, matched properly.
 *
 * This exists because the first version of the scheduler read only the hour and
 * minute and ignored the day fields entirely, which meant the "Weekly Game
 * Update" the seeder itself creates fired every single day. A schedule that
 * says weekly and runs daily is worse than one that does not run at all: the
 * first is a surprise, the second is a bug report.
 *
 * Supports what the schedule form can actually produce: *, a number, a,b,c
 * lists, a-b ranges, and a step over either (asterisk-slash-n or a-b/n). No
 * dependency needed for that, and adding one for thirty lines of integer
 * matching would be its own kind of mistake.
 */
class Cron
{
    /**
     * @param  string  $minute  0 to 59
     * @param  string  $hour  0 to 23
     * @param  string  $dayOfMonth  1 to 31
     * @param  string  $month  1 to 12
     * @param  string  $dayOfWeek  0 to 7, where both 0 and 7 mean Sunday
     */
    public function __construct(
        private string $minute = '*',
        private string $hour = '*',
        private string $dayOfMonth = '*',
        private string $month = '*',
        private string $dayOfWeek = '*',
    ) {}

    public static function parse(string $expression): self
    {
        $fields = preg_split('/\s+/', trim($expression)) ?: [];
        $fields = array_pad(array_slice($fields, 0, 5), 5, '*');

        return new self(...$fields);
    }

    /** Is this expression something the matcher understands? */
    public static function isValid(string $expression): bool
    {
        foreach (preg_split('/\s+/', trim($expression)) ?: [] as $field) {
            if (! preg_match('/^(\*|\d+)(-\d+)?(\/\d+)?(,(\*|\d+)(-\d+)?(\/\d+)?)*$/', $field)) {
                return false;
            }
        }

        return true;
    }

    public function matches(Carbon $moment): bool
    {
        return $this->fieldMatches($this->minute, (int) $moment->minute, 0, 59)
            && $this->fieldMatches($this->hour, (int) $moment->hour, 0, 23)
            && $this->fieldMatches($this->month, (int) $moment->month, 1, 12)
            && $this->dayMatches($moment);
    }

    /**
     * Day-of-month and day-of-week are ORed when both are restricted, which is
     * the behaviour of every cron implementation and surprises people who
     * expect AND. "0 0 1 * 1" is the first of the month AND every Monday, not
     * only Mondays that fall on the first.
     */
    private function dayMatches(Carbon $moment): bool
    {
        $domRestricted = trim($this->dayOfMonth) !== '*';
        $dowRestricted = trim($this->dayOfWeek) !== '*';

        $dom = $this->fieldMatches($this->dayOfMonth, (int) $moment->day, 1, 31);
        // Carbon gives 0 for Sunday; cron accepts 0 or 7 for it.
        $weekday = (int) $moment->dayOfWeek;
        $dow = $this->fieldMatches($this->dayOfWeek, $weekday, 0, 7)
            || ($weekday === 0 && $this->fieldMatches($this->dayOfWeek, 7, 0, 7));

        return match (true) {
            $domRestricted && $dowRestricted => $dom || $dow,
            $domRestricted => $dom,
            $dowRestricted => $dow,
            default => true,
        };
    }

    private function fieldMatches(string $field, int $value, int $min, int $max): bool
    {
        foreach (explode(',', trim($field)) as $part) {
            if ($this->partMatches(trim($part), $value, $min, $max)) {
                return true;
            }
        }

        return false;
    }

    private function partMatches(string $part, int $value, int $min, int $max): bool
    {
        if ($part === '') {
            return false;
        }

        $step = 1;
        if (str_contains($part, '/')) {
            [$part, $stepText] = explode('/', $part, 2);
            $step = max(1, (int) $stepText);
        }

        $from = $min;
        $to = $max;

        if ($part !== '*' && $part !== '') {
            if (str_contains($part, '-')) {
                [$fromText, $toText] = explode('-', $part, 2);
                $from = (int) $fromText;
                $to = (int) $toText;
            } else {
                $from = $to = (int) $part;
            }
        }

        if ($value < $from || $value > $to) {
            return false;
        }

        return ($value - $from) % $step === 0;
    }

    /**
     * The next moment at or after $from that matches.
     *
     * Walks minute by minute, capped at roughly four years so an expression
     * that can never match (31 February) returns null rather than spinning.
     */
    public function nextRun(?Carbon $from = null): ?Carbon
    {
        $moment = ($from ?? Carbon::now())->copy()->startOfMinute()->addMinute();

        // Whole days are skipped when the date fields rule them out, so a yearly
        // schedule does not cost half a million iterations.
        for ($i = 0; $i < 366 * 4; $i++) {
            if (! $this->fieldMatches($this->month, (int) $moment->month, 1, 12) || ! $this->dayMatches($moment)) {
                $moment->addDay()->startOfDay();

                continue;
            }

            $endOfDay = $moment->copy()->endOfDay();
            while ($moment->lte($endOfDay)) {
                if ($this->matches($moment)) {
                    return $moment;
                }
                $moment->addMinute();
            }
        }

        return null;
    }
}
