<?php
/**
 * GET /api/admin/me
 *
 * Returns the signed-in administrator. Used by the frontend admin route guard
 * to validate a stored token on boot.
 */

return function (): void {
    $admin = Auth::require(Token::ROLE_ADMIN);

    Response::success([
        'admin' => [
            'id' => (int) $admin['id'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'role' => Token::ROLE_ADMIN,
        ],
    ]);
};
