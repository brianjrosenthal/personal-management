<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';

class ObligationManagement {
    // Categories from the spec (free text — the UI offers these via a datalist)
    public const CATEGORY_SUGGESTIONS = [
        'Financial', 'Insurance', 'Health', 'Home Maintenance', 'Vehicle', 'Legal', 'Personal', 'Other',
    ];

    public const RECURRENCE_TYPES = [
        'every_n_days', 'every_n_weeks', 'every_n_months', 'every_n_years',
        'day_of_month', 'date_of_year', 'after_completion',
    ];

    private static function pdo(): PDO {
        return pdo();
    }

    private static function log(string $action, ?int $obligationId, array $details = []): void {
        try {
            $ctx = UserContext::getLoggedInUserContext();
            $meta = $details;
            if ($obligationId !== null && !array_key_exists('obligation_id', $meta)) {
                $meta['obligation_id'] = $obligationId;
            }
            ActivityLog::log($ctx, $action, $meta);
        } catch (\Throwable $e) {
            // Best-effort logging
        }
    }

    private static function assertLoggedIn(?UserContext $ctx): UserContext {
        if (!$ctx) {
            throw new RuntimeException('Login required');
        }
        return $ctx;
    }

    // ===== Recurrence engine =====

    // Add months keeping the target day-of-month, clamping to the end of short
    // months (Jan 31 + 1 month = Feb 28/29, not Mar 2/3).
    public static function addMonthsClamped(string $date, int $months, ?int $preferredDay = null): string {
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        $day = $preferredDay ?? $d;
        $m += $months;
        $y += intdiv($m - 1, 12);
        $m = (($m - 1) % 12) + 1;
        if ($m <= 0) { $m += 12; $y -= 1; }
        $lastDay = (int)date('t', mktime(12, 0, 0, $m, 1, $y));
        return sprintf('%04d-%02d-%02d', $y, $m, min($day, $lastDay));
    }

    private static function addDays(string $date, int $days): string {
        return (new DateTimeImmutable($date))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }

    // The k-th occurrence (k >= 0) of a fixed-schedule obligation.
    private static function occurrence(array $o, int $k): string {
        $anchor = (string)($o['anchor_date'] ?? '');
        $n = max(1, (int)($o['recurrence_interval'] ?? 1));
        switch ($o['recurrence_type']) {
            case 'every_n_days':
                return self::addDays($anchor, $k * $n);
            case 'every_n_weeks':
                return self::addDays($anchor, $k * $n * 7);
            case 'every_n_months':
                return self::addMonthsClamped($anchor, $k * $n, (int)substr($anchor, 8, 2));
            case 'every_n_years':
                return self::addMonthsClamped($anchor, $k * $n * 12, (int)substr($anchor, 8, 2));
            case 'day_of_month':
                // Occurrences are month k counted from the anchor month (or epoch below)
                $base = substr((string)$o['_dom_base'], 0, 7) . '-01';
                return self::addMonthsClamped($base, $k, (int)$o['day_of_month']);
            case 'date_of_year':
                [$mm, $dd] = array_map('intval', explode('-', (string)$o['annual_month_day']));
                $year = (int)$o['_doy_base_year'] + $k;
                $lastDay = (int)date('t', mktime(12, 0, 0, $mm, 1, $year));
                return sprintf('%04d-%02d-%02d', $year, $mm, min($dd, $lastDay));
        }
        throw new LogicException('Not a fixed-schedule recurrence type');
    }

    // First occurrence strictly after $afterDate (or on/after when $inclusive).
    private static function firstOccurrence(array $o, string $afterDate, bool $inclusive): string {
        // Seed bases for the month/year-position types so occurrence(0) is near $afterDate
        if ($o['recurrence_type'] === 'day_of_month') {
            $o['_dom_base'] = self::addMonthsClamped(substr($afterDate, 0, 7) . '-01', -1);
        } elseif ($o['recurrence_type'] === 'date_of_year') {
            $o['_doy_base_year'] = (int)substr($afterDate, 0, 4) - 1;
        }

        for ($k = 0; $k < 10000; $k++) {
            $date = self::occurrence($o, $k);
            if ($inclusive ? ($date >= $afterDate) : ($date > $afterDate)) {
                return $date;
            }
        }
        throw new RuntimeException('Could not compute next occurrence.');
    }

    /**
     * Compute next_due_on for an obligation.
     *
     * $lastCompletedOn null (never completed):
     *   - fixed types: first occurrence on/after $today (anchor itself if in the future)
     *   - after_completion: anchor_date if set, else $today + N unit
     * With a completion:
     *   - fixed types: first occurrence strictly after max(completed_on, previous
     *     next_due). Late completion skips missed occurrences; early completion
     *     still satisfies the pending one.
     *   - after_completion: completed_on + N unit
     */
    public static function computeNextDueOn(array $o, ?string $lastCompletedOn, ?string $prevNextDue, string $today): string {
        if ($o['recurrence_type'] === 'after_completion') {
            $n = max(1, (int)$o['recurrence_interval']);
            $unit = (string)$o['recurrence_unit'];
            if ($lastCompletedOn === null) {
                return !empty($o['anchor_date'])
                    ? (string)$o['anchor_date']
                    : self::advanceByUnit($today, $n, $unit);
            }
            return self::advanceByUnit($lastCompletedOn, $n, $unit);
        }

        if ($lastCompletedOn === null) {
            // For every_n_* the anchor is required; for day/date types any base works
            $base = !empty($o['anchor_date']) ? (string)$o['anchor_date'] : $today;
            if (in_array($o['recurrence_type'], ['day_of_month', 'date_of_year'], true)) {
                return self::firstOccurrence($o, $today, true);
            }
            // First occurrence on/after today, starting from the anchor
            return $base >= $today ? $base : self::firstOccurrence($o, $today, true);
        }

        $after = ($prevNextDue !== null && $prevNextDue > $lastCompletedOn) ? $prevNextDue : $lastCompletedOn;
        return self::firstOccurrence($o, $after, false);
    }

    private static function advanceByUnit(string $date, int $n, string $unit): string {
        switch ($unit) {
            case 'days':
                return self::addDays($date, $n);
            case 'weeks':
                return self::addDays($date, $n * 7);
            case 'months':
                return self::addMonthsClamped($date, $n);
        }
        throw new InvalidArgumentException('Invalid recurrence unit.');
    }

    // Human-readable recurrence, e.g. "Every 3 months", "Every year on Apr 1",
    // "Monthly on day 15", "90 days after last completion"
    public static function describeRecurrence(array $o): string {
        $n = (int)($o['recurrence_interval'] ?? 0);
        switch ($o['recurrence_type']) {
            case 'every_n_days':
                return $n === 1 ? 'Every day' : "Every $n days";
            case 'every_n_weeks':
                return $n === 1 ? 'Every week' : "Every $n weeks";
            case 'every_n_months':
                return $n === 1 ? 'Every month' : "Every $n months";
            case 'every_n_years':
                return $n === 1 ? 'Every year' : "Every $n years";
            case 'day_of_month':
                return 'Monthly on day ' . (int)$o['day_of_month'];
            case 'date_of_year':
                [$mm, $dd] = array_map('intval', explode('-', (string)$o['annual_month_day']));
                return 'Every year on ' . date('M j', mktime(12, 0, 0, $mm, $dd, 2000));
            case 'after_completion':
                $unit = $n === 1 ? rtrim((string)$o['recurrence_unit'], 's') : (string)$o['recurrence_unit'];
                return "$n $unit after last completion";
        }
        return '';
    }

    // ===== Validation =====

    // Normalize/validate form fields. Returns the column => value map for insert/update.
    private static function normalizeFields(array $data): array {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        $type = (string)($data['recurrence_type'] ?? '');
        if (!in_array($type, self::RECURRENCE_TYPES, true)) {
            throw new InvalidArgumentException('Please choose a recurrence type.');
        }

        $interval = (int)($data['recurrence_interval'] ?? 0);
        $unit = (string)($data['recurrence_unit'] ?? '');
        $dayOfMonth = (int)($data['day_of_month'] ?? 0);
        $annualMonthDay = trim((string)($data['annual_month_day'] ?? ''));
        $anchorDate = trim((string)($data['anchor_date'] ?? ''));

        if ($anchorDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchorDate)) {
            throw new InvalidArgumentException('Start date must be a valid date.');
        }

        $fields = [
            'recurrence_interval' => null,
            'recurrence_unit' => null,
            'day_of_month' => null,
            'annual_month_day' => null,
            'anchor_date' => null,
        ];

        if (in_array($type, ['every_n_days', 'every_n_weeks', 'every_n_months', 'every_n_years'], true)) {
            if ($interval < 1) {
                throw new InvalidArgumentException('Repeat interval must be at least 1.');
            }
            if ($anchorDate === '') {
                throw new InvalidArgumentException('A start date is required for this recurrence type.');
            }
            $fields['recurrence_interval'] = $interval;
            $fields['anchor_date'] = $anchorDate;
        } elseif ($type === 'day_of_month') {
            if ($dayOfMonth < 1 || $dayOfMonth > 31) {
                throw new InvalidArgumentException('Day of month must be between 1 and 31.');
            }
            $fields['day_of_month'] = $dayOfMonth;
        } elseif ($type === 'date_of_year') {
            if (!preg_match('/^(\d{2})-(\d{2})$/', $annualMonthDay, $m)
                || (int)$m[1] < 1 || (int)$m[1] > 12 || (int)$m[2] < 1 || (int)$m[2] > 31) {
                throw new InvalidArgumentException('Annual date must be in MM-DD format.');
            }
            $fields['annual_month_day'] = $annualMonthDay;
        } else { // after_completion
            if ($interval < 1) {
                throw new InvalidArgumentException('Repeat interval must be at least 1.');
            }
            if (!in_array($unit, ['days', 'weeks', 'months'], true)) {
                throw new InvalidArgumentException('Please choose days, weeks, or months.');
            }
            $fields['recurrence_interval'] = $interval;
            $fields['recurrence_unit'] = $unit;
            $fields['anchor_date'] = $anchorDate !== '' ? $anchorDate : null; // optional first due date
        }

        $category = trim((string)($data['category'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $responsible = (int)($data['responsible_user_id'] ?? 0);
        $appliesTo = (int)($data['applies_to_user_id'] ?? 0);
        $leadDays = max(0, (int)($data['reminder_lead_days'] ?? 7));

        return [
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'category' => $category !== '' ? $category : null,
            'recurrence_type' => $type,
            'responsible_user_id' => $responsible > 0 ? $responsible : null,
            'applies_to_user_id' => $appliesTo > 0 ? $appliesTo : null,
            'reminder_lead_days' => $leadDays,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ] + $fields;
    }

    // ===== CRUD =====

    public static function createObligation(?UserContext $ctx, array $data, array $links = []): int {
        $ctx = self::assertLoggedIn($ctx);
        $fields = self::normalizeFields($data);
        $fields['next_due_on'] = self::computeNextDueOn($fields, null, null, date('Y-m-d'));
        $fields['created_by_user_id'] = $ctx->id;

        $cols = implode(', ', array_keys($fields));
        $marks = implode(', ', array_fill(0, count($fields), '?'));
        $st = self::pdo()->prepare("INSERT INTO obligations ($cols) VALUES ($marks)");
        $st->execute(array_values($fields));
        $id = (int)self::pdo()->lastInsertId();

        self::replaceLinks($id, $links);
        self::log('obligation.create', $id, ['title' => $fields['title']]);
        return $id;
    }

    public static function updateObligation(?UserContext $ctx, int $id, array $data, array $links = []): bool {
        self::assertLoggedIn($ctx);
        $existing = self::getObligation($id);
        if (!$existing) return false;

        $fields = self::normalizeFields($data);

        // If the schedule changed, recompute the next due date from the
        // completion history; otherwise keep the current one.
        $scheduleKeys = ['recurrence_type', 'recurrence_interval', 'recurrence_unit', 'day_of_month', 'annual_month_day', 'anchor_date'];
        $scheduleChanged = false;
        foreach ($scheduleKeys as $key) {
            if ((string)($existing[$key] ?? '') !== (string)($fields[$key] ?? '')) {
                $scheduleChanged = true;
                break;
            }
        }
        if ($scheduleChanged) {
            $fields['next_due_on'] = self::computeNextDueOn(
                $fields,
                $existing['last_completed_on'] ?: null,
                null,
                date('Y-m-d')
            );
        }

        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $st = self::pdo()->prepare("UPDATE obligations SET $set WHERE id = ?");
        $ok = $st->execute([...array_values($fields), $id]);

        if ($ok) {
            self::replaceLinks($id, $links);
            self::log('obligation.update', $id, ['title' => $fields['title'], 'schedule_changed' => $scheduleChanged]);
        }
        return $ok;
    }

    public static function deleteObligation(?UserContext $ctx, int $id): bool {
        self::assertLoggedIn($ctx);
        $obligation = self::getObligation($id);
        if (!$obligation) return false;

        $st = self::pdo()->prepare('DELETE FROM obligations WHERE id = ?');
        $ok = $st->execute([$id]); // completions and links cascade

        if ($ok) {
            self::log('obligation.delete', $id, ['title' => $obligation['title']]);
        }
        return $ok;
    }

    // One obligation with responsible/applies-to names and linked object ids
    public static function getObligation(int $id): ?array {
        $st = self::pdo()->prepare(
            "SELECT o.*,
                    CONCAT(r.first_name, ' ', r.last_name) AS responsible_name,
                    CONCAT(a.first_name, ' ', a.last_name) AS applies_to_name
             FROM obligations o
             LEFT JOIN users r ON r.id = o.responsible_user_id
             LEFT JOIN users a ON a.id = o.applies_to_user_id
             WHERE o.id = ? LIMIT 1"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) return null;

        $row['linked_asset_ids'] = self::linkedIds('obligation_assets', 'asset_id', $id);
        $row['linked_document_ids'] = self::linkedIds('obligation_documents', 'document_id', $id);
        $row['linked_policy_ids'] = self::linkedIds('obligation_policies', 'policy_id', $id);
        $row['linked_contact_ids'] = self::linkedIds('obligation_contacts', 'contact_id', $id);
        return $row;
    }

    public static function listObligations(string $search = '', bool $includeInactive = true): array {
        $sql = "SELECT o.*,
                       CONCAT(r.first_name, ' ', r.last_name) AS responsible_name,
                       CONCAT(a.first_name, ' ', a.last_name) AS applies_to_name
                FROM obligations o
                LEFT JOIN users r ON r.id = o.responsible_user_id
                LEFT JOIN users a ON a.id = o.applies_to_user_id";
        $where = [];
        $params = [];

        if (!$includeInactive) {
            $where[] = 'o.is_active = 1';
        }
        if ($search !== '') {
            $where[] = '(o.title LIKE ? OR o.category LIKE ? OR o.description LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term);
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY o.is_active DESC, o.next_due_on IS NULL, o.next_due_on, o.title';

        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    // Active obligations grouped for the homepage dashboard:
    // 'overdue' (due before today), 'due_today', and 'upcoming' (due within
    // $upcomingDays after today).
    public static function dashboardObligations(string $today, int $upcomingDays = 30): array {
        $horizon = self::addDays($today, $upcomingDays);
        $st = self::pdo()->prepare(
            "SELECT o.*,
                    CONCAT(r.first_name, ' ', r.last_name) AS responsible_name,
                    CONCAT(a.first_name, ' ', a.last_name) AS applies_to_name
             FROM obligations o
             LEFT JOIN users r ON r.id = o.responsible_user_id
             LEFT JOIN users a ON a.id = o.applies_to_user_id
             WHERE o.is_active = 1 AND o.next_due_on IS NOT NULL AND o.next_due_on <= ?
             ORDER BY o.next_due_on, o.title"
        );
        $st->execute([$horizon]);

        $groups = ['overdue' => [], 'due_today' => [], 'upcoming' => []];
        foreach ($st->fetchAll() as $row) {
            if ($row['next_due_on'] < $today) {
                $groups['overdue'][] = $row;
            } elseif ($row['next_due_on'] === $today) {
                $groups['due_today'][] = $row;
            } else {
                $groups['upcoming'][] = $row;
            }
        }
        return $groups;
    }

    // ===== Completions =====

    public static function addCompletion(?UserContext $ctx, int $obligationId, string $completedOn, ?string $notes = null): int {
        $ctx = self::assertLoggedIn($ctx);
        $obligation = self::getObligation($obligationId);
        if (!$obligation) {
            throw new InvalidArgumentException('Obligation not found.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $completedOn)) {
            throw new InvalidArgumentException('Completion date must be a valid date.');
        }
        $notes = $notes !== null ? trim($notes) : null;

        $st = self::pdo()->prepare(
            'INSERT INTO obligation_completions (obligation_id, completed_by_user_id, completed_on, notes) VALUES (?,?,?,?)'
        );
        $st->execute([$obligationId, $ctx->id, $completedOn, $notes !== '' ? $notes : null]);
        $id = (int)self::pdo()->lastInsertId();

        // Advance the cached schedule fields — but only when this is the newest
        // completion; backfilling an older history entry leaves the schedule alone.
        $prevLast = $obligation['last_completed_on'] ?: null;
        if ($prevLast === null || $completedOn >= $prevLast) {
            $nextDue = self::computeNextDueOn($obligation, $completedOn, $obligation['next_due_on'] ?: null, date('Y-m-d'));
            $upd = self::pdo()->prepare('UPDATE obligations SET last_completed_on = ?, next_due_on = ? WHERE id = ?');
            $upd->execute([$completedOn, $nextDue, $obligationId]);
        }

        self::log('obligation.complete', $obligationId, ['completed_on' => $completedOn, 'title' => $obligation['title']]);
        return $id;
    }

    // History rows may be deleted (a mistaken entry) but never edited — the
    // spec requires history to be permanent, so fixing an entry = delete + re-add.
    public static function deleteCompletion(?UserContext $ctx, int $completionId): bool {
        self::assertLoggedIn($ctx);

        $st = self::pdo()->prepare('SELECT * FROM obligation_completions WHERE id = ? LIMIT 1');
        $st->execute([$completionId]);
        $completion = $st->fetch();
        if (!$completion) return false;

        $del = self::pdo()->prepare('DELETE FROM obligation_completions WHERE id = ?');
        $ok = $del->execute([$completionId]);

        if ($ok) {
            self::recomputeScheduleFromHistory((int)$completion['obligation_id']);
            self::log('obligation.completion_delete', (int)$completion['obligation_id'], [
                'completed_on' => $completion['completed_on'],
            ]);
        }
        return $ok;
    }

    public static function listCompletions(int $obligationId): array {
        $st = self::pdo()->prepare(
            "SELECT oc.*, CONCAT(u.first_name, ' ', u.last_name) AS completed_by_name
             FROM obligation_completions oc
             LEFT JOIN users u ON u.id = oc.completed_by_user_id
             WHERE oc.obligation_id = ?
             ORDER BY oc.completed_on DESC, oc.id DESC"
        );
        $st->execute([$obligationId]);
        return $st->fetchAll();
    }

    // Rebuild last_completed_on/next_due_on from the completion history
    // (used after deleting a history row).
    private static function recomputeScheduleFromHistory(int $obligationId): void {
        $obligation = self::getObligation($obligationId);
        if (!$obligation) return;

        $st = self::pdo()->prepare('SELECT MAX(completed_on) FROM obligation_completions WHERE obligation_id = ?');
        $st->execute([$obligationId]);
        $lastCompleted = $st->fetchColumn() ?: null;

        $nextDue = self::computeNextDueOn($obligation, $lastCompleted, null, date('Y-m-d'));
        $upd = self::pdo()->prepare('UPDATE obligations SET last_completed_on = ?, next_due_on = ? WHERE id = ?');
        $upd->execute([$lastCompleted, $nextDue, $obligationId]);
    }

    // ===== Linked objects =====

    private static function linkedIds(string $table, string $column, int $obligationId): array {
        $st = self::pdo()->prepare("SELECT $column FROM $table WHERE obligation_id = ?");
        $st->execute([$obligationId]);
        return array_map('intval', array_column($st->fetchAll(), $column));
    }

    // $links: ['assets' => [ids], 'documents' => [ids], 'policies' => [ids], 'contacts' => [ids]]
    private static function replaceLinks(int $obligationId, array $links): void {
        $map = [
            'assets' => ['obligation_assets', 'asset_id'],
            'documents' => ['obligation_documents', 'document_id'],
            'policies' => ['obligation_policies', 'policy_id'],
            'contacts' => ['obligation_contacts', 'contact_id'],
        ];
        foreach ($map as $key => [$table, $column]) {
            if (!array_key_exists($key, $links)) continue;
            $ids = array_unique(array_filter(array_map('intval', (array)$links[$key]), fn($v) => $v > 0));

            $del = self::pdo()->prepare("DELETE FROM $table WHERE obligation_id = ?");
            $del->execute([$obligationId]);

            $ins = self::pdo()->prepare("INSERT IGNORE INTO $table (obligation_id, $column) VALUES (?, ?)");
            foreach ($ids as $id) {
                $ins->execute([$obligationId, $id]);
            }
        }
    }

    // Linked objects with names, for display: returns
    // ['assets' => rows, 'documents' => rows, 'policies' => rows, 'contacts' => rows]
    public static function getLinkedObjects(int $obligationId): array {
        $queries = [
            'assets' => "SELECT a.id, a.name FROM obligation_assets l JOIN assets a ON a.id = l.asset_id WHERE l.obligation_id = ? ORDER BY a.name",
            'documents' => "SELECT d.id, d.title AS name FROM obligation_documents l JOIN documents d ON d.id = l.document_id WHERE l.obligation_id = ? ORDER BY d.title",
            'policies' => "SELECT p.id, p.name FROM obligation_policies l JOIN insurance_policies p ON p.id = l.policy_id WHERE l.obligation_id = ? ORDER BY p.name",
            'contacts' => "SELECT c.id, c.name FROM obligation_contacts l JOIN contacts c ON c.id = l.contact_id WHERE l.obligation_id = ? ORDER BY c.name",
        ];
        $out = [];
        foreach ($queries as $key => $sql) {
            $st = self::pdo()->prepare($sql);
            $st->execute([$obligationId]);
            $out[$key] = $st->fetchAll();
        }
        return $out;
    }
}
