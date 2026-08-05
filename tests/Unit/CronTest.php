<?php

namespace Tests\Unit;

use App\Support\Cron;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The scheduler originally read only the hour and minute, so the seeded
 * "Weekly Game Update" fired every day. These pin down every field.
 */
class CronTest extends TestCase
{
    private function at(string $when): Carbon
    {
        return Carbon::parse($when);
    }

    public function test_daily_matches_only_at_its_hour(): void
    {
        $cron = Cron::parse('0 5 * * *');

        $this->assertTrue($cron->matches($this->at('2026-08-05 05:00')));
        $this->assertFalse($cron->matches($this->at('2026-08-05 05:01')));
        $this->assertFalse($cron->matches($this->at('2026-08-05 06:00')));
    }

    /** The bug this whole class exists for. */
    public function test_weekly_matches_only_on_its_weekday(): void
    {
        $cron = Cron::parse('30 6 * * 2');

        // 2026-08-04 is a Tuesday, 2026-08-05 a Wednesday.
        $this->assertTrue($cron->matches($this->at('2026-08-04 06:30')));
        $this->assertFalse($cron->matches($this->at('2026-08-05 06:30')));
        $this->assertFalse($cron->matches($this->at('2026-08-04 06:31')));
    }

    public function test_steps_and_lists_and_ranges(): void
    {
        $quarterly = Cron::parse('*/15 * * * *');
        $this->assertTrue($quarterly->matches($this->at('2026-08-05 10:30')));
        $this->assertFalse($quarterly->matches($this->at('2026-08-05 10:31')));

        $list = Cron::parse('0 3,15 * * *');
        $this->assertTrue($list->matches($this->at('2026-08-05 15:00')));
        $this->assertFalse($list->matches($this->at('2026-08-05 04:00')));

        $range = Cron::parse('0 9-17 * * *');
        $this->assertTrue($range->matches($this->at('2026-08-05 12:00')));
        $this->assertFalse($range->matches($this->at('2026-08-05 18:00')));
    }

    /** Day-of-month and day-of-week are ORed, as in every other cron. */
    public function test_day_fields_are_ored_when_both_are_set(): void
    {
        $cron = Cron::parse('0 0 1 * 1');

        $this->assertTrue($cron->matches($this->at('2026-08-01 00:00')), 'the first of the month');
        $this->assertTrue($cron->matches($this->at('2026-08-03 00:00')), 'a Monday');
        $this->assertFalse($cron->matches($this->at('2026-08-05 00:00')), 'neither');
    }

    public function test_sunday_is_both_zero_and_seven(): void
    {
        $sunday = $this->at('2026-08-02 00:00');

        $this->assertTrue(Cron::parse('0 0 * * 0')->matches($sunday));
        $this->assertTrue(Cron::parse('0 0 * * 7')->matches($sunday));
    }

    public function test_next_run_lands_a_week_out_for_a_weekly_schedule(): void
    {
        $next = Cron::parse('30 6 * * 2')->nextRun($this->at('2026-08-04 07:00'));

        $this->assertNotNull($next);
        $this->assertSame('2026-08-11 06:30', $next->format('Y-m-d H:i'));
    }

    /** An expression that can never match returns null rather than spinning. */
    public function test_an_impossible_expression_gives_up(): void
    {
        $this->assertNull(Cron::parse('0 0 31 2 *')->nextRun($this->at('2026-08-05 00:00')));
    }

    public function test_validity_check(): void
    {
        $this->assertTrue(Cron::isValid('*/15 9-17 * * 1,3,5'));
        $this->assertFalse(Cron::isValid('every 5 minutes'));
    }
}
