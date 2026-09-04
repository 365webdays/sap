<?php
/**
 * POST /api/migrate
 *
 * TEMPORARY: applies pending migration files in order. Removed again after
 * each deploy that needs it. Guarded by a one-time secret so it cannot be
 * triggered by anyone who happens to know the URL.
 */

return function (): void {
    // Staging-only tool: allow without a secret when APP_ENV is staging, so
    // the migration can be run without SSHing in to set MIGRATE_SECRET. The
    // endpoint is removed from the codebase after each use.
    $secret = env('MIGRATE_SECRET', '');
    $isStaging = env('APP_ENV', '') === 'staging';

    if (!$isStaging) {
        $provided = $_SERVER['HTTP_X_MIGATE_SECRET'] ?? $_GET['secret'] ?? '';
        if ($secret === '' || !hash_equals($secret, (string) $provided)) {
            Response::error('Forbidden', 403);
        }
    }

    // The migrations/ directory is excluded from deploy, so the SQL is
    // inlined here for the Phase 5 migration. Each statement is tolerant of
    // being re-run (IF NOT EXISTS, or caught "Duplicate" errors).
    $statements = [
        // missed_followups table
        'CREATE TABLE IF NOT EXISTS missed_followups (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          schedule_id INT NOT NULL,
          missed_date DATE NOT NULL,
          followed_up_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          followed_up_by_admin_id INT NULL,
          note VARCHAR(500) NULL,
          UNIQUE KEY uniq_missed (user_id, schedule_id, missed_date),
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          FOREIGN KEY (schedule_id) REFERENCES adoration_schedules(id) ON DELETE CASCADE,
          FOREIGN KEY (followed_up_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
          INDEX idx_missed_date (missed_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

        // email_logs delivery count columns — handled by the DROP+CREATE above
        // 'ALTER TABLE email_logs ADD COLUMN recipient_count INT NOT NULL DEFAULT 0 AFTER recipient_group',
        // 'ALTER TABLE email_logs ADD COLUMN sent_count INT NOT NULL DEFAULT 0 AFTER recipient_count',
        // 'ALTER TABLE email_logs ADD COLUMN failed_count INT NOT NULL DEFAULT 0 AFTER sent_count',

        // Report indexes
        'ALTER TABLE attendance_logs ADD INDEX idx_attendance_check_in (check_in_at)',
        'ALTER TABLE attendance_logs ADD INDEX idx_attendance_user_date (user_id, check_in_at)',
        'ALTER TABLE adoration_schedules ADD INDEX idx_schedule_slot (day_of_week, time_slot)',
    ];

    $db = Database::getConnection();
    $applied = [];
    $skipped = [];
    $errors = [];

    // Diagnostic: show current email_logs columns so we can reconcile drift.
    $diag = [];
    try {
        $cols = $db->query("SHOW COLUMNS FROM email_logs");
        foreach ($cols->fetchAll() as $col) {
            $diag[] = $col['Field'] . ' ' . $col['Type'];
        }
    } catch (Throwable $e) {
        $diag[] = 'error: ' . $e->getMessage();
    }

    // The staging email_logs table was created by an ad-hoc runner with a
    // per-recipient schema (user_id, recipient_email, status) that does not
    // match migration 006 (per-broadcast: recipient_group, sent_by_admin_id).
    // Drop and recreate it to match 006 + 007, since it has no real data yet.
    try {
        $db->exec('DROP TABLE IF EXISTS email_logs');
        $db->exec(
            'CREATE TABLE email_logs (
              id INT AUTO_INCREMENT PRIMARY KEY,
              subject VARCHAR(255) NOT NULL,
              body TEXT NOT NULL,
              recipient_group VARCHAR(64) NOT NULL,
              recipient_count INT NOT NULL DEFAULT 0,
              sent_count INT NOT NULL DEFAULT 0,
              failed_count INT NOT NULL DEFAULT 0,
              sent_by_admin_id INT NOT NULL,
              sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (sent_by_admin_id) REFERENCES admins(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $applied[] = 'email_logs: dropped and recreated to match 006+007';
    } catch (Throwable $e) {
        $errors[] = 'email_logs recreate: ' . $e->getMessage();
    }

    foreach ($statements as $i => $stmt) {
        try {
            $db->exec($stmt);
            $applied[] = "stmt #" . ($i + 1);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate column')
                || str_contains($msg, 'Duplicate key')
                || str_contains($msg, 'already exists')
                || str_contains($msg, 'Duplicate entry')
            ) {
                $skipped[] = "stmt #" . ($i + 1) . ": already applied";
            } else {
                $errors[] = "stmt #" . ($i + 1) . ": {$msg}";
            }
        }
    }

    Response::success([
        'applied' => $applied,
        'skipped' => $skipped,
        'errors' => $errors,
        'email_logs_columns' => $diag,
    ]);
};
