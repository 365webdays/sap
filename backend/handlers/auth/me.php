<?php
/**
 * GET /api/auth/me
 *
 * Returns the signed-in adorer. The frontend calls this on boot to confirm a
 * stored token is still valid before trusting it.
 */

return function (): void {
    $user = Auth::require(Token::ROLE_ADORER);

    $scheduleStmt = Database::getConnection()->prepare(
        'SELECT day_of_week, time_slot
         FROM adoration_schedules
         WHERE user_id = :user_id
         ORDER BY id
         LIMIT 1'
    );
    $scheduleStmt->execute(['user_id' => $user['id']]);
    $schedule = $scheduleStmt->fetch() ?: null;

    Response::success([
        'user' => [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'mobile_number' => $user['mobile_number'],
            'role' => Token::ROLE_ADORER,
        ],
        'schedule' => $schedule === null ? null : [
            'day_of_week' => $schedule['day_of_week'],
            'time_slot' => $schedule['time_slot'],
            'label' => $schedule['day_of_week'] . ' at ' . Schedule::label($schedule['time_slot']),
        ],
    ]);
};
