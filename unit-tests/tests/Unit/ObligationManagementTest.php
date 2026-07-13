<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../www/lib/ObligationManagement.php';
require_once __DIR__ . '/../../../www/lib/AssetManagement.php';
require_once __DIR__ . '/../../../www/lib/ContactManagement.php';

// Database-backed tests for obligation CRUD, completions, links, and the dashboard.
final class ObligationManagementTest extends TestCase
{
    private UserContext $ctx;

    protected function setUp(): void
    {
        test_reset_users();
        pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['obligation_assets', 'obligation_documents', 'obligation_policies', 'obligation_contacts',
                  'obligation_comments', 'obligation_completions', 'obligations', 'assets', 'contacts',
                  'contact_categories', 'private_files'] as $t) {
            pdo()->exec("TRUNCATE TABLE $t");
        }
        pdo()->exec('SET FOREIGN_KEY_CHECKS=1');

        pdo()->exec("INSERT INTO users (first_name, last_name, email, password_hash, email_verified_at)
                     VALUES ('Test', 'User', 'test@example.com', 'hash', NOW())");
        $this->ctx = new UserContext((int)pdo()->lastInsertId(), false);
        UserContext::set($this->ctx);
    }

    private function createAnnual(string $monthDay, array $extra = []): int
    {
        return ObligationManagement::createObligation($this->ctx, $extra + [
            'title' => 'Annual obligation',
            'recurrence_type' => 'date_of_year',
            'annual_month_day' => $monthDay,
            'is_active' => 1,
        ]);
    }

    public function testCreateComputesNextDue(): void
    {
        $due = date('Y-m-d', strtotime('+10 days'));
        $id = ObligationManagement::createObligation($this->ctx, [
            'title' => 'Renew passports',
            'recurrence_type' => 'every_n_years',
            'recurrence_interval' => 10,
            'anchor_date' => $due,
            'is_active' => 1,
        ]);

        $o = ObligationManagement::getObligation($id);
        $this->assertSame($due, $o['next_due_on']);
        $this->assertNull($o['last_completed_on']);
    }

    public function testCreateValidatesRecurrenceFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ObligationManagement::createObligation($this->ctx, [
            'title' => 'Broken',
            'recurrence_type' => 'every_n_months',
            'recurrence_interval' => 0, // invalid
            'anchor_date' => '2026-01-01',
        ]);
    }

    public function testCompletionAdvancesSchedule(): void
    {
        $today = date('Y-m-d');
        $id = ObligationManagement::createObligation($this->ctx, [
            'title' => 'Replace HVAC filter',
            'recurrence_type' => 'after_completion',
            'recurrence_interval' => 90,
            'recurrence_unit' => 'days',
            'is_active' => 1,
        ]);

        ObligationManagement::addCompletion($this->ctx, $id, $today, 'Changed filter');

        $o = ObligationManagement::getObligation($id);
        $this->assertSame($today, $o['last_completed_on']);
        $this->assertSame(date('Y-m-d', strtotime($today . ' +90 days')), $o['next_due_on']);

        $history = ObligationManagement::listCompletions($id);
        $this->assertCount(1, $history);
        $this->assertSame('Changed filter', $history[0]['notes']);
        $this->assertSame('Test User', $history[0]['completed_by_name']);
    }

    public function testBackfilledOlderCompletionDoesNotAdvanceSchedule(): void
    {
        $today = date('Y-m-d');
        $id = ObligationManagement::createObligation($this->ctx, [
            'title' => 'Dental cleaning',
            'recurrence_type' => 'after_completion',
            'recurrence_interval' => 6,
            'recurrence_unit' => 'months',
            'is_active' => 1,
        ]);

        ObligationManagement::addCompletion($this->ctx, $id, $today);
        $afterFirst = ObligationManagement::getObligation($id);

        // Backfill a completion from a year ago — schedule must not move
        ObligationManagement::addCompletion($this->ctx, $id, date('Y-m-d', strtotime('-1 year')));
        $afterBackfill = ObligationManagement::getObligation($id);

        $this->assertSame($afterFirst['next_due_on'], $afterBackfill['next_due_on']);
        $this->assertSame($today, $afterBackfill['last_completed_on']);
        $this->assertCount(2, ObligationManagement::listCompletions($id));
    }

    public function testDeleteCompletionRecomputesSchedule(): void
    {
        $today = date('Y-m-d');
        $id = ObligationManagement::createObligation($this->ctx, [
            'title' => 'Gutter cleaning',
            'recurrence_type' => 'after_completion',
            'recurrence_interval' => 90,
            'recurrence_unit' => 'days',
            'is_active' => 1,
        ]);
        $originalDue = ObligationManagement::getObligation($id)['next_due_on'];

        $completionId = ObligationManagement::addCompletion($this->ctx, $id, $today);
        $this->assertNotSame($originalDue, null);

        ObligationManagement::deleteCompletion($this->ctx, $completionId);
        $o = ObligationManagement::getObligation($id);
        $this->assertNull($o['last_completed_on']);
        $this->assertSame(date('Y-m-d', strtotime('+90 days')), $o['next_due_on']);
        $this->assertSame([], ObligationManagement::listCompletions($id));
    }

    public function testUpdateWithScheduleChangeRecomputesNextDue(): void
    {
        $id = $this->createAnnual('04-01', ['title' => 'Property taxes']);
        $before = ObligationManagement::getObligation($id);

        // Change the annual date — next due must be recomputed
        ObligationManagement::updateObligation($this->ctx, $id, [
            'title' => 'Property taxes',
            'recurrence_type' => 'date_of_year',
            'annual_month_day' => '11-15',
            'is_active' => 1,
        ]);
        $after = ObligationManagement::getObligation($id);
        $this->assertNotSame($before['next_due_on'], $after['next_due_on']);
        $this->assertSame('11-15', substr($after['next_due_on'], 5));
    }

    public function testUpdateWithoutScheduleChangeKeepsNextDue(): void
    {
        $id = $this->createAnnual('04-01', ['title' => 'Property taxes']);
        $before = ObligationManagement::getObligation($id);

        ObligationManagement::updateObligation($this->ctx, $id, [
            'title' => 'Property taxes (county)',
            'recurrence_type' => 'date_of_year',
            'annual_month_day' => '04-01',
            'is_active' => 1,
        ]);
        $after = ObligationManagement::getObligation($id);
        $this->assertSame($before['next_due_on'], $after['next_due_on']);
        $this->assertSame('Property taxes (county)', $after['title']);
    }

    public function testLinkedObjectsRoundTrip(): void
    {
        $assetId = AssetManagement::createAsset($this->ctx, ['name' => 'HVAC']);
        $contactId = ContactManagement::createContact($this->ctx, ['name' => 'HVAC Co'], ['HVAC']);

        $id = ObligationManagement::createObligation($this->ctx, [
            'title' => 'Service HVAC',
            'recurrence_type' => 'every_n_months',
            'recurrence_interval' => 6,
            'anchor_date' => date('Y-m-d'),
            'is_active' => 1,
        ], ['assets' => [$assetId], 'contacts' => [$contactId]]);

        $o = ObligationManagement::getObligation($id);
        $this->assertSame([$assetId], $o['linked_asset_ids']);
        $this->assertSame([$contactId], $o['linked_contact_ids']);

        $linked = ObligationManagement::getLinkedObjects($id);
        $this->assertSame('HVAC', $linked['assets'][0]['name']);
        $this->assertSame('HVAC Co', $linked['contacts'][0]['name']);

        // Unlink the contact via update
        ObligationManagement::updateObligation($this->ctx, $id, [
            'title' => 'Service HVAC',
            'recurrence_type' => 'every_n_months',
            'recurrence_interval' => 6,
            'anchor_date' => date('Y-m-d'),
            'is_active' => 1,
        ], ['assets' => [$assetId], 'contacts' => []]);

        $o = ObligationManagement::getObligation($id);
        $this->assertSame([$assetId], $o['linked_asset_ids']);
        $this->assertSame([], $o['linked_contact_ids']);
    }

    public function testDashboardBuckets(): void
    {
        $today = date('Y-m-d');

        // Overdue: anchored 5 days ago, never completed
        ObligationManagement::createObligation($this->ctx, [
            'title' => 'Overdue thing',
            'recurrence_type' => 'every_n_years',
            'recurrence_interval' => 1,
            'anchor_date' => date('Y-m-d', strtotime('-5 days')),
            'is_active' => 1,
        ]);
        // Hack: creation clamps to next occurrence >= today, so force overdue directly
        pdo()->exec("UPDATE obligations SET next_due_on = '" . date('Y-m-d', strtotime('-5 days')) . "' WHERE title = 'Overdue thing'");

        ObligationManagement::createObligation($this->ctx, [
            'title' => 'Due today thing',
            'recurrence_type' => 'every_n_years',
            'recurrence_interval' => 1,
            'anchor_date' => $today,
            'is_active' => 1,
        ]);

        ObligationManagement::createObligation($this->ctx, [
            'title' => 'Upcoming thing',
            'recurrence_type' => 'every_n_years',
            'recurrence_interval' => 1,
            'anchor_date' => date('Y-m-d', strtotime('+10 days')),
            'is_active' => 1,
        ]);

        ObligationManagement::createObligation($this->ctx, [
            'title' => 'Far future thing',
            'recurrence_type' => 'every_n_years',
            'recurrence_interval' => 1,
            'anchor_date' => date('Y-m-d', strtotime('+60 days')),
            'is_active' => 1,
        ]);

        ObligationManagement::createObligation($this->ctx, [
            'title' => 'Inactive thing',
            'recurrence_type' => 'every_n_years',
            'recurrence_interval' => 1,
            'anchor_date' => $today,
            'is_active' => 0,
        ]);

        $groups = ObligationManagement::dashboardObligations($today, 30);
        $this->assertSame(['Overdue thing'], array_column($groups['overdue'], 'title'));
        $this->assertSame(['Due today thing'], array_column($groups['due_today'], 'title'));
        $this->assertSame(['Upcoming thing'], array_column($groups['upcoming'], 'title'));
    }

    public function testOneTimeObligationLifecycle(): void
    {
        $due = date('Y-m-d', strtotime('+3 days'));
        $id = ObligationManagement::createObligation($this->ctx, [
            'title' => 'Set up the trust',
            'recurrence_type' => 'does_not_repeat',
            'anchor_date' => $due,
            'is_active' => 1,
        ]);

        $o = ObligationManagement::getObligation($id);
        $this->assertSame($due, $o['next_due_on']);
        $this->assertContains('Set up the trust', array_column(
            ObligationManagement::dashboardObligations(date('Y-m-d'), 30)['upcoming'], 'title'));

        // Completing it removes it from the schedule and the dashboard
        ObligationManagement::addCompletion($this->ctx, $id, date('Y-m-d'));
        $o = ObligationManagement::getObligation($id);
        $this->assertNull($o['next_due_on']);
        $groups = ObligationManagement::dashboardObligations(date('Y-m-d'), 30);
        $all = array_merge($groups['overdue'], $groups['due_today'], $groups['upcoming']);
        $this->assertNotContains('Set up the trust', array_column($all, 'title'));

        // Removing the completion restores the due date
        $history = ObligationManagement::listCompletions($id);
        ObligationManagement::deleteCompletion($this->ctx, (int)$history[0]['id']);
        $this->assertSame($due, ObligationManagement::getObligation($id)['next_due_on']);
    }

    public function testOneTimeObligationRequiresDueDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ObligationManagement::createObligation($this->ctx, [
            'title' => 'No date',
            'recurrence_type' => 'does_not_repeat',
            'is_active' => 1,
        ]);
    }

    public function testUpdateLinksStandalone(): void
    {
        $assetId = AssetManagement::createAsset($this->ctx, ['name' => 'Roof']);
        $contactId = ContactManagement::createContact($this->ctx, ['name' => 'Roofer Co'], ['Contractor']);
        $id = $this->createAnnual('06-01');

        ObligationManagement::updateLinks($this->ctx, $id, ['assets' => [$assetId], 'contacts' => [$contactId]]);
        $o = ObligationManagement::getObligation($id);
        $this->assertSame([$assetId], $o['linked_asset_ids']);
        $this->assertSame([$contactId], $o['linked_contact_ids']);

        // Deselecting everything clears the provided types
        ObligationManagement::updateLinks($this->ctx, $id, ['assets' => [], 'documents' => [], 'policies' => [], 'contacts' => []]);
        $o = ObligationManagement::getObligation($id);
        $this->assertSame([], $o['linked_asset_ids']);
        $this->assertSame([], $o['linked_contact_ids']);
    }

    public function testMainFormUpdateLeavesLinksUntouched(): void
    {
        $assetId = AssetManagement::createAsset($this->ctx, ['name' => 'Boiler']);
        $id = $this->createAnnual('06-01', ['title' => 'Service boiler']);
        ObligationManagement::updateLinks($this->ctx, $id, ['assets' => [$assetId]]);

        // The edit form saves without any links argument — links must survive
        ObligationManagement::updateObligation($this->ctx, $id, [
            'title' => 'Service boiler (renamed)',
            'recurrence_type' => 'date_of_year',
            'annual_month_day' => '06-01',
            'is_active' => 1,
        ]);

        $o = ObligationManagement::getObligation($id);
        $this->assertSame('Service boiler (renamed)', $o['title']);
        $this->assertSame([$assetId], $o['linked_asset_ids']);
    }

    // ===== Comments / updates =====

    public function testAddCommentAndListUpdates(): void
    {
        $id = $this->createAnnual('06-01');
        $c1 = ObligationManagement::addComment($this->ctx, $id, 'Called the county, waiting for a callback.');
        $c2 = ObligationManagement::addComment($this->ctx, $id, 'They called back — payment plan confirmed.');

        $updates = ObligationManagement::listUpdates($id);
        $this->assertCount(2, $updates);
        // Newest first (same timestamp resolves by id)
        $this->assertSame($c2, (int)$updates[0]['id']);
        $this->assertSame($c1, (int)$updates[1]['id']);
        $this->assertSame('comment', $updates[0]['kind']);
        $this->assertSame('Test User', $updates[0]['created_by_name']);
    }

    public function testAddCommentRejectsEmptyUpdate(): void
    {
        $id = $this->createAnnual('06-01');
        $this->expectException(InvalidArgumentException::class);
        ObligationManagement::addComment($this->ctx, $id, '   ');
    }

    public function testCommentWithAttachment(): void
    {
        $id = $this->createAnnual('06-01');
        $fileId = Files::insertPrivateFile('receipt-bytes', 'application/pdf', 'receipt.pdf', $this->ctx->id);
        $commentId = ObligationManagement::addComment($this->ctx, $id, 'Receipt attached.', $fileId);

        $updates = ObligationManagement::listUpdates($id);
        $this->assertSame('receipt.pdf', $updates[0]['original_filename']);

        // Removing the update deletes the attachment bytes
        $this->assertTrue(ObligationManagement::deleteComment($this->ctx, $commentId));
        $this->assertNull(Files::getPrivateFileMeta($fileId));
        $this->assertSame([], ObligationManagement::listUpdates($id));
    }

    public function testCommentLinkedToCompletionRemovesCompletionOnDelete(): void
    {
        $today = date('Y-m-d');
        $id = ObligationManagement::createObligation($this->ctx, [
            'title' => 'Gutters',
            'recurrence_type' => 'after_completion',
            'recurrence_interval' => 90,
            'recurrence_unit' => 'days',
            'is_active' => 1,
        ]);
        $originalDue = ObligationManagement::getObligation($id)['next_due_on'];

        // The update_eval flow: completion first, then the linked comment
        $completionId = ObligationManagement::addCompletion($this->ctx, $id, $today);
        $commentId = ObligationManagement::addComment($this->ctx, $id, 'Done, receipt to follow.', null, $completionId);

        $o = ObligationManagement::getObligation($id);
        $this->assertSame($today, $o['last_completed_on']);

        $updates = ObligationManagement::listUpdates($id);
        $this->assertCount(1, $updates, 'Linked completion must not appear as a separate bare entry');
        $this->assertSame('comment', $updates[0]['kind']);
        $this->assertSame($today, $updates[0]['completed_on']);

        // Deleting the update undoes the completion and recomputes the schedule
        $this->assertTrue(ObligationManagement::deleteComment($this->ctx, $commentId));
        $o = ObligationManagement::getObligation($id);
        $this->assertNull($o['last_completed_on']);
        $this->assertSame($originalDue, $o['next_due_on']);
        $this->assertSame([], ObligationManagement::listCompletions($id));
    }

    public function testListUpdatesIncludesBareCompletions(): void
    {
        $id = $this->createAnnual('06-01');
        ObligationManagement::addCompletion($this->ctx, $id, date('Y-m-d'), 'Quick complete from homepage');
        ObligationManagement::addComment($this->ctx, $id, 'A separate comment.');

        $updates = ObligationManagement::listUpdates($id);
        $this->assertCount(2, $updates);
        $kinds = array_column($updates, 'kind');
        sort($kinds);
        $this->assertSame(['comment', 'completion'], $kinds);
    }

    public function testDeleteObligationDeletesCommentAttachmentBytes(): void
    {
        $id = $this->createAnnual('06-01');
        $fileId = Files::insertPrivateFile('receipt-bytes', 'application/pdf', 'receipt.pdf', $this->ctx->id);
        ObligationManagement::addComment($this->ctx, $id, null, $fileId);

        $this->assertTrue(ObligationManagement::deleteObligation($this->ctx, $id));
        $this->assertNull(Files::getPrivateFileMeta($fileId), 'Attachment bytes should not linger after obligation delete');
    }

    public function testDeleteObligationCascades(): void
    {
        $id = $this->createAnnual('06-01');
        ObligationManagement::addCompletion($this->ctx, $id, date('Y-m-d'));

        $this->assertTrue(ObligationManagement::deleteObligation($this->ctx, $id));
        $this->assertNull(ObligationManagement::getObligation($id));

        $st = pdo()->prepare('SELECT COUNT(*) FROM obligation_completions WHERE obligation_id = ?');
        $st->execute([$id]);
        $this->assertSame(0, (int)$st->fetchColumn());
    }
}
