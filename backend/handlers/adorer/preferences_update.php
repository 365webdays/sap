<?php
/**
 * PUT /api/adorer/preferences
 *
 * Replaces all three notification toggles. Every key is required so a partial
 * payload can never silently leave a toggle at a stale value.
 */

return function (): void {
    $user = Auth::require(Token::ROLE_ADORER);

    $v = Validator::fromJsonBody();

    $labels = [
        'hour_reminders' => 'Hour reminders',
        'announcements' => 'Chapel announcements',
        'attendance_notifications' => 'Attendance notifications',
    ];

    $prefs = [];
    foreach (Preferences::KEYS as $key) {
        $prefs[$key] = $v->boolean($key, $labels[$key]);
    }

    $v->stopIfInvalid();

    try {
        $saved = Preferences::save((int) $user['id'], $prefs);
    } catch (Throwable $e) {
        error_log('preferences: ' . $e->getMessage());
        Response::error('Could not save your preferences. Please try again.', 500);
    }

    Response::success([
        'preferences' => $saved,
        'message' => 'Your notification preferences have been saved.',
    ]);
};
