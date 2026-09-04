-- Migration 008: Phase 6 automated reminder dedup log
--
-- Cron-sent emails (hour reminders, missed-attendance notifications) are
-- logged here so the cron scripts can skip a send that already went out in
-- the same window. This is separate from email_logs, which tracks
-- admin-composed announcements only.
--
-- time_slot is NOT NULL with a default of '00:00:00' because MySQL treats
-- NULL as distinct in unique indexes (multiple NULLs allowed), which would
-- break dedup. For 'hour' reminders the real slot is stored; for 'missed'
-- notifications the default sentinel is used since we send one per user per
-- day, not per slot.

CREATE TABLE IF NOT EXISTS sent_reminders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  reminder_type VARCHAR(32) NOT NULL COMMENT 'hour|missed',
  reference_date DATE NOT NULL COMMENT 'the scheduled date being reminded about',
  time_slot TIME NOT NULL DEFAULT '00:00:00',
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_reminder (user_id, reminder_type, reference_date, time_slot),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_reminder_date (reference_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
