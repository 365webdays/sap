<?php
/**
 * Bulk email to adorer groups.
 *
 * Recipients are resolved from a named group, then each message is sent
 * individually. One bad address must not abort the whole send: failures are
 * counted and returned so the admin sees a partial-failure result rather than
 * a blanket error.
 */

class BulkMail
{
    public const GROUPS = ['all', 'active', 'inactive', 'missed'];

    /**
     * Resolve a recipient group to a list of adorers.
     *
     * @return list<array{id:int, full_name:string, email:string}>
     */
    public static function recipients(string $group): array
    {
        $db = Database::getConnection();

        if ($group === 'all') {
            $sql = 'SELECT id, full_name, email FROM users WHERE is_active = 1 ORDER BY full_name';
            $stmt = $db->query($sql);
        } elseif ($group === 'inactive') {
            $stmt = $db->query(
                'SELECT id, full_name, email FROM users WHERE is_active = 0 ORDER BY full_name'
            );
        } elseif ($group === 'missed') {
            // Adorers who missed their scheduled hour in the last 7 days.
            $now = new DateTimeImmutable('now');
            $from = $now->modify('-7 days')->setTime(0, 0);
            $missed = MissedAttendance::between($from, $now, $now);

            $ids = array_values(array_unique(array_map(fn($r) => $r['user_id'], $missed)));
            if ($ids === []) {
                return [];
            }

            $placeholders = implode(',', array_map('intval', $ids));
            $stmt = $db->query(
                "SELECT id, full_name, email FROM users
                 WHERE id IN ({$placeholders}) AND is_active = 1
                 ORDER BY full_name"
            );
        } else {
            // 'active' and any safe fallback.
            $stmt = $db->query(
                'SELECT id, full_name, email FROM users WHERE is_active = 1 ORDER BY full_name'
            );
        }

        $recipients = [];
        foreach ($stmt->fetchAll() as $row) {
            // A malformed address in the DB would fail every send; skip it
            // rather than poisoning the whole batch.
            if (filter_var($row['email'], FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $recipients[] = [
                'id' => (int) $row['id'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
            ];
        }
        return $recipients;
    }

    /**
     * Send to a resolved list and log the outcome.
     *
     * @param list<array{id:int, full_name:string, email:string}> $recipients
     * @return array{sent:int, failed:int, failures:list<string>}
     */
    public static function send(
        array $recipients,
        string $group,
        string $subject,
        string $bodyHtml,
        int $adminId
    ): array {
        $template = EmailTemplate::announcement($subject, $bodyHtml);

        $sent = 0;
        $failed = 0;
        $failures = [];

        foreach ($recipients as $recipient) {
            $ok = Mailer::send(
                $recipient['email'],
                $recipient['full_name'],
                $template['subject'],
                $template['html'],
                $template['text']
            );

            if ($ok) {
                $sent++;
            } else {
                $failed++;
                $failures[] = $recipient['email'];
            }
        }

        // Log regardless of partial failure — the admin needs the audit trail,
        // and "sent 3 of 10" is more useful than no record at all.
        self::log($group, $subject, $bodyHtml, count($recipients), $sent, $failed, $adminId);

        return ['sent' => $sent, 'failed' => $failed, 'failures' => $failures];
    }

    public static function log(
        string $group,
        string $subject,
        string $body,
        int $recipientCount,
        int $sent,
        int $failed,
        int $adminId
    ): void {
        Database::getConnection()->prepare(
            'INSERT INTO email_logs
                (subject, body, recipient_group, recipient_count, sent_count, failed_count, sent_by_admin_id)
             VALUES (:subject, :body, :group, :recipient_count, :sent_count, :failed_count, :admin_id)'
        )->execute([
            'subject' => $subject,
            'body' => $body,
            'group' => $group,
            'recipient_count' => $recipientCount,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'admin_id' => $adminId,
        ]);
    }
}
