<?php
/**
 * Check-in recording and attendance history.
 *
 * All timestamps are written from PHP using the parish timezone rather than
 * MySQL's NOW(), because the database server runs in UTC and the day/hour
 * matching below is only meaningful in local time.
 */

class Attendance
{
    public const METHOD_MANUAL = 'manual';
    public const METHOD_QR = 'qr';

    /**
     * How long after a check-in a second one is treated as a duplicate.
     * Overridable with CHECKIN_WINDOW_MINUTES so the parish can loosen or
     * tighten it without a code change.
     */
    private const DEFAULT_WINDOW_MINUTES = 60;

    public static function windowMinutes(): int
    {
        $configured = (int) (env('CHECKIN_WINDOW_MINUTES', '') ?: 0);
        return $configured > 0 ? $configured : self::DEFAULT_WINDOW_MINUTES;
    }

    public static function isValidMethod(string $method): bool
    {
        return in_array($method, [self::METHOD_MANUAL, self::METHOD_QR], true);
    }

    /**
     * The adorer's schedule for the hour containing $now, if any.
     *
     * A check-in is only linked to a schedule when it actually falls inside
     * that scheduled hour; visits outside it are recorded with a null
     * schedule_id so missed-hour reporting stays accurate.
     *
     * @return array{id:int, day_of_week:string, time_slot:string}|null
     */
    public static function findScheduleForMoment(int $userId, DateTimeImmutable $now): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, day_of_week, time_slot
             FROM adoration_schedules
             WHERE user_id = :user_id
               AND day_of_week = :day
               AND HOUR(time_slot) = :hour
             ORDER BY id
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'day' => $now->format('l'),
            'hour' => (int) $now->format('G'),
        ]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'day_of_week' => $row['day_of_week'],
            'time_slot' => $row['time_slot'],
        ];
    }

    /**
     * The most recent check-in inside the duplicate window, if one exists.
     *
     * @return array{id:int, check_in_at:string, method:string}|null
     */
    public static function recentCheckIn(int $userId, DateTimeImmutable $now, int $windowMinutes): ?array
    {
        $cutoff = $now->modify("-{$windowMinutes} minutes");

        $stmt = Database::getConnection()->prepare(
            'SELECT id, check_in_at, method
             FROM attendance_logs
             WHERE user_id = :user_id
               AND check_in_at >= :cutoff
             ORDER BY check_in_at DESC
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'cutoff' => $cutoff->format('Y-m-d H:i:s'),
        ]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'check_in_at' => $row['check_in_at'],
            'method' => $row['method'],
        ];
    }

    /**
     * Record a check-in and return the created row, shaped for the API.
     *
     * @return array<string, mixed>
     */
    public static function record(
        int $userId,
        string $method,
        DateTimeImmutable $now,
        ?array $schedule
    ): array {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'INSERT INTO attendance_logs (user_id, schedule_id, check_in_at, method)
             VALUES (:user_id, :schedule_id, :check_in_at, :method)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'schedule_id' => $schedule['id'] ?? null,
            'check_in_at' => $now->format('Y-m-d H:i:s'),
            'method' => $method,
        ]);

        return self::present([
            'id' => (int) $db->lastInsertId(),
            'check_in_at' => $now->format('Y-m-d H:i:s'),
            'method' => $method,
            'day_of_week' => $schedule['day_of_week'] ?? null,
            'time_slot' => $schedule['time_slot'] ?? null,
        ]);
    }

    /**
     * Most recent check-in of any age, or null if the adorer has never
     * checked in.
     *
     * @return array<string, mixed>|null
     */
    public static function last(int $userId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT a.id, a.check_in_at, a.method, s.day_of_week, s.time_slot
             FROM attendance_logs a
             LEFT JOIN adoration_schedules s ON s.id = a.schedule_id
             WHERE a.user_id = :user_id
             ORDER BY a.check_in_at DESC, a.id DESC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);

        $row = $stmt->fetch();
        return $row === false ? null : self::present($row);
    }

    /**
     * Paginated check-in history, newest first.
     *
     * @return array{items:list<array<string,mixed>>, total:int}
     */
    public static function history(int $userId, int $limit, int $offset): array
    {
        $db = Database::getConnection();

        $countStmt = $db->prepare('SELECT COUNT(*) FROM attendance_logs WHERE user_id = :user_id');
        $countStmt->execute(['user_id' => $userId]);
        $total = (int) $countStmt->fetchColumn();

        // LIMIT/OFFSET cannot be bound as strings with emulation disabled, so
        // they are cast to int and interpolated. Both are validated upstream.
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $stmt = $db->prepare(
            "SELECT a.id, a.check_in_at, a.method, s.day_of_week, s.time_slot
             FROM attendance_logs a
             LEFT JOIN adoration_schedules s ON s.id = a.schedule_id
             WHERE a.user_id = :user_id
             ORDER BY a.check_in_at DESC, a.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute(['user_id' => $userId]);

        return [
            'items' => array_map([self::class, 'present'], $stmt->fetchAll()),
            'total' => $total,
        ];
    }

    /**
     * Per-adorer counts used by the dashboard.
     *
     * @return array{total:int, this_month:int, this_year:int}
     */
    public static function summary(int $userId, DateTimeImmutable $now): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(check_in_at >= :month_start) AS this_month,
                SUM(check_in_at >= :year_start)  AS this_year
             FROM attendance_logs
             WHERE user_id = :user_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'month_start' => $now->format('Y-m-01 00:00:00'),
            'year_start' => $now->format('Y-01-01 00:00:00'),
        ]);

        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'this_month' => (int) ($row['this_month'] ?? 0),
            'this_year' => (int) ($row['this_year'] ?? 0),
        ];
    }

    /**
     * Shape a joined attendance row for JSON output, adding display strings so
     * the client never has to parse or reformat timestamps itself.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function present(array $row): array
    {
        $checkInAt = (string) $row['check_in_at'];
        $ts = strtotime($checkInAt);

        return [
            'id' => (int) $row['id'],
            'check_in_at' => $checkInAt,
            'check_in_at_iso' => $ts === false ? $checkInAt : date('c', $ts),
            'date_label' => $ts === false ? $checkInAt : date('D, M j, Y', $ts),
            'time_label' => $ts === false ? '' : date('g:i A', $ts),
            'method' => (string) $row['method'],
            'scheduled_hour' => empty($row['day_of_week'])
                ? null
                : $row['day_of_week'] . ' at ' . Schedule::label((string) $row['time_slot']),
        ];
    }
}
