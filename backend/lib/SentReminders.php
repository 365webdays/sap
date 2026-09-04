<?php
/**
 * Dedup log for cron-sent reminder emails.
 *
 * The cron scripts run on a schedule (hourly or daily) and may fire multiple
 * times within the same reminder window. This table records what was already
 * sent so a second invocation skips it rather than spamming the adorer.
 *
 * For 'hour' reminders, the unique key is (user, 'hour', scheduled_date,
 * time_slot) — one reminder per scheduled hour per day.
 *
 * For 'missed' notifications, the key is (user, 'missed', missed_date,
 * '00:00:00') — one notification per user per day, regardless of how many
 * hours they missed.
 */
class SentReminders
{
    public const TYPE_HOUR = 'hour';
    public const TYPE_MISSED = 'missed';

    /** Sentinel time_slot for reminders that aren't tied to a specific slot. */
    public const ANY_SLOT = '00:00:00';

    /**
     * Has a reminder of this type already been sent for the given reference?
     */
    public static function alreadySent(
        int $userId,
        string $type,
        string $referenceDate,
        string $timeSlot = self::ANY_SLOT
    ): bool {
        $stmt = Database::getConnection()->prepare(
            'SELECT 1 FROM sent_reminders
             WHERE user_id = :user_id
               AND reminder_type = :type
               AND reference_date = :date
               AND time_slot = :slot
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'date' => $referenceDate,
            'slot' => $timeSlot,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Record a sent reminder. INSERT IGNORE so a race between two cron
     * invocations doesn't raise a duplicate-key error — the first one wins.
     */
    public static function log(
        int $userId,
        string $type,
        string $referenceDate,
        string $timeSlot = self::ANY_SLOT
    ): void {
        Database::getConnection()->prepare(
            'INSERT IGNORE INTO sent_reminders
                (user_id, reminder_type, reference_date, time_slot)
             VALUES (:user_id, :type, :date, :slot)'
        )->execute([
            'user_id' => $userId,
            'type' => $type,
            'date' => $referenceDate,
            'slot' => $timeSlot,
        ]);
    }

    /**
     * Delete records older than the given number of days.
     *
     * Called by each cron run so the table stays bounded over time. The dedup
     * window is at most one day, so anything older is pure history and safe to
     * remove.
     */
    public static function prune(int $days): void
    {
        Database::getConnection()->prepare(
            'DELETE FROM sent_reminders WHERE sent_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
        )->execute(['days' => $days]);
    }
}
