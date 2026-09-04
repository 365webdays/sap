<?php
/**
 * POST /api/adorer/checkin
 *
 * Records a check-in for the signed-in adorer. Accepts an optional
 * { "method": "manual" | "qr" } body; anything else defaults to manual so the
 * plain dashboard button needs no payload at all.
 */

return function (): void {
    $user = Auth::require(Token::ROLE_ADORER);
    $userId = (int) $user['id'];

    // The body is optional here, so parse leniently instead of using
    // Validator::fromJsonBody() (which 400s on a missing body).
    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    $method = is_array($body) ? ($body['method'] ?? Attendance::METHOD_MANUAL) : Attendance::METHOD_MANUAL;

    if (!is_string($method) || !Attendance::isValidMethod($method)) {
        Response::error('Check-in method must be either "manual" or "qr"', 422);
    }

    $now = new DateTimeImmutable('now');
    $window = Attendance::windowMinutes();

    // Refuse a second check-in inside the window, but report the existing one
    // so the client can show "you already checked in at ..." rather than a
    // bare error.
    $existing = Attendance::recentCheckIn($userId, $now, $window);
    if ($existing !== null) {
        $ts = strtotime($existing['check_in_at']);

        http_response_code(409);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'You have already checked in within the last '
                . $window . ' minutes (at '
                . ($ts === false ? $existing['check_in_at'] : date('g:i A', $ts)) . ').',
            'data' => [
                'already_checked_in' => true,
                'check_in_at' => $existing['check_in_at'],
                'time_label' => $ts === false ? '' : date('g:i A', $ts),
                'window_minutes' => $window,
            ],
        ]);
        exit;
    }

    // Only linked when the check-in genuinely falls inside the adorer's
    // scheduled hour; otherwise it is recorded as an off-schedule visit.
    $schedule = Attendance::findScheduleForMoment($userId, $now);

    try {
        $entry = Attendance::record($userId, $method, $now, $schedule);
    } catch (Throwable $e) {
        error_log('checkin: ' . $e->getMessage());
        Response::error('Could not record your check-in. Please try again.', 500);
    }

    Response::success([
        'entry' => $entry,
        'within_scheduled_hour' => $schedule !== null,
        'message' => $schedule !== null
            ? 'Checked in for your ' . $schedule['day_of_week'] . ' '
                . Schedule::label($schedule['time_slot']) . ' hour. Thank you.'
            : 'Check-in recorded. Thank you for visiting the chapel.',
    ], 201);
};
