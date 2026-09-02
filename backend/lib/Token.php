<?php
/**
 * JWT issuing and verification.
 *
 * Tokens carry the subject id plus a role claim ('adorer' or 'admin') so route
 * guards can keep the two account types completely separate — an adorer token
 * must never satisfy an admin route.
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

class Token
{
    public const ROLE_ADORER = 'adorer';
    public const ROLE_ADMIN = 'admin';

    /** Token lifetime in seconds (7 days). */
    private const TTL = 604800;

    private const ALGO = 'HS256';

    private static function secret(): string
    {
        $secret = env('JWT_SECRET', '');
        if ($secret === null || strlen($secret) < 32) {
            // Refuse to sign with a weak or missing key rather than issuing
            // tokens an attacker could forge.
            throw new RuntimeException('JWT_SECRET is missing or too short (need >= 32 chars).');
        }
        return $secret;
    }

    /**
     * Issue a signed token for a user or admin.
     *
     * @param int    $subjectId users.id or admins.id, depending on $role
     * @param string $role      self::ROLE_ADORER or self::ROLE_ADMIN
     */
    public static function issue(int $subjectId, string $role): string
    {
        $now = time();

        return JWT::encode([
            'sub' => $subjectId,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + self::TTL,
        ], self::secret(), self::ALGO);
    }

    /**
     * Verify a token and return its claims, or null if invalid/expired.
     *
     * @return array{sub:int, role:string, iat:int, exp:int}|null
     */
    public static function verify(string $token): ?array
    {
        try {
            $claims = (array) JWT::decode($token, new Key(self::secret(), self::ALGO));
        } catch (ExpiredException $e) {
            return null;
        } catch (Throwable $e) {
            // Malformed, bad signature, unsupported algorithm, ...
            return null;
        }

        // Reject anything missing the claims our guards depend on.
        if (!isset($claims['sub'], $claims['role'])) {
            return null;
        }

        $role = (string) $claims['role'];
        if (!in_array($role, [self::ROLE_ADORER, self::ROLE_ADMIN], true)) {
            return null;
        }

        return [
            'sub' => (int) $claims['sub'],
            'role' => $role,
            'iat' => (int) ($claims['iat'] ?? 0),
            'exp' => (int) ($claims['exp'] ?? 0),
        ];
    }

    public static function ttl(): int
    {
        return self::TTL;
    }
}
