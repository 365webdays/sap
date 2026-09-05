-- Seed data for development/staging
-- Test accounts use clearly named emails for easy cleanup before go-live.
--
-- Test adorer accounts:  email = test1@stanthonyadoration.com ... test5@stanthonyadoration.com
-- Admin account:         email = admin@stanthonyadoration.com
--
-- Passwords are not documented here — use the password reset flow or
-- regenerate the bcrypt hashes if you need to know them.
--
-- IMPORTANT: Delete all test accounts before go-live (Phase 11.1).

-- 1 admin
INSERT INTO admins (name, email, password_hash) VALUES
  ('Test Admin', 'admin@stanthonyadoration.com', '$2y$10$W.RtQTZajXIcYy7x2uTDceMQFqBl3SaUZeEtjfS16a1QJ/4GscE62');

-- 5 sample adorers
INSERT INTO users (full_name, email, mobile_number, password_hash, email_verified_at, privacy_consent, is_active) VALUES
  ('Test User One',   'test1@stanthonyadoration.com', '555-0001', '$2y$10$VYurhR2N8RxyKNzPoj.Z.uWXSpsXC7GYvU632ojlQjID666k9cAga', NOW(), TRUE, TRUE),
  ('Test User Two',   'test2@stanthonyadoration.com', '555-0002', '$2y$10$VYurhR2N8RxyKNzPoj.Z.uWXSpsXC7GYvU632ojlQjID666k9cAga', NOW(), TRUE, TRUE),
  ('Test User Three', 'test3@stanthonyadoration.com', '555-0003', '$2y$10$VYurhR2N8RxyKNzPoj.Z.uWXSpsXC7GYvU632ojlQjID666k9cAga', NOW(), TRUE, TRUE),
  ('Test User Four',  'test4@stanthonyadoration.com', '555-0004', '$2y$10$VYurhR2N8RxyKNzPoj.Z.uWXSpsXC7GYvU632ojlQjID666k9cAga', NOW(), TRUE, FALSE),
  ('Test User Five',  'test5@stanthonyadoration.com', '555-0005', '$2y$10$VYurhR2N8RxyKNzPoj.Z.uWXSpsXC7GYvU632ojlQjID666k9cAga', NOW(), TRUE, TRUE);

-- Schedules for the 5 adorers (varied days/times)
INSERT INTO adoration_schedules (user_id, day_of_week, time_slot) VALUES
  (1, 'Monday',    '08:00:00'),
  (2, 'Monday',    '09:00:00'),
  (3, 'Tuesday',   '14:00:00'),
  (4, 'Wednesday', '19:00:00'),
  (5, 'Friday',    '06:00:00');

-- Email preferences for all 5 adorers (defaults)
INSERT INTO email_preferences (user_id, hour_reminders, announcements, attendance_notifications) VALUES
  (1, TRUE, TRUE, TRUE),
  (2, TRUE, TRUE, TRUE),
  (3, TRUE, FALSE, TRUE),
  (4, FALSE, TRUE, FALSE),
  (5, TRUE, TRUE, TRUE);

-- Sample attendance logs
INSERT INTO attendance_logs (user_id, schedule_id, check_in_at, method) VALUES
  (1, 1, '2026-08-25 08:05:00', 'manual'),
  (2, 2, '2026-08-25 09:12:00', 'qr'),
  (3, 3, '2026-08-26 14:03:00', 'manual'),
  (1, 1, '2026-09-01 08:01:00', 'qr');
