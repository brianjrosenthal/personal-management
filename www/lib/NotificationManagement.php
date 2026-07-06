<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../settings.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';

/**
 * Daily reminder engine for recurring obligations.
 *
 * Policy (see docs/email-notificaitons-spec.md + agreed refinements):
 *  - One digest email per recipient per run, showing the full picture.
 *  - An email is TRIGGERED only by: an overdue item (daily), an item due today,
 *    an item newly entering its reminder window (once per window), or the
 *    Monday "week ahead" summary (if enabled).
 *  - Items already inside their window ride along in triggered digests but do
 *    not trigger email themselves — quiet days stay quiet.
 *  - Recipients: the responsible person; if unassigned (or they have no email),
 *    all admins. Deduplicated via notification_log, so re-running is safe.
 *
 * Email is the only channel for now; the digest data structure returned by
 * collectRecipientDigests() is channel-agnostic so push/SMS can be added later.
 */
class NotificationManagement {
    private static function pdo(): PDO {
        return pdo();
    }

    // ===== Collection =====

    /**
     * Build the per-recipient digest for $today. Returns
     *   recipient_user_id => [
     *     'user' => user row,
     *     'overdue' | 'due_today' | 'upcoming' => obligation rows (with days_until),
     *     'triggers' => [[obligation_id|null, notification_type], ...],
     *   ]
     * plus '_stats' with the evaluated-obligation count.
     */
    public static function collectRecipientDigests(string $today, ?bool $isWeeklyDay = null): array {
        $isWeeklyDay = $isWeeklyDay ?? self::isWeeklyDay($today);

        $usersById = [];
        $admins = [];
        foreach (self::pdo()->query('SELECT * FROM users') as $u) {
            $usersById[(int)$u['id']] = $u;
            if (!empty($u['is_admin']) && !empty($u['email'])) {
                $admins[] = $u;
            }
        }

        $st = self::pdo()->prepare(
            "SELECT o.*, CONCAT(a.first_name, ' ', a.last_name) AS applies_to_name
             FROM obligations o
             LEFT JOIN users a ON a.id = o.applies_to_user_id
             WHERE o.is_active = 1 AND o.next_due_on IS NOT NULL
             ORDER BY o.next_due_on, o.title"
        );
        $st->execute();
        $obligations = $st->fetchAll();

        $digests = [];
        $ensure = function (array $user) use (&$digests): int {
            $id = (int)$user['id'];
            if (!isset($digests[$id])) {
                $digests[$id] = ['user' => $user, 'overdue' => [], 'due_today' => [], 'upcoming' => [], 'triggers' => []];
            }
            return $id;
        };

        foreach ($obligations as $o) {
            $daysUntil = self::daysBetween($today, (string)$o['next_due_on']);
            $lead = max(0, (int)$o['reminder_lead_days']);

            // Which bucket, if any, does this obligation land in today?
            $bucket = null;
            if ($daysUntil < 0) {
                $bucket = 'overdue';
            } elseif ($daysUntil === 0) {
                $bucket = 'due_today';
            } elseif ($daysUntil <= $lead || ($isWeeklyDay && $daysUntil <= 7)) {
                $bucket = 'upcoming';
            }
            if ($bucket === null) continue;

            // Recipient rules: responsible person with an email, else all admins
            $recipients = [];
            $responsible = $usersById[(int)($o['responsible_user_id'] ?? 0)] ?? null;
            if ($responsible && !empty($responsible['email'])) {
                $recipients = [$responsible];
            } else {
                $recipients = $admins;
            }
            if (empty($recipients)) continue;

            $o['days_until'] = $daysUntil;
            foreach ($recipients as $recipient) {
                $rid = $ensure($recipient);
                $digests[$rid][$bucket][] = $o;

                // Trigger determination (dedup against the notification log)
                $oid = (int)$o['id'];
                if ($bucket === 'overdue' && !self::wasSentOn($oid, $rid, 'overdue', $today)) {
                    $digests[$rid]['triggers'][] = [$oid, 'overdue'];
                } elseif ($bucket === 'due_today' && !self::wasSentOn($oid, $rid, 'due_today', $today)) {
                    $digests[$rid]['triggers'][] = [$oid, 'due_today'];
                } elseif ($bucket === 'upcoming' && $daysUntil <= $lead) {
                    // Entering the reminder window triggers once per window
                    $windowStart = date('Y-m-d', strtotime($o['next_due_on'] . ' -' . $lead . ' days'));
                    if (!self::wasSentSince($oid, $rid, 'entered_window', $windowStart)) {
                        $digests[$rid]['triggers'][] = [$oid, 'entered_window'];
                    }
                }
            }
        }

        // Weekly summary: on the weekly day, any recipient with content gets a
        // digest even if nothing else triggered.
        if ($isWeeklyDay) {
            foreach ($digests as $rid => &$digest) {
                $hasContent = !empty($digest['overdue']) || !empty($digest['due_today']) || !empty($digest['upcoming']);
                if ($hasContent && !self::wasSentOn(null, $rid, 'weekly_summary', $today)) {
                    $digest['triggers'][] = [null, 'weekly_summary'];
                }
            }
            unset($digest);
        }

        $digests['_stats'] = ['obligations_evaluated' => count($obligations)];
        return $digests;
    }

    // The Monday "week ahead" summary (controlled by the weekly_digest_enabled setting)
    public static function isWeeklyDay(string $today): bool {
        return date('N', strtotime($today)) === '1'
            && Settings::get('weekly_digest_enabled', '1') === '1';
    }

    // ===== Sending =====

    /**
     * Run the daily notification pass. Safe to run multiple times per day.
     *
     * @param callable|null $sendEmail fn(string $to, string $toName, string $subject, string $html): bool
     *                                 (defaults to the SMTP mailer; tests inject a fake)
     * @param bool $dryRun collect and report, but send nothing and record nothing
     * @return array stats
     */
    public static function runDailyNotifications(string $today, ?callable $sendEmail = null, bool $dryRun = false): array {
        if ($sendEmail === null) {
            require_once __DIR__ . '/../mailer.php';
            $sendEmail = function (string $to, string $toName, string $subject, string $html): bool {
                return send_email($to, $subject, $html, $toName);
            };
        }

        $digests = self::collectRecipientDigests($today);
        $stats = [
            'date' => $today,
            'obligations_evaluated' => $digests['_stats']['obligations_evaluated'],
            'recipients_with_triggers' => 0,
            'emails_sent' => 0,
            'emails_failed' => 0,
            'notifications_recorded' => 0,
            'dry_run' => $dryRun,
        ];
        unset($digests['_stats']);

        foreach ($digests as $rid => $digest) {
            if (empty($digest['triggers'])) continue;
            $stats['recipients_with_triggers']++;

            $user = $digest['user'];
            [$subject, $html] = self::buildDigestEmail($digest, $today);

            if ($dryRun) {
                continue;
            }

            $ok = false;
            $errorMessage = null;
            try {
                $ok = (bool)$sendEmail((string)$user['email'], trim($user['first_name'] . ' ' . $user['last_name']), $subject, $html);
            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();
            }

            if ($ok) {
                $stats['emails_sent']++;
            } else {
                $stats['emails_failed']++;
                $errorMessage = $errorMessage ?? 'send failed';
            }

            // Record every trigger. Failed sends are recorded for the audit trail
            // but do not block a retry (dedup only counts delivery_status='sent').
            foreach ($digest['triggers'] as [$obligationId, $type]) {
                self::recordNotification($obligationId, (int)$rid, $type, $today, (string)$user['email'], $ok, $errorMessage);
                $stats['notifications_recorded']++;
            }
        }

        return $stats;
    }

    // Subject + HTML body for one recipient's digest
    public static function buildDigestEmail(array $digest, string $today): array {
        $baseUrl = rtrim(Settings::get('site_base_url', 'https://familyoffice.brianrosenthal.org'), '/');
        $siteTitle = Settings::siteTitle();
        $user = $digest['user'];

        $subjectParts = [];
        if (!empty($digest['overdue'])) $subjectParts[] = count($digest['overdue']) . ' overdue';
        if (!empty($digest['due_today'])) $subjectParts[] = count($digest['due_today']) . ' due today';
        if (!empty($digest['upcoming'])) $subjectParts[] = count($digest['upcoming']) . ' upcoming';
        $subject = $siteTitle . ': ' . (!empty($subjectParts) ? implode(', ', $subjectParts) : 'weekly summary');

        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $total = count($digest['overdue']) + count($digest['due_today']) + count($digest['upcoming']);

        $item = function (array $o, string $suffix) use ($baseUrl, $e): string {
            $url = $baseUrl . '/obligations/edit.php?id=' . (int)$o['id'];
            $for = !empty($o['applies_to_name']) ? ' for ' . $e($o['applies_to_name']) : '';
            return '<li><a href="' . $e($url) . '">' . $e($o['title']) . '</a>' . $for . ' — ' . $e($suffix) . '</li>';
        };

        $html = '<p>Hello ' . $e($user['first_name']) . ',</p>'
              . '<p>You have ' . $total . ' ' . $e($siteTitle) . ' reminder' . ($total === 1 ? '' : 's') . '.</p>';

        if (!empty($digest['overdue'])) {
            $html .= '<h3 style="color:#b91c1c;">Overdue</h3><ul>';
            foreach ($digest['overdue'] as $o) {
                $n = -(int)$o['days_until'];
                $html .= $item($o, $n . ' day' . ($n === 1 ? '' : 's') . ' overdue');
            }
            $html .= '</ul>';
        }

        if (!empty($digest['due_today'])) {
            $html .= '<h3 style="color:#b45309;">Due Today</h3><ul>';
            foreach ($digest['due_today'] as $o) {
                $html .= $item($o, 'due today');
            }
            $html .= '</ul>';
        }

        if (!empty($digest['upcoming'])) {
            $html .= '<h3>Upcoming</h3><ul>';
            foreach ($digest['upcoming'] as $o) {
                $n = (int)$o['days_until'];
                $html .= $item($o, 'due in ' . $n . ' day' . ($n === 1 ? '' : 's') . ' (' . date('M j', strtotime($o['next_due_on'])) . ')');
            }
            $html .= '</ul>';
        }

        $html .= '<p><a href="' . $e($baseUrl) . '/">View all reminders</a></p>';

        return [$subject, $html];
    }

    // ===== Notification log =====

    // Was a notification of $type successfully sent for exactly $date?
    public static function wasSentOn(?int $obligationId, int $recipientUserId, string $type, string $date): bool {
        $sql = "SELECT 1 FROM notification_log
                WHERE recipient_user_id = ? AND notification_type = ? AND notification_date = ?
                  AND delivery_status = 'sent' AND obligation_id " . ($obligationId === null ? 'IS NULL' : '= ?') . ' LIMIT 1';
        $st = self::pdo()->prepare($sql);
        $params = [$recipientUserId, $type, $date];
        if ($obligationId !== null) $params[] = $obligationId;
        $st->execute($params);
        return (bool)$st->fetchColumn();
    }

    // Was a notification of $type successfully sent on/after $sinceDate?
    // (Used to fire entered_window only once per reminder window.)
    public static function wasSentSince(int $obligationId, int $recipientUserId, string $type, string $sinceDate): bool {
        $st = self::pdo()->prepare(
            "SELECT 1 FROM notification_log
             WHERE obligation_id = ? AND recipient_user_id = ? AND notification_type = ?
               AND notification_date >= ? AND delivery_status = 'sent' LIMIT 1"
        );
        $st->execute([$obligationId, $recipientUserId, $type, $sinceDate]);
        return (bool)$st->fetchColumn();
    }

    public static function recordNotification(?int $obligationId, int $recipientUserId, string $type, string $date, string $email, bool $sent, ?string $errorMessage = null): void {
        $st = self::pdo()->prepare(
            'INSERT INTO notification_log (obligation_id, recipient_user_id, notification_type, notification_date, email_address, delivery_status, error_message)
             VALUES (?,?,?,?,?,?,?)'
        );
        $st->execute([$obligationId, $recipientUserId, $type, $date, $email, $sent ? 'sent' : 'failed', $sent ? null : $errorMessage]);
    }

    // Whole days from $from to $to (negative when $to is in the past)
    private static function daysBetween(string $from, string $to): int {
        return (int)round(((new DateTimeImmutable($to))->getTimestamp() - (new DateTimeImmutable($from))->getTimestamp()) / 86400);
    }
}
