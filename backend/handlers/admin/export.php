<?php
/**
 * GET /api/admin/export?type=attendance|adorers|missed
 *
 * CSV downloads. Each type honours the same filters as its on-screen report,
 * so what the admin sees is what they get in the file.
 *
 * Exports are capped: these are parish-sized reports, and an unbounded export
 * would be an easy way to exhaust the shared host.
 */

const EXPORT_LIMIT = 10000;

return function (): void {
    Auth::require(Token::ROLE_ADMIN);

    $type = (string) ($_GET['type'] ?? 'attendance');
    $now = new DateTimeImmutable('now');
    $stamp = $now->format('Y-m-d');

    if ($type === 'adorers') {
        $result = AdminQuery::adorers([
            'search' => (string) ($_GET['search'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'day' => (string) ($_GET['day'] ?? ''),
            'slot' => (string) ($_GET['slot'] ?? ''),
        ], EXPORT_LIMIT, 0);

        $rows = [];
        foreach ($result['items'] as $a) {
            $rows[] = [
                $a['full_name'],
                $a['email'],
                $a['mobile_number'] ?? '',
                $a['is_active'] ? 'Active' : 'Inactive',
                implode('; ', array_map(fn($s) => $s['label'], $a['schedules'])),
                $a['check_in_count'],
                $a['last_check_in'] ?? 'Never',
                $a['created_at'],
            ];
        }

        Csv::download(
            "adorers-{$stamp}.csv",
            ['Name', 'Email', 'Mobile', 'Status', 'Assigned Hours', 'Check-Ins', 'Last Check-In', 'Registered'],
            $rows
        );
    }

    if ($type === 'missed') {
        $to = AdminQuery::asDate((string) ($_GET['to'] ?? '')) ?? $now;
        $from = AdminQuery::asDate((string) ($_GET['from'] ?? '')) ?? $to->modify('-29 days');
        $userId = (int) ($_GET['user_id'] ?? 0);

        $records = MissedAttendance::between($from, $to, $now, $userId > 0 ? $userId : null);

        $followedUp = (string) ($_GET['followed_up'] ?? '');
        if ($followedUp === 'yes' || $followedUp === 'no') {
            $want = $followedUp === 'yes';
            $records = array_filter($records, fn(array $r) => $r['followed_up'] === $want);
        }

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                $r['missed_date'],
                $r['full_name'],
                $r['email'],
                $r['day_of_week'],
                $r['time_label'],
                $r['followed_up'] ? 'Yes' : 'No',
                $r['followed_up_at'] ?? '',
                $r['follow_up_note'] ?? '',
            ];
        }

        Csv::download(
            "missed-attendance-{$stamp}.csv",
            ['Missed Date', 'Name', 'Email', 'Day', 'Hour', 'Followed Up', 'Followed Up At', 'Note'],
            $rows
        );
    }

    // Default: attendance records.
    $result = AdminQuery::attendance([
        'from' => (string) ($_GET['from'] ?? ''),
        'to' => (string) ($_GET['to'] ?? ''),
        'search' => (string) ($_GET['search'] ?? ''),
        'method' => (string) ($_GET['method'] ?? ''),
        'day' => (string) ($_GET['day'] ?? ''),
        'slot' => (string) ($_GET['slot'] ?? ''),
    ], EXPORT_LIMIT, 0);

    $rows = [];
    foreach ($result['items'] as $a) {
        $rows[] = [
            $a['check_in_at'],
            $a['date_label'],
            $a['time_label'],
            $a['full_name'],
            $a['email'],
            $a['method'] === 'qr' ? 'QR' : 'Manual',
            $a['scheduled_hour'] ?? 'Outside assigned hour',
        ];
    }

    Csv::download(
        "attendance-{$stamp}.csv",
        ['Timestamp', 'Date', 'Time', 'Name', 'Email', 'Method', 'Scheduled Hour'],
        $rows
    );
};
