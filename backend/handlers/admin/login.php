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

    $stmt = Database::getConnection()->prepare(
        'SELECT id, name, email, password_hash FROM admins WHERE email = :email LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $admin = $stmt->fetch();

    // Constant-ish work regardless of whether the account exists.
    $hash = $admin['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
    $passwordOk = password_verify($password, $hash);

    if ($admin === false || !$passwordOk) {
        Response::error('Incorrect email or password', 401);
    }

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
