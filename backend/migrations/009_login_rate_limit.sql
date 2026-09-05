-- Migration 009: Login rate limiting
--
-- Tracks failed login attempts by IP address so the login handlers can
-- throttle brute-force attacks. Successful logins clear the counter for
-- that IP; old rows are pruned on each check.
--
-- The key is (ip_address, endpoint) so adorer-login and admin-login limits
-- are tracked independently — an attacker hammering /auth/login does not
-- also lock out /admin/login for the same IP.

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6',
  endpoint VARCHAR(32) NOT NULL COMMENT 'auth_login|admin_login',
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lookup (ip_address, endpoint, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
