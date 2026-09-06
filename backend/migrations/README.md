# Database Migrations

Run these files in numeric order via phpMyAdmin (cPanel → phpMyAdmin → select your database → SQL tab).

## Order

1. `001_create_users.sql`
2. `002_create_adoration_schedules.sql`
3. `003_create_attendance_logs.sql`
4. `004_create_email_preferences.sql`
5. `005_create_admins.sql`
6. `006_create_email_logs.sql`
7. `007_phase5_admin.sql` — missed_followups table, email_logs delivery counts, report indexes
8. `008_phase6_reminders.sql` — sent_reminders dedup table for cron emails
9. `009_login_rate_limit.sql` — login_attempts table for brute-force protection
10. `seed.sql` — inserts test data (1 admin, 5 adorers with schedules)
11. `010_add_admin_janet.sql` — replaces the seed test admin with the official parish admin account

## Test Accounts (after seeding)

| Role   | Email                              |
|--------|------------------------------------|
| Admin  | admin@stanthonyadoration.com       |
| Adorer | test1@stanthonyadoration.com       |
| Adorer | test2@stanthonyadoration.com       |
| Adorer | test3@stanthonyadoration.com       |
| Adorer | test4@stanthonyadoration.com       |
| Adorer | test5@stanthonyadoration.com       |

Passwords are not documented in the repo. If you need access to a test
account, regenerate the bcrypt hash in `seed.sql` with a known password.

## Official Admin Account (after migration 010)

| Role  | Name          | Email                 |
|-------|---------------|-----------------------|
| Admin | Janet Laguio  | bonetp168@gmail.com   |

Migration 010 deletes the seed test admin (`admin@stanthonyadoration.com`)
and inserts the official parish admin above. The password is not stored in
the repo — only the bcrypt hash is.

## Notes

- These migration files create tables without `IF NOT EXISTS` — they will error if run twice. This is intentional to prevent accidental data loss.
- **Delete all test accounts before go-live** (see Phase 11.1 of the dev plan).
- Staging and production share the same database — run migrations only once.
