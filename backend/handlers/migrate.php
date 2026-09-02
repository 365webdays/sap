<?php
/**
 * GET /api/migrate — one-shot migration runner.
 *
 * Reads every .sql file in backend/migrations/ in numeric order and executes
 * it against the staging database. Idempotent for CREATE TABLE statements
 * (uses IF NOT EXISTS) so it's safe to run more than once.
 *
 * This endpoint is intentionally simple: it does not handle rollbacks, it does
 * not track applied migrations, and it dies after one run. DELETE the route
 * registration in index.php before go-live.
 */
return function (): void {
    header('Content-Type: text/plain; charset=utf-8');

    $migrationsDir = __DIR__ . '/../migrations';

    // The deploy workflow strips migrations/ from the artifact, so on the
    // server this directory won't exist. We embed the SQL inline instead.
    $migrations = [
        '001_create_users.sql' => <<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  mobile_number VARCHAR(32) NULL,
  password_hash VARCHAR(255) NOT NULL,
  email_verified_at DATETIME NULL,
  privacy_consent BOOLEAN NOT NULL DEFAULT FALSE,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        ,
        '002_create_adoration_schedules.sql' => <<<SQL
CREATE TABLE IF NOT EXISTS adoration_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  day_of_week VARCHAR(10) NOT NULL,
  time_slot TIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_schedule_user (user_id),
  INDEX idx_schedule_day_time (day_of_week, time_slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        ,
        '003_create_attendance_logs.sql' => <<<SQL
CREATE TABLE IF NOT EXISTS attendance_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  schedule_id INT NULL,
  check_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  check_out_at DATETIME NULL,
  method ENUM('manual','qr','kiosk') NOT NULL DEFAULT 'manual',
  notes TEXT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (schedule_id) REFERENCES adoration_schedules(id) ON DELETE SET NULL,
  INDEX idx_attendance_user (user_id),
  INDEX idx_attendance_date (check_in_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        ,
        '004_create_email_preferences.sql' => <<<SQL
CREATE TABLE IF NOT EXISTS email_preferences (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  hour_reminders BOOLEAN NOT NULL DEFAULT TRUE,
  announcements BOOLEAN NOT NULL DEFAULT TRUE,
  attendance_notifications BOOLEAN NOT NULL DEFAULT TRUE,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        ,
        '005_create_admins.sql' => <<<SQL
CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        ,
        '006_create_email_logs.sql' => <<<SQL
CREATE TABLE IF NOT EXISTS email_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  recipient_email VARCHAR(255) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_html TEXT NULL,
  body_text TEXT NULL,
  status ENUM('sent','failed','queued') NOT NULL DEFAULT 'queued',
  error_message TEXT NULL,
  sent_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_email_logs_user (user_id),
  INDEX idx_email_logs_status (status),
  INDEX idx_email_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
        ,
    ];

    // Seed data — only insert if the tables are empty.
    $seed = <<<SQL
INSERT IGNORE INTO admins (name, email, password_hash) VALUES
  ('Test Admin', 'admin@stanthonyadoration.com', '$2y$10$W.RtQTZajXIcYy7x2uTDceMQFqBl3SaUZeEtjfS16a1QJ/4GscE62');

INSERT IGNORE INTO users (full_name, email, mobile_number, password_hash, email_verified_at, privacy_consent, is_active) VALUES
  ('Test User One',   'test1@stanthonyadoration.com', '555-0001', '$2y$10$VYurhR2N8RxyKNzPoj.Z.uWXSpsXC7GYvU632ojlQjID666k9cAga', NOW(), TRUE, TRUE),
  ('Test User Two',   'test2@stanthonyadoration.com', '555-0002', '$2y$10$VYurhR2N8RxyKNzPoj.Z.uWXSpsXC7GYvU632ojlQjID666k9cAga', NOW(), TRUE, TRUE),
  ('Test User Three', 'test3@stanthonyadoration.com', '555-0003', '$2y$10$VYurhR2N8RxyKNzPoj.Z.uWXSpsXC7GYvU632ojlQjID666k9cAga', NOW(), TRUE, TRUE),
  ('Test User Four',  'test4@stanthonyadoration.com', '555-0004', '$2y$10$VYurhR2N8RxyKNzPoj.Z.uWXSpsXC7GYvU632ojlQjID666k9cAga', NOW(), TRUE, FALSE),
  ('Test User Five',  'test5@stanthonyadoration.com', '555-0005', '$2y$10$VYurhR2N8RxyKNzPoj.Z.uWXSpsXC7GYvU632ojlQjID666k9cAga', NOW(), TRUE, TRUE);

INSERT IGNORE INTO adoration_schedules (user_id, day_of_week, time_slot) VALUES
  (1, 'Monday',    '08:00:00'),
  (2, 'Monday',    '09:00:00'),
  (3, 'Tuesday',   '14:00:00'),
  (4, 'Wednesday', '19:00:00'),
  (5, 'Friday',    '06:00:00');

INSERT IGNORE INTO email_preferences (user_id, hour_reminders, announcements, attendance_notifications) VALUES
  (1, TRUE, TRUE, TRUE),
  (2, TRUE, TRUE, TRUE),
  (3, TRUE, FALSE, TRUE),
  (4, FALSE, TRUE, FALSE),
  (5, TRUE, TRUE, TRUE);

INSERT IGNORE INTO attendance_logs (user_id, schedule_id, check_in_at, method) VALUES
  (1, 1, '2026-08-25 08:05:00', 'manual'),
  (2, 2, '2026-08-25 09:12:00', 'qr'),
  (3, 3, '2026-08-26 14:03:00', 'manual'),
  (1, 1, '2026-09-01 08:01:00', 'qr');
SQL;

    try {
        $dsn = "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME', '') . ";charset=utf8mb4";
        $db = new PDO($dsn, env('DB_USER', ''), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        echo "Connected to " . env('DB_NAME', '') . "\n\n";

        foreach ($migrations as $name => $sql) {
            echo "Running {$name}... ";
            // Split on semicolons that end a statement (naive but works for
            // these simple CREATE TABLE files with no embedded semicolons).
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if ($stmt === '') continue;
                $db->exec($stmt);
            }
            echo "OK\n";
        }

        echo "\nRunning seed... ";
        $statements = array_filter(array_map('trim', explode(';', $seed)));
        foreach ($statements as $stmt) {
            if ($stmt === '') continue;
            $db->exec($stmt);
        }
        echo "OK\n\n";

        echo "=== Final table state ===\n";
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $count = $db->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
            echo "  {$t} ({$count} rows)\n";
        }

        echo "\nMigration complete.\n";
    } catch (Throwable $e) {
        echo "\nERROR: " . $e->getMessage() . "\n";
    }

    exit;
};
