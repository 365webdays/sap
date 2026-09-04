<?php
/**
 * Missed adoration hour detection.
 *
 * A missed hour is a scheduled hour that has already passed with no check-in
 * inside it. Nothing is stored: the report is derived from schedules and
 * attendance on every request, so it stays correct when an adorer's schedule
 * changes or a late check-in is added. Only the admin's follow-up action is
 * persisted (missed_followups).
 *
 * All comparisons run in parish local time — see Attendance for why.
 */

class MissedAttendance
{
    /** Guard against an unbounded scan if a caller passes a huge range. */
    private const MAX_DAYS = 400;

    /**
     * Find missed hours between two dates, inclusive.
     *
     * Only hours that have fully elapsed count: an hour in progress, or later
     * today, has not been missed yet.
     *
     * @return list<array<string, mixed>>
     */
    public static function between(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        DateTimeImmutable $now,
        ?int $userId = null
    ): array {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);
        if ($to < $from) {
            return [];
        }

        $db = Database::getConnection();

        // Only active adorers are expected to attend; a deactivated account
        // should not accrue missed hours.
        $sql = 'SELECT s.id AS schedule_id, s.user_id, s.day_of_week, s.time_slot,
                       u.full_name, u.email
                FROM adoration_schedules s
                JOIN users u ON u.id = s.user_id
                WHERE u.is_active = 1';
        $params = [];
        if ($userId !== null) {
            $sql .= ' AND s.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $schedules = $stmt->fetchAll();

        if ($schedules === []) {
            return [];
        }

        // Pull attendance and follow-ups for the window once, then match in
        // PHP — one query each instead of one per scheduled date.
        $checkIns = self::checkInIndex($from, $to, $userId);
        $followUps = self::followUpIndex($from, $to, $userId);

        $missed = [];
        $dayCount = (int) $from->diff($to)->days;
        if ($dayCount > self::MAX_DAYS) {
            $dayCount = self::MAX_DAYS;
        }

        foreach ($schedules as $schedule) {
            $hour = (int) substr((string) $schedule['time_slot'], 0, 2);

            for ($offset = 0; $offset <= $dayCount; $offset++) {
                $date = $from->modify("+{$offset} days");

                if ($date->format('l') !== $schedule['day_of_week']) {
                    continue;
                }

                // The hour must have finished before it can be called missed.
                $hourEnd = $date->setTime($hour, 0)->modify('+1 hour');
                if ($hourEnd > $now) {
                    continue;
                }

                $key = $schedule['user_id'] . '|' . $date->format('Y-m-d') . '|' . $hour;
                if (isset($checkIns[$key])) {
                    continue;
                }

                $followKey = $schedule['user_id'] . '|' . $schedule['schedule_id'] . '|' . $date->format('Y-m-d');
                $follow = $followUps[$followKey] ?? null;

                $missed[] = [
                    'user_id' => (int) $schedule['user_id'],
                    'schedule_id' => (int) $schedule['schedule_id'],
                    'full_name' => $schedule['full_name'],
                    'email' => $schedule['email'],
                    'missed_date' => $date->format('Y-m-d'),
                    'date_label' => $date->format('D, M j, Y'),
                    'day_of_week' => $schedule['day_of_week'],
                    'time_slot' => $schedule['time_slot'],
                    'time_label' => Schedule::label((string) $schedule['time_slot']),
                    'followed_up' => $follow !== null,
                    'followed_up_at' => $follow['followed_up_at'] ?? null,
                    'follow_up_note' => $follow['note'] ?? null,
                ];
            }
        }

        // Most recent first, then alphabetically so the order is stable.
        usort($missed, function (array $a, array $b): int {
            return [$b['missed_date'], $a['full_name'], $a['time_slot']]
               <=> [$a['missed_date'], $b['full_name'], $b['time_slot']];
        });

        return $missed;
    }

    /**
     * Check-ins in the window, keyed "userId|Y-m-d|hour" for O(1) lookup.
     *
     * @return array<string, true>
     */
    private static function checkInIndex(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        ?int $userId
    ): array {
        $sql = 'SELECT user_id, DATE(check_in_at) AS d, HOUR(check_in_at) AS h
                FROM attendance_logs
                WHERE check_in_at >= :from AND check_in_at < :to';
        $params = [
            'from' => $from->format('Y-m-d 00:00:00'),
            'to' => $to->modify('+1 day')->format('Y-m-d 00:00:00'),
        ];
        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        $index = [];
        foreach ($stmt->fetchAll() as $row) {
            $index[$row['user_id'] . '|' . $row['d'] . '|' . (int) $row['h']] = true;
        }
        return $index;
    }

    /**
     * Recorded follow-ups in the window, keyed "userId|scheduleId|Y-m-d".
     *
     * @return array<string, array{followed_up_at:string, note:?string}>
     */
    private static function followUpIndex(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        ?int $userId
    ): array {
        $sql = 'SELECT user_id, schedule_id, missed_date, followed_up_at, note
                FROM missed_followups
                WHERE missed_date BETWEEN :from AND :to';
        $params = [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ];
        if ($userId !== null) {
            $sql .= ' AND user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        $index = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['user_id'] . '|' . $row['schedule_id'] . '|' . $row['missed_date'];
            $index[$key] = [
                'followed_up_at' => $row['followed_up_at'],
                'note' => $row['note'],
            ];
        }
        return $index;
    }

    /**
     * Record (or update) a follow-up for one missed hour.
     * Idempotent: marking the same record twice refreshes it rather than
     * failing on the unique key.
     */
    public static function markFollowedUp(
        int $userId,
        int $scheduleId,
        string $missedDate,
        ?int $adminId,
        ?string $note
    ): void {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO missed_followups
                (user_id, schedule_id, missed_date, followed_up_by_admin_id, note)
             VALUES (:user_id, :schedule_id, :missed_date, :admin_id, :note)
             ON DUPLICATE KEY UPDATE
                followed_up_at = CURRENT_TIMESTAMP,
                followed_up_by_admin_id = VALUES(followed_up_by_admin_id),
                note = VALUES(note)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'schedule_id' => $scheduleId,
            'missed_date' => $missedDate,
            'admin_id' => $adminId,
            'note' => $note,
        ]);
    }

    public static function clearFollowUp(int $userId, int $scheduleId, string $missedDate): void
    {
        $stmt = Database::getConnection()->prepare(
            'DELETE FROM missed_followups
             WHERE user_id = :user_id AND schedule_id = :schedule_id AND missed_date = :missed_date'
        );
        $stmt->execute([
            'user_id' => $userId,
            'schedule_id' => $scheduleId,
            'missed_date' => $missedDate,
        ]);
    }
}
