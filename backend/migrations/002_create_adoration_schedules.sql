-- Migration 002: Adoration schedules
-- Each user can have one or more assigned day/time slots

CREATE TABLE adoration_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  day_of_week VARCHAR(16) NOT NULL COMMENT 'e.g. Monday, Tuesday',
  time_slot TIME NOT NULL COMMENT 'e.g. 08:00:00',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
