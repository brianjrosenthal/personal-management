<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../www/lib/NotificationManagement.php';
require_once __DIR__ . '/../../../www/lib/ObligationManagement.php';

final class NotificationManagementTest extends TestCase
{
    private UserContext $adminCtx;
    private int $adminId;
    private int $memberId;

    /** @var array<int, array{to:string, subject:string, html:string}> */
    private array $sentEmails = [];

    protected function setUp(): void
    {
        test_reset_users();
        pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['notification_log', 'obligation_completions', 'obligations'] as $t) {
            pdo()->exec("TRUNCATE TABLE $t");
        }
        pdo()->exec('SET FOREIGN_KEY_CHECKS=1');

        pdo()->exec("INSERT INTO users (first_name, last_name, email, password_hash, is_admin, email_verified_at)
                     VALUES ('Admin', 'One', 'admin@example.com', 'hash', 1, NOW())");
        $this->adminId = (int)pdo()->lastInsertId();
        $this->adminCtx = new UserContext($this->adminId, true);
        UserContext::set($this->adminCtx);

        pdo()->exec("INSERT INTO users (first_name, last_name, email, password_hash, is_admin, email_verified_at)
                     VALUES ('Member', 'Two', 'member@example.com', 'hash', 0, NOW())");
        $this->memberId = (int)pdo()->lastInsertId();

        $this->sentEmails = [];
    }

    private function fakeSender(bool $result = true): callable
    {
        return function (string $to, string $toName, string $subject, string $html) use ($result): bool {
            $this->sentEmails[] = ['to' => $to, 'subject' => $subject, 'html' => $html];
            return $result;
        };
    }

    private function runNotifications(string $today, bool $result = true): array
    {
        return NotificationManagement::runDailyNotifications($today, $this->fakeSender($result));
    }

    // Create an obligation due on a specific date via a one-off (simplest to control)
    private function createDue(string $dueDate, array $extra = []): int
    {
        return ObligationManagement::createObligation($this->adminCtx, $extra + [
            'title' => 'Obligation due ' . $dueDate,
            'recurrence_type' => 'does_not_repeat',
            'anchor_date' => $dueDate,
            'reminder_lead_days' => 7,
            'is_active' => 1,
        ]);
    }

    // Use a Tuesday so the weekly digest never interferes unless requested
    private const TUE = '2026-07-07';
    private const WED = '2026-07-08';
    private const MON = '2026-07-06';

    public function testDueTodayTriggersEmailToResponsible(): void
    {
        $this->createDue(self::TUE, ['responsible_user_id' => $this->memberId, 'title' => 'Pay property taxes']);

        $stats = $this->runNotifications(self::TUE);

        $this->assertSame(1, $stats['emails_sent']);
        $this->assertSame('member@example.com', $this->sentEmails[0]['to']);
        $this->assertStringContainsString('1 due today', $this->sentEmails[0]['subject']);
        $this->assertStringContainsString('Pay property taxes', $this->sentEmails[0]['html']);
    }

    public function testUnassignedObligationGoesToAdmins(): void
    {
        $this->createDue(self::TUE); // no responsible person

        $this->runNotifications(self::TUE);

        $this->assertCount(1, $this->sentEmails);
        $this->assertSame('admin@example.com', $this->sentEmails[0]['to']);
    }

    public function testResponsibleWithoutEmailFallsBackToAdmins(): void
    {
        // A no-login family member with no email address
        pdo()->exec("INSERT INTO users (first_name, last_name, email, password_hash) VALUES ('Kid', 'Three', NULL, '')");
        $kidId = (int)pdo()->lastInsertId();
        $this->createDue(self::TUE, ['responsible_user_id' => $kidId]);

        $this->runNotifications(self::TUE);

        $this->assertCount(1, $this->sentEmails);
        $this->assertSame('admin@example.com', $this->sentEmails[0]['to']);
    }

    public function testSecondRunSameDaySendsNothing(): void
    {
        $this->createDue(self::TUE);

        $first = $this->runNotifications(self::TUE);
        $second = $this->runNotifications(self::TUE);

        $this->assertSame(1, $first['emails_sent']);
        $this->assertSame(0, $second['emails_sent']);
        $this->assertCount(1, $this->sentEmails);
    }

    public function testOverdueTriggersAgainNextDay(): void
    {
        $this->createDue(self::TUE);

        $this->runNotifications(self::TUE);   // due today
        $this->runNotifications(self::WED);   // now 1 day overdue — daily overdue reminder

        $this->assertCount(2, $this->sentEmails);
        $this->assertStringContainsString('1 overdue', $this->sentEmails[1]['subject']);
        $this->assertStringContainsString('1 day overdue', $this->sentEmails[1]['html']);
    }

    public function testEnteredWindowTriggersOnceThenStaysQuiet(): void
    {
        // Due Jul 14, lead 7 → enters window on Jul 7
        $this->createDue('2026-07-14');

        $enter = $this->runNotifications(self::TUE);
        $this->assertSame(1, $enter['emails_sent']);
        $this->assertStringContainsString('1 upcoming', $this->sentEmails[0]['subject']);
        $this->assertStringContainsString('due in 7 days', $this->sentEmails[0]['html']);

        // Next day: still in window but no new trigger — silence
        $quiet = $this->runNotifications(self::WED);
        $this->assertSame(0, $quiet['emails_sent']);
    }

    public function testInWindowItemRidesAlongWhenSomethingElseTriggers(): void
    {
        $this->createDue('2026-07-14', ['title' => 'Upcoming rider']); // in window from Jul 7
        $this->runNotifications(self::TUE); // entered_window email

        // Wednesday: a new due-today obligation triggers; the rider must appear in content
        $this->createDue(self::WED, ['title' => 'Due Wednesday']);
        $stats = $this->runNotifications(self::WED);

        $this->assertSame(1, $stats['emails_sent']);
        $html = $this->sentEmails[1]['html'];
        $this->assertStringContainsString('Due Wednesday', $html);
        $this->assertStringContainsString('Upcoming rider', $html);
        // But the rider generated no notification row for Wednesday
        $this->assertFalse(NotificationManagement::wasSentOn(
            null, $this->adminId, 'weekly_summary', self::WED));
    }

    public function testWeeklySummaryOnMonday(): void
    {
        Settings::set('weekly_digest_enabled', '1');
        // Due in 5 days — outside the 3-day lead window, but within the weekly 7-day view
        $this->createDue('2026-07-11', ['reminder_lead_days' => 3, 'title' => 'Weekly item']);

        $stats = $this->runNotifications(self::MON);

        $this->assertSame(1, $stats['emails_sent']);
        $this->assertStringContainsString('Weekly item', $this->sentEmails[0]['html']);

        // Second Monday run: dedup
        $again = $this->runNotifications(self::MON);
        $this->assertSame(0, $again['emails_sent']);
    }

    public function testWeeklySummaryRespectsSetting(): void
    {
        Settings::set('weekly_digest_enabled', '0');
        $this->createDue('2026-07-11', ['reminder_lead_days' => 3]);

        $stats = $this->runNotifications(self::MON);
        $this->assertSame(0, $stats['emails_sent']);

        Settings::set('weekly_digest_enabled', '1');
    }

    public function testQuietDaySendsNothing(): void
    {
        // Due in 20 days with a 7-day lead: nothing actionable today
        $this->createDue('2026-07-27');

        $stats = $this->runNotifications(self::TUE);
        $this->assertSame(0, $stats['emails_sent']);
        $this->assertSame(1, $stats['obligations_evaluated']);
    }

    public function testFailedSendDoesNotBlockRetry(): void
    {
        $this->createDue(self::TUE);

        $failed = NotificationManagement::runDailyNotifications(self::TUE, $this->fakeSender(false));
        $this->assertSame(1, $failed['emails_failed']);

        // Retry the same day: the failed attempt does not count for dedup
        $retry = $this->runNotifications(self::TUE);
        $this->assertSame(1, $retry['emails_sent']);
    }

    public function testInactiveObligationsAreIgnored(): void
    {
        $this->createDue(self::TUE, ['is_active' => 0]);

        $stats = $this->runNotifications(self::TUE);
        $this->assertSame(0, $stats['emails_sent']);
    }

    public function testDryRunSendsAndRecordsNothing(): void
    {
        $this->createDue(self::TUE);

        $stats = NotificationManagement::runDailyNotifications(self::TUE, $this->fakeSender(), true);
        $this->assertSame(1, $stats['recipients_with_triggers']);
        $this->assertSame(0, $stats['emails_sent']);
        $this->assertCount(0, $this->sentEmails);

        // A real run afterwards still sends (nothing was recorded)
        $real = $this->runNotifications(self::TUE);
        $this->assertSame(1, $real['emails_sent']);
    }
}
