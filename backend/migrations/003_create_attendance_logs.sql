-- Migration 003: Attendance logs
-- Records each check-in event (manual or QR)

CREATE TABLE attendance_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  schedule_id INT NULL COMMENT 'Nullable — user may check in without a schedule',
  check_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  method ENUM('manual', 'qr') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (schedule_id) REFERENCES adoration_schedules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
