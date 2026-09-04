<?php
/**
 * Filtered, paginated queries behind the admin roster and attendance views.
 *
 * Filters are assembled from a whitelist and always bound as parameters; the
 * only values interpolated into SQL are integers this class computes itself
 * (LIMIT/OFFSET, which cannot be bound with emulated prepares disabled).
 */

class AdminQuery
{
    public const MAX_PER_PAGE = 100;

    public static function clampPerPage(int $perPage, int $default = 20): int
    {
        if ($perPage <= 0) {
            $perPage = $default;
        }
        return min(self::MAX_PER_PAGE, $perPage);
    }

    /**
     * Parse a strict YYYY-MM-DD date, or null if absent/malformed.
     * Rejects loose input like "next tuesday" that DateTime would accept.
     */
    public static function asDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false) {
            return null;
        }

        // createFromFormat is lenient about overflow (2026-02-31 becomes March);
        // round-tripping catches that.
        return $date->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * Adorer roster with search and filters.
     *
     * @param array{search?:string, status?:string, day?:string, slot?:string} $filters
     * @return array{items:list<array<string,mixed>>, total:int}
     */
    public static function adorers(array $filters, int $limit, int $offset): array
    {
        $where = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(u.full_name LIKE :search OR u.email LIKE :search OR u.mobile_number LIKE :search)';
            // Escape LIKE wildcards so a literal % typed in the box does not
            // silently match everything.
            $params['search'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status === 'active') {
            $where[] = 'u.is_active = 1';
        } elseif ($status === 'inactive') {
            $where[] = 'u.is_active = 0';
        }

        // Day/slot filters are existence checks so an adorer with several
        // hours is returned once, not once per matching hour.
        $day = (string) ($filters['day'] ?? '');
        if ($day !== '' && in_array($day, Schedule::DAYS, true)) {
            $where[] = 'EXISTS (SELECT 1 FROM adoration_schedules sd
                                WHERE sd.user_id = u.id AND sd.day_of_week = :day)';
            $params['day'] = $day;
        }

        $slot = (string) ($filters['slot'] ?? '');
        if ($slot !== '' && in_array($slot, Schedule::timeSlots(), true)) {
            $where[] = 'EXISTS (SELECT 1 FROM adoration_schedules ss
                                WHERE ss.user_id = u.id AND ss.time_slot = :slot)';
            $params['slot'] = $slot;
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $db = Database::getConnection();

        $countStmt = $db->prepare("SELECT COUNT(*) FROM users u{$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $limit = self::clampPerPage($limit);
        $offset = max(0, $offset);

        $stmt = $db->prepare(
            "SELECT u.id, u.full_name, u.email, u.mobile_number, u.is_active, u.created_at,
                    (SELECT COUNT(*) FROM attendance_logs a WHERE a.user_id = u.id) AS check_in_count,
                    (SELECT MAX(a2.check_in_at) FROM attendance_logs a2 WHERE a2.user_id = u.id) AS last_check_in
             FROM users u{$whereSql}
             ORDER BY u.full_name
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // One query for every listed adorer's hours, rather than N queries.
        $schedules = self::schedulesFor(array_map(fn($r) => (int) $r['id'], $rows));

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $items[] = [
                'id' => $id,
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'mobile_number' => $row['mobile_number'],
                'is_active' => (bool) (int) $row['is_active'],
                'created_at' => $row['created_at'],
                'check_in_count' => (int) $row['check_in_count'],
                'last_check_in' => $row['last_check_in'],
                'schedules' => $schedules[$id] ?? [],
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Assigned hours grouped by user id.
     *
     * @param list<int> $userIds
     * @return array<int, list<array<string, mixed>>>
     */
    public static function schedulesFor(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        // Values are cast to int above, so this interpolation is safe and lets
        // us use a single IN (...) rather than a variable-length bind list.
        $ids = implode(',', array_map('intval', $userIds));

        $stmt = Database::getConnection()->query(
            "SELECT id, user_id, day_of_week, time_slot
             FROM adoration_schedules
             WHERE user_id IN ({$ids})
             ORDER BY FIELD(day_of_week, 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), time_slot"
        );

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['user_id']][] = [
                'id' => (int) $row['id'],
                'day_of_week' => $row['day_of_week'],
                'time_slot' => $row['time_slot'],
                'label' => $row['day_of_week'] . ' at ' . Schedule::label((string) $row['time_slot']),
            ];
        }
        return $grouped;
    }

    /**
     * Attendance records across all adorers, filtered.
     *
     * @param array{from?:string, to?:string, search?:string, method?:string, day?:string, slot?:string} $filters
     * @return array{items:list<array<string,mixed>>, total:int}
     */
    public static function attendance(array $filters, int $limit, int $offset): array
    {
        $where = [];
        $params = [];

        // Malformed dates are ignored rather than fatal: a bad query string
        // should narrow nothing, not 500.
        $from = self::asDate((string) ($filters['from'] ?? ''));
        if ($from !== null) {
            $where[] = 'a.check_in_at >= :from';
            $params['from'] = $from->format('Y-m-d 00:00:00');
        }

        $to = self::asDate((string) ($filters['to'] ?? ''));
        if ($to !== null) {
            // Exclusive upper bound on the next day so the whole end date is
            // included regardless of time.
            $where[] = 'a.check_in_at < :to';
            $params['to'] = $to->modify('+1 day')->format('Y-m-d 00:00:00');
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(u.full_name LIKE :search OR u.email LIKE :search)';
            $params['search'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        }

        $method = (string) ($filters['method'] ?? '');
        if ($method !== '' && Attendance::isValidMethod($method)) {
            $where[] = 'a.method = :method';
            $params['method'] = $method;
        }

        $day = (string) ($filters['day'] ?? '');
        if ($day !== '' && in_array($day, Schedule::DAYS, true)) {
            $where[] = 'DAYNAME(a.check_in_at) = :day';
            $params['day'] = $day;
        }

        $slot = (string) ($filters['slot'] ?? '');
        if ($slot !== '' && in_array($slot, Schedule::timeSlots(), true)) {
            $where[] = 'HOUR(a.check_in_at) = :slot_hour';
            $params['slot_hour'] = (int) substr($slot, 0, 2);
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $db = Database::getConnection();

        $countStmt = $db->prepare(
            "SELECT COUNT(*) FROM attendance_logs a JOIN users u ON u.id = a.user_id{$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $limit = self::clampPerPage($limit);
        $offset = max(0, $offset);

        $stmt = $db->prepare(
            "SELECT a.id, a.check_in_at, a.method, a.user_id,
                    u.full_name, u.email,
                    s.day_of_week, s.time_slot
             FROM attendance_logs a
             JOIN users u ON u.id = a.user_id
             LEFT JOIN adoration_schedules s ON s.id = a.schedule_id
             {$whereSql}
             ORDER BY a.check_in_at DESC, a.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $ts = strtotime((string) $row['check_in_at']);
            $items[] = [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'check_in_at' => $row['check_in_at'],
                'date_label' => $ts === false ? $row['check_in_at'] : date('D, M j, Y', $ts),
                'time_label' => $ts === false ? '' : date('g:i A', $ts),
                'method' => $row['method'],
                'scheduled_hour' => empty($row['day_of_week'])
                    ? null
                    : $row['day_of_week'] . ' at ' . Schedule::label((string) $row['time_slot']),
            ];
        }

        return ['items' => $items, 'total' => $total];
    }
}
