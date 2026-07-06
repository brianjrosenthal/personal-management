<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../www/lib/ObligationManagement.php';

// Pure tests of the recurrence engine (no database).
final class ObligationRecurrenceTest extends TestCase
{
    private function next(array $o, ?string $last, ?string $prevDue, string $today): ?string
    {
        return ObligationManagement::computeNextDueOn($o, $last, $prevDue, $today);
    }

    // --- does_not_repeat ---

    public function testDoesNotRepeatDueOnAnchorDate(): void
    {
        $o = ['recurrence_type' => 'does_not_repeat', 'anchor_date' => '2026-08-01'];
        $this->assertSame('2026-08-01', $this->next($o, null, null, '2026-07-06'));
    }

    public function testDoesNotRepeatStaysOverdueWhenPastDue(): void
    {
        $o = ['recurrence_type' => 'does_not_repeat', 'anchor_date' => '2026-06-01'];
        $this->assertSame('2026-06-01', $this->next($o, null, null, '2026-07-06'));
    }

    public function testDoesNotRepeatIsDoneAfterCompletion(): void
    {
        $o = ['recurrence_type' => 'does_not_repeat', 'anchor_date' => '2026-06-01'];
        $this->assertNull($this->next($o, '2026-07-01', '2026-06-01', '2026-07-06'));
    }

    // --- every_n_days / weeks ---

    public function testEveryNDaysAnchorInFuture(): void
    {
        $o = ['recurrence_type' => 'every_n_days', 'recurrence_interval' => 10, 'anchor_date' => '2026-08-01'];
        $this->assertSame('2026-08-01', $this->next($o, null, null, '2026-07-06'));
    }

    public function testEveryNDaysAnchorInPast(): void
    {
        $o = ['recurrence_type' => 'every_n_days', 'recurrence_interval' => 10, 'anchor_date' => '2026-06-01'];
        // Occurrences: Jun 1, 11, 21, Jul 1, 11 — first on/after Jul 6 is Jul 11
        $this->assertSame('2026-07-11', $this->next($o, null, null, '2026-07-06'));
    }

    public function testEveryNDaysLateCompletionSkipsMissedOccurrences(): void
    {
        $o = ['recurrence_type' => 'every_n_days', 'recurrence_interval' => 10, 'anchor_date' => '2026-06-01'];
        // Due Jun 11 but completed Jul 3 — next is first occurrence after Jul 3 (Jul 11), not Jun 21
        $this->assertSame('2026-07-11', $this->next($o, '2026-07-03', '2026-06-11', '2026-07-06'));
    }

    public function testEveryNDaysEarlyCompletionSatisfiesPendingDue(): void
    {
        $o = ['recurrence_type' => 'every_n_days', 'recurrence_interval' => 10, 'anchor_date' => '2026-06-01'];
        // Next due Jul 11, completed early on Jul 6 — advances past Jul 11 to Jul 21
        $this->assertSame('2026-07-21', $this->next($o, '2026-07-06', '2026-07-11', '2026-07-06'));
    }

    public function testEveryNWeeks(): void
    {
        $o = ['recurrence_type' => 'every_n_weeks', 'recurrence_interval' => 2, 'anchor_date' => '2026-07-01'];
        // Jul 1, 15, 29 — first on/after Jul 6 is Jul 15
        $this->assertSame('2026-07-15', $this->next($o, null, null, '2026-07-06'));
    }

    // --- every_n_months / years (clamping) ---

    public function testEveryNMonthsClampsShortMonthsButKeepsAnchorDay(): void
    {
        $o = ['recurrence_type' => 'every_n_months', 'recurrence_interval' => 1, 'anchor_date' => '2026-01-31'];
        // Jan 31 → Feb 28 (2026 not a leap year)
        $this->assertSame('2026-02-28', $this->next($o, null, null, '2026-02-01'));
        // After completing the February occurrence, March gets the 31st back
        $this->assertSame('2026-03-31', $this->next($o, '2026-02-28', '2026-02-28', '2026-02-28'));
    }

    public function testEveryNYearsFromLeapDay(): void
    {
        $o = ['recurrence_type' => 'every_n_years', 'recurrence_interval' => 1, 'anchor_date' => '2024-02-29'];
        // 2025 is not a leap year → Feb 28
        $this->assertSame('2025-02-28', $this->next($o, null, null, '2024-03-01'));
        // 2028 is a leap year → Feb 29 restored (k=4 from anchor)
        $this->assertSame('2028-02-29', $this->next($o, '2027-02-28', '2027-02-28', '2027-02-28'));
    }

    public function testEveryTenYears(): void
    {
        $o = ['recurrence_type' => 'every_n_years', 'recurrence_interval' => 10, 'anchor_date' => '2020-05-15'];
        $this->assertSame('2030-05-15', $this->next($o, null, null, '2026-07-06'));
    }

    // --- day_of_month ---

    public function testDayOfMonthUpcomingThisMonth(): void
    {
        $o = ['recurrence_type' => 'day_of_month', 'day_of_month' => 15];
        $this->assertSame('2026-07-15', $this->next($o, null, null, '2026-07-06'));
    }

    public function testDayOfMonthAlreadyPassedThisMonth(): void
    {
        $o = ['recurrence_type' => 'day_of_month', 'day_of_month' => 3];
        $this->assertSame('2026-08-03', $this->next($o, null, null, '2026-07-06'));
    }

    public function testDayOfMonthTodayCountsAsDue(): void
    {
        $o = ['recurrence_type' => 'day_of_month', 'day_of_month' => 6];
        $this->assertSame('2026-07-06', $this->next($o, null, null, '2026-07-06'));
    }

    public function testDayOfMonth31ClampsInShortMonths(): void
    {
        $o = ['recurrence_type' => 'day_of_month', 'day_of_month' => 31];
        $this->assertSame('2026-02-28', $this->next($o, null, null, '2026-02-01'));
        // After completing February, next is March 31
        $this->assertSame('2026-03-31', $this->next($o, '2026-02-28', '2026-02-28', '2026-02-28'));
    }

    // --- date_of_year ---

    public function testDateOfYearUpcoming(): void
    {
        $o = ['recurrence_type' => 'date_of_year', 'annual_month_day' => '04-01'];
        $this->assertSame('2027-04-01', $this->next($o, null, null, '2026-07-06'));
    }

    public function testDateOfYearStillAheadThisYear(): void
    {
        $o = ['recurrence_type' => 'date_of_year', 'annual_month_day' => '12-31'];
        $this->assertSame('2026-12-31', $this->next($o, null, null, '2026-07-06'));
    }

    public function testDateOfYearFeb29ClampsInNonLeapYears(): void
    {
        $o = ['recurrence_type' => 'date_of_year', 'annual_month_day' => '02-29'];
        $this->assertSame('2027-02-28', $this->next($o, null, null, '2026-07-06'));
        // Completing in 2027 → 2028 is a leap year, Feb 29
        $this->assertSame('2028-02-29', $this->next($o, '2027-02-28', '2027-02-28', '2027-02-28'));
    }

    public function testDateOfYearLateCompletionSkipsMissedYears(): void
    {
        $o = ['recurrence_type' => 'date_of_year', 'annual_month_day' => '04-01'];
        // Was due 2025-04-01, finally completed 2026-07-01 → next is 2027, not 2026
        $this->assertSame('2027-04-01', $this->next($o, '2026-07-01', '2025-04-01', '2026-07-06'));
    }

    // --- after_completion ---

    public function testAfterCompletionNeverCompletedDefaultsFromToday(): void
    {
        $o = ['recurrence_type' => 'after_completion', 'recurrence_interval' => 90, 'recurrence_unit' => 'days', 'anchor_date' => null];
        $this->assertSame('2026-10-04', $this->next($o, null, null, '2026-07-06'));
    }

    public function testAfterCompletionNeverCompletedUsesFirstDueDate(): void
    {
        $o = ['recurrence_type' => 'after_completion', 'recurrence_interval' => 90, 'recurrence_unit' => 'days', 'anchor_date' => '2026-07-10'];
        $this->assertSame('2026-07-10', $this->next($o, null, null, '2026-07-06'));
    }

    public function testAfterCompletionAdvancesFromCompletion(): void
    {
        $o = ['recurrence_type' => 'after_completion', 'recurrence_interval' => 6, 'recurrence_unit' => 'months', 'anchor_date' => null];
        $this->assertSame('2027-01-15', $this->next($o, '2026-07-15', '2026-07-10', '2026-07-15'));
    }

    public function testAfterCompletionMonthsClamp(): void
    {
        $o = ['recurrence_type' => 'after_completion', 'recurrence_interval' => 1, 'recurrence_unit' => 'months', 'anchor_date' => null];
        $this->assertSame('2026-02-28', $this->next($o, '2026-01-31', null, '2026-01-31'));
    }

    public function testAfterCompletionWeeks(): void
    {
        $o = ['recurrence_type' => 'after_completion', 'recurrence_interval' => 2, 'recurrence_unit' => 'weeks', 'anchor_date' => null];
        $this->assertSame('2026-07-20', $this->next($o, '2026-07-06', null, '2026-07-06'));
    }

    // --- describeRecurrence ---

    public function testDescribeRecurrence(): void
    {
        $this->assertSame('Does not repeat', ObligationManagement::describeRecurrence(['recurrence_type' => 'does_not_repeat']));
        $this->assertSame('Every 3 months', ObligationManagement::describeRecurrence(['recurrence_type' => 'every_n_months', 'recurrence_interval' => 3]));
        $this->assertSame('Every day', ObligationManagement::describeRecurrence(['recurrence_type' => 'every_n_days', 'recurrence_interval' => 1]));
        $this->assertSame('Monthly on day 15', ObligationManagement::describeRecurrence(['recurrence_type' => 'day_of_month', 'day_of_month' => 15]));
        $this->assertSame('Every year on Apr 1', ObligationManagement::describeRecurrence(['recurrence_type' => 'date_of_year', 'annual_month_day' => '04-01']));
        $this->assertSame('90 days after last completion', ObligationManagement::describeRecurrence(['recurrence_type' => 'after_completion', 'recurrence_interval' => 90, 'recurrence_unit' => 'days']));
        $this->assertSame('1 week after last completion', ObligationManagement::describeRecurrence(['recurrence_type' => 'after_completion', 'recurrence_interval' => 1, 'recurrence_unit' => 'weeks']));
    }
}
