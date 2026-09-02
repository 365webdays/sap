<?php
/**
 * Request authentication guard.
 *
 * Reads the bearer token, verifies it, and confirms the account still exists
 * and is active. Handlers call require() and get back a trusted identity, or
 * the request is terminated with a 401/403 before the handler body runs.
 */

class Auth
{
    /**
     * Extract the bearer token from the Authorization header.
     *
     * Falls back to apache_request_headers() because some Apache/CGI setups
     * (GoDaddy shared hosting among them) do not expose HTTP_AUTHORIZATION in
     * $_SERVER unless a rewrite rule forwards it.
     */
    private static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $header = $value;
                    break;
                }
            }
        }

        if (!preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m)) {
            return null;
        }

        return $m[1];
    }

    /**
     * Require a valid token for the given role and return the account record.
     *
     * Exits with 401 when the token is missing/invalid and 403 when the role
     * does not match, so an adorer token can never reach an admin route.
     *
     * @return array The users or admins row, plus a 'role' key.
     */
    public static function require(string $role): array
    {
        $token = self::bearerToken();
        if ($token === null) {
            Response::error('Authentication required', 401);
        }

        $claims = Token::verify($token);
        if ($claims === null) {
            Response::error('Invalid or expired token', 401);
        }

        if ($claims['role'] !== $role) {
            Response::error('Forbidden', 403);
        }

        $account = $role === Token::ROLE_ADMIN
            ? self::findAdmin($claims['sub'])
            : self::findActiveUser($claims['sub']);

        // The token was valid but the account was deleted or deactivated after
        // it was issued — treat it as unauthenticated.
        if ($account === null) {
            Response::error('Account is no longer active', 401);
        }

        $account['role'] = $role;
        return $account;
    }

    private static function findActiveUser(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, full_name, email, mobile_number, email_verified_at,
                    privacy_consent, is_active, created_at
             FROM users
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    private static function findAdmin(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT id, name, email, created_at FROM admins WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }
}
