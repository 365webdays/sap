<?php
/**
 * POST /api/admin/login
 *
 * Verifies credentials against the admins table and returns an admin-scoped
 * token. Kept entirely separate from adorer login — the two account types live
 * in different tables and their tokens are not interchangeable.
 */

return function (): void {
    $v = Validator::fromJsonBody();

    $email = $v->email('email', 'Email address');
    $password = $v->password('password', 'Password', 1);

    $v->stopIfInvalid();

    // Throttle brute-force attempts before doing any DB work.
    $remaining = RateLimiter::remainingAttempts(RateLimiter::ENDPOINT_ADMIN_LOGIN);
    if ($remaining === 0) {
        Response::error('Too many login attempts. Please try again later.', 429);
    }

    $stmt = Database::getConnection()->prepare(
        'SELECT id, name, email, password_hash FROM admins WHERE email = :email LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $admin = $stmt->fetch();

    // Constant-ish work regardless of whether the account exists.
    $hash = $admin['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
    $passwordOk = password_verify($password, $hash);

    if ($admin === false || !$passwordOk) {
        RateLimiter::recordFailure(RateLimiter::ENDPOINT_ADMIN_LOGIN);
        $left = RateLimiter::remainingAttempts(RateLimiter::ENDPOINT_ADMIN_LOGIN);
        if ($left > 0) {
            Response::error("Incorrect email or password. {$left} attempt" . ($left === 1 ? '' : 's') . ' remaining.', 401);
        }
        Response::error('Too many login attempts. Please try again later.', 429);
    }

    RateLimiter::clear(RateLimiter::ENDPOINT_ADMIN_LOGIN);

    $adminId = (int) $admin['id'];

    Response::success([
        'token' => Token::issue($adminId, Token::ROLE_ADMIN),
        'admin' => [
            'id' => $adminId,
            'name' => $admin['name'],
            'email' => $admin['email'],
            'role' => Token::ROLE_ADMIN,
        ],
    ]);
};
