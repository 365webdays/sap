-- Migration 007: Phase 5 admin support
--
-- Two jobs:
--   1. Reconcile drift. The staging database was originally created by an
--      ad-hoc runner that did not match 003/006 exactly, so those tables are
--      brought back in line here.
--   2. Add what the admin panel needs: bulk-email send counts and a place to
--      record missed-hour follow-ups.
--
-- The ALTERs below are written for a fresh, aligned database. Applying this to
-- a drifted one is done by the migration runner, which checks
-- information_schema first so each step is skipped when already satisfied.

-- --- Missed attendance follow-ups -------------------------------------------
-- Missed hours are derived (a scheduled hour with no matching check-in), not
-- stored. Only the admin's follow-up action needs persisting, keyed by the
-- adorer, the schedule, and the date that was missed.
CREATE TABLE IF NOT EXISTS missed_followups (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Bulk email delivery counts ---------------------------------------------
-- 006 records what was sent and to which group; these add how delivery went,
-- so the admin history can show partial failures rather than just "sent".
ALTER TABLE email_logs
  ADD COLUMN recipient_count INT NOT NULL DEFAULT 0 AFTER recipient_group,
  ADD COLUMN sent_count INT NOT NULL DEFAULT 0 AFTER recipient_count,
  ADD COLUMN failed_count INT NOT NULL DEFAULT 0 AFTER sent_count;

-- --- Indexes for the admin reports ------------------------------------------
-- Attendance is filtered and grouped by date constantly in Phase 5.
ALTER TABLE attendance_logs ADD INDEX idx_attendance_check_in (check_in_at);
ALTER TABLE attendance_logs ADD INDEX idx_attendance_user_date (user_id, check_in_at);
ALTER TABLE adoration_schedules ADD INDEX idx_schedule_slot (day_of_week, time_slot);
