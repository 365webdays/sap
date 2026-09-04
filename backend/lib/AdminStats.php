<?php
/**
 * Aggregate figures for the admin dashboard and analytics views.
 *
 * Date boundaries are computed in PHP (parish local time) and passed as
 * explicit range bounds so the grouping does not depend on the database
 * server's timezone.
 */

class AdminStats
{
    /**
     * Headline counts for the summary cards.
     *
     * @return array<string, int>
     */
    public static function overview(DateTimeImmutable $now): array
    {
        $db = Database::getConnection();

        $users = $db->query(
            'SELECT
                COUNT(*) AS total,
                SUM(is_active = 1) AS active,
                SUM(is_active = 0) AS inactive
             FROM users'
        )->fetch() ?: [];

        $todayStart = $now->setTime(0, 0);
        // Weeks start Sunday to match how the schedule is expressed.
        $weekStart = $todayStart->modify('-' . (int) $todayStart->format('w') . ' days');

        $ranges = [
            'today' => [$todayStart, $todayStart->modify('+1 day')],
            'this_week' => [$weekStart, $weekStart->modify('+7 days')],
            'this_month' => [$now->modify('first day of this month')->setTime(0, 0),
                             $now->modify('first day of next month')->setTime(0, 0)],
        ];

        $counts = [];
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM attendance_logs WHERE check_in_at >= :from AND check_in_at < :to'
        );
        foreach ($ranges as $key => [$start, $end]) {
            $stmt->execute([
                'from' => $start->format('Y-m-d H:i:s'),
                'to' => $end->format('Y-m-d H:i:s'),
            ]);
            $counts[$key] = (int) $stmt->fetchColumn();
        }

        $scheduled = (int) $db->query(
            'SELECT COUNT(*) FROM adoration_schedules s
             JOIN users u ON u.id = s.user_id AND u.is_active = 1'
        )->fetchColumn();

        return [
            'total_adorers' => (int) ($users['total'] ?? 0),
            'active_adorers' => (int) ($users['active'] ?? 0),
            'inactive_adorers' => (int) ($users['inactive'] ?? 0),
            'assigned_hours' => $scheduled,
            'attendance_today' => $counts['today'],
            'attendance_this_week' => $counts['this_week'],
            'attendance_this_month' => $counts['this_month'],
            'attendance_total' => (int) $db->query('SELECT COUNT(*) FROM attendance_logs')->fetchColumn(),
        ];
    }

    /**
     * Attendance counts bucketed for the trend chart.
     *
     * Buckets with no check-ins are filled with zero so the chart shows a
     * continuous line instead of skipping quiet days.
     *
     * @param string $granularity day|week|month
     * @return list<array{bucket:string, label:string, count:int}>
     */
    public static function trend(DateTimeImmutable $now, string $granularity, int $periods): array
    {
        [$sqlFormat, $phpFormat, $unit, $labelFormat] = match ($granularity) {
            'month' => ['%Y-%m', 'Y-m', 'month', 'M Y'],
            'week' => ['%x-W%v', 'o-\WW', 'week', "\\W\\e\\e\\k W, Y"],
            default => ['%Y-%m-%d', 'Y-m-d', 'day', 'M j'],
        };

        $periods = max(1, min(365, $periods));

        // Snap to the start of the current bucket, walk back to the first one,
        // then forward again so every bucket in between is represented.
        $cursor = match ($granularity) {
            'month' => $now->modify('first day of this month')->setTime(0, 0),
            'week' => $now->setTime(0, 0)->modify('-' . (int) $now->format('w') . ' days'),
            default => $now->setTime(0, 0),
        };
        $start = $cursor->modify('-' . ($periods - 1) . ' ' . $unit);

        $stmt = Database::getConnection()->prepare(
            "SELECT DATE_FORMAT(check_in_at, '{$sqlFormat}') AS bucket, COUNT(*) AS c
             FROM attendance_logs
             WHERE check_in_at >= :from
             GROUP BY bucket"
        );
        $stmt->execute(['from' => $start->format('Y-m-d H:i:s')]);

        $found = [];
        foreach ($stmt->fetchAll() as $row) {
            $found[$row['bucket']] = (int) $row['c'];
        }

        $series = [];
        for ($i = 0; $i < $periods; $i++) {
            $point = $start->modify('+' . $i . ' ' . $unit);
            $bucket = $point->format($phpFormat);
            $series[] = [
                'bucket' => $bucket,
                'label' => $point->format($labelFormat),
                'count' => $found[$bucket] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * Check-in counts per weekday/hour cell, for the peak-periods heatmap.
     *
     * @return array{cells:list<array{day:string, hour:int, count:int}>, max:int}
     */
    public static function peakPeriods(DateTimeImmutable $now, int $daysBack = 90): array
    {
        $from = $now->setTime(0, 0)->modify('-' . max(1, $daysBack) . ' days');

        $stmt = Database::getConnection()->prepare(
            "SELECT DAYNAME(check_in_at) AS day, HOUR(check_in_at) AS hour, COUNT(*) AS c
             FROM attendance_logs
             WHERE check_in_at >= :from
             GROUP BY day, hour"
        );
        $stmt->execute(['from' => $from->format('Y-m-d H:i:s')]);

        $cells = [];
        $max = 0;
        foreach ($stmt->fetchAll() as $row) {
            $count = (int) $row['c'];
            $max = max($max, $count);
            $cells[] = [
                'day' => (string) $row['day'],
                'hour' => (int) $row['hour'],
                'count' => $count,
            ];
        }

        return ['cells' => $cells, 'max' => $max, 'days_back' => $daysBack];
    }

    /**
     * Every day/hour slot with who is assigned to it, including empty slots so
     * gaps in chapel coverage are visible.
     *
     * @return list<array<string, mixed>>
     */
    public static function coverage(): array
    {
        $stmt = Database::getConnection()->query(
            'SELECT s.day_of_week, s.time_slot, s.id AS schedule_id,
                    u.id AS user_id, u.full_name, u.email, u.is_active
             FROM adoration_schedules s
             JOIN users u ON u.id = s.user_id
             ORDER BY s.time_slot'
        );

        $assigned = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['day_of_week'] . '|' . $row['time_slot'];
            $assigned[$key][] = [
                'schedule_id' => (int) $row['schedule_id'],
                'user_id' => (int) $row['user_id'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'is_active' => (bool) (int) $row['is_active'],
            ];
        }

        $slots = [];
        foreach (Schedule::DAYS as $day) {
            foreach (Schedule::timeSlots() as $slot) {
                $key = $day . '|' . $slot;
                $adorers = $assigned[$key] ?? [];
                $slots[] = [
                    'day_of_week' => $day,
                    'time_slot' => $slot,
                    'time_label' => Schedule::label($slot),
                    'adorers' => $adorers,
                    'count' => count($adorers),
                    'active_count' => count(array_filter($adorers, fn($a) => $a['is_active'])),
                ];
            }
        }

        return $slots;
    }
}
