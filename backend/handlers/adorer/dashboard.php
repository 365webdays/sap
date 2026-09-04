<?php
/**
 * GET /api/adorer/dashboard
 *
 * Everything the adorer dashboard renders, in a single round trip: assigned
 * hours, last check-in, recent history, totals, and whether the check-in
 * button should currently be enabled.
 */

return function (): void {
    $user = Auth::require(Token::ROLE_ADORER);
    $userId = (int) $user['id'];

    $now = new DateTimeImmutable('now');
    $db = Database::getConnection();

    // An adorer may hold more than one hour, so return all of them.
    $scheduleStmt = $db->prepare(
        'SELECT id, day_of_week, time_slot
         FROM adoration_schedules
         WHERE user_id = :user_id
         ORDER BY FIELD(day_of_week, "Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"), time_slot'
    );
    $scheduleStmt->execute(['user_id' => $userId]);

    $schedules = [];
    foreach ($scheduleStmt->fetchAll() as $row) {
        $schedules[] = [
            'id' => (int) $row['id'],
            'day_of_week' => $row['day_of_week'],
            'time_slot' => $row['time_slot'],
            'label' => $row['day_of_week'] . ' at ' . Schedule::label($row['time_slot']),
        ];
    }

    $window = Attendance::windowMinutes();
    $recent = Attendance::recentCheckIn($userId, $now, $window);
    $currentHour = Attendance::findScheduleForMoment($userId, $now);
    $history = Attendance::history($userId, 10, 0);

    Response::success([
        'user' => [
            'id' => $userId,
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'mobile_number' => $user['mobile_number'],
            'role' => Token::ROLE_ADORER,
        ],
        'schedules' => $schedules,
        'last_check_in' => Attendance::last($userId),
        'recent_history' => $history['items'],
        'total_check_ins' => $history['total'],
        'summary' => Attendance::summary($userId, $now),
        'check_in' => [
            // False while inside the duplicate window, so the UI can disable
            // the button and explain why instead of failing on submit.
            'can_check_in' => $recent === null,
            'window_minutes' => $window,
            'last_within_window' => $recent,
            'within_scheduled_hour' => $currentHour !== null,
            'current_scheduled_hour' => $currentHour === null ? null : [
                'day_of_week' => $currentHour['day_of_week'],
                'time_slot' => $currentHour['time_slot'],
                'label' => $currentHour['day_of_week'] . ' at ' . Schedule::label($currentHour['time_slot']),
            ],
        ],
        'server_time' => $now->format('c'),
    ]);
};
