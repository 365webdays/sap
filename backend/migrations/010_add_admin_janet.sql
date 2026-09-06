-- Migration 010: Official admin account
--
-- Replaces the seed test admin (admin@stanthonyadoration.com) with the
-- official parish admin account. The seed admin is test data that the
-- migrations README says must be removed before go-live; this migration
-- performs that removal and inserts the real admin in the same step.
--
-- Safe to re-run: the DELETE is a no-op once the test admin is gone, and the
-- INSERT uses ON DUPLICATE KEY UPDATE so a second run updates the row in
-- place instead of failing on the UNIQUE email constraint.

DELETE FROM admins WHERE email = 'admin@stanthonyadoration.com';

INSERT INTO admins (name, email, password_hash)
VALUES (
  'Janet Laguio',
  'bonetp168@gmail.com',
  '$2y$10$uHzSkF4/6lkVsm6mNZuMG.qzBZoxRukVzLnjiHEKEYYlOP9Fi48ny'
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  password_hash = VALUES(password_hash);
