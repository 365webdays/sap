-- Migration 006: Email logs
-- Audit trail for all bulk/announcement emails sent by admins

CREATE TABLE email_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  recipient_group VARCHAR(64) NOT NULL COMMENT 'all|active|inactive|missed',
  sent_by_admin_id INT NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sent_by_admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
