<?php
/**
 * GET /api/admin/adorer?id=5
 *
 * One adorer's full profile: details, assigned hours, preferences, attendance
 * totals, and recent check-ins.
 */

return function (): void {
    Auth::require(Token::ROLE_ADMIN);

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        Response::error('An adorer id is required', 422);
    }

    $stmt = Database::getConnection()->prepare(
        'SELECT id, full_name, email, mobile_number, privacy_consent,
                email_verified_at, is_active, created_at
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    if ($user === false) {
        Response::error('Adorer not found', 404);
    }

    $now = new DateTimeImmutable('now');
    $history = Attendance::history($id, 20, 0);

    Response::success([
        'adorer' => [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'mobile_number' => $user['mobile_number'],
            'privacy_consent' => (bool) (int) $user['privacy_consent'],
            'email_verified_at' => $user['email_verified_at'],
            'is_active' => (bool) (int) $user['is_active'],
            'created_at' => $user['created_at'],
        ],
        'schedules' => AdminQuery::schedulesFor([$id])[$id] ?? [],
        'preferences' => Preferences::forUser($id),
        'summary' => Attendance::summary($id, $now),
        'last_check_in' => Attendance::last($id),
        'recent_history' => $history['items'],
        'total_check_ins' => $history['total'],
    ]);
};
