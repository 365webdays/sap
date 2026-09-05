<?php
/**
 * Rate limiting for login endpoints.
 *
 * Tracks failed login attempts by IP address in the login_attempts table.
 * After MAX_ATTEMPTS failures within WINDOW_SECONDS, the IP is locked out
 * until the oldest attempt in the window expires. Successful logins clear
 * the counter for that IP + endpoint.
 *
 * The limit is per-IP, per-endpoint so /auth/login and /admin/login are
 * tracked independently. This is intentionally simple — no account-level
 * lockout (which could be abused to lock out other users) and no CAPTCHA.
 */
class RateLimiter
{
    /** Max failed attempts before lockout. */
    private const MAX_ATTEMPTS = 5;

    /** Sliding window in seconds (15 minutes). */
    private const WINDOW_SECONDS = 900;

    public const ENDPOINT_AUTH_LOGIN = 'auth_login';
    public const ENDPOINT_ADMIN_LOGIN = 'admin_login';

    /**
     * Returns the number of remaining attempts, or 0 if locked out.
     * When locked out, also sends a Retry-After header.
     */
    public static function remainingAttempts(string $endpoint): int
    {
        $ip = self::clientIp();
        $cutoff = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = :ip AND endpoint = :endpoint AND attempted_at >= :cutoff'
        );
        $stmt->execute([
            'ip' => $ip,
            'endpoint' => $endpoint,
            'cutoff' => $cutoff,
        ]);
        $count = (int) $stmt->fetchColumn();

        $remaining = max(0, self::MAX_ATTEMPTS - $count);

        if ($remaining === 0) {
            // Tell the client how long until the oldest attempt expires.
            $stmt = $db->prepare(
                'SELECT MIN(attempted_at) FROM login_attempts
                 WHERE ip_address = :ip AND endpoint = :endpoint AND attempted_at >= :cutoff'
            );
            $stmt->execute([
                'ip' => $ip,
                'endpoint' => $endpoint,
                'cutoff' => $cutoff,
            ]);
            $oldest = $stmt->fetchColumn();
            if ($oldest !== false) {
                $retryAfter = strtotime((string) $oldest) + self::WINDOW_SECONDS - time();
                header('Retry-After: ' . max(1, $retryAfter));
            }
        }

        return $remaining;
    }

    /**
     * Record a failed attempt and prune old rows opportunistically.
     */
    public static function recordFailure(string $endpoint): void
    {
        $ip = self::clientIp();

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'INSERT INTO login_attempts (ip_address, endpoint) VALUES (:ip, :endpoint)'
        );
        $stmt->execute([
            'ip' => $ip,
            'endpoint' => $endpoint,
        ]);

        self::prune();
    }

    /**
     * Clear all attempts for this IP + endpoint (call on successful login).
     */
    public static function clear(string $endpoint): void
    {
        $ip = self::clientIp();

        $db = Database::getConnection();
        $stmt = $db->prepare(
            'DELETE FROM login_attempts WHERE ip_address = :ip AND endpoint = :endpoint'
        );
        $stmt->execute([
            'ip' => $ip,
            'endpoint' => $endpoint,
        ]);
    }

    /**
     * Delete rows older than the window to keep the table small.
     */
    private static function prune(): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
        $db = Database::getConnection();
        $db->prepare('DELETE FROM login_attempts WHERE attempted_at < :cutoff')
            ->execute(['cutoff' => $cutoff]);
    }

    private static function clientIp(): string
    {
        // GoDaddy shared hosting sits behind a proxy; respect X-Forwarded-For
        // but fall back to REMOTE_ADDR. Take the first IP if there's a chain.
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);
            if ($first !== '') {
                return $first;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
