<?php
/**
 * POST /api/auth/login
 *
 * Verifies adorer credentials and returns a signed token.
 */

return function (): void {
    $v = Validator::fromJsonBody();

    $email = $v->email('email', 'Email address');
    $password = $v->password('password', 'Password', 1);

    $v->stopIfInvalid();

    // Throttle brute-force attempts before doing any DB work.
    $remaining = RateLimiter::remainingAttempts(RateLimiter::ENDPOINT_AUTH_LOGIN);
    if ($remaining === 0) {
        Response::error('Too many login attempts. Please try again later.', 429);
    }

    $stmt = Database::getConnection()->prepare(
        'SELECT id, full_name, email, mobile_number, password_hash, is_active
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    // Always run a hash comparison, even when the account does not exist, so
    // response timing does not reveal which emails are registered.
    $hash = $user['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
    $passwordOk = password_verify($password, $hash);

    if ($user === false || !$passwordOk) {
        RateLimiter::recordFailure(RateLimiter::ENDPOINT_AUTH_LOGIN);
        $left = RateLimiter::remainingAttempts(RateLimiter::ENDPOINT_AUTH_LOGIN);
        if ($left > 0) {
            Response::error("Incorrect email or password. {$left} attempt" . ($left === 1 ? '' : 's') . ' remaining.', 401);
        }
        Response::error('Too many login attempts. Please try again later.', 429);
    }

    // Deliberately distinct from bad credentials: the password was right, but
    // an admin has deactivated the account.
    if ((int) $user['is_active'] !== 1) {
        Response::error('This account has been deactivated. Please contact the parish office.', 403);
    }

    RateLimiter::clear(RateLimiter::ENDPOINT_AUTH_LOGIN);

    $userId = (int) $user['id'];

    $scheduleStmt = Database::getConnection()->prepare(
        'SELECT day_of_week, time_slot
         FROM adoration_schedules
         WHERE user_id = :user_id
         ORDER BY id
         LIMIT 1'
    );
    $scheduleStmt->execute(['user_id' => $userId]);
    $schedule = $scheduleStmt->fetch() ?: null;

    Response::success([
        'token' => Token::issue($userId, Token::ROLE_ADORER),
        'user' => [
            'id' => $userId,
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
