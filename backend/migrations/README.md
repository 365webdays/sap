# Database Migrations

Run these files in numeric order via phpMyAdmin (cPanel → phpMyAdmin → select your database → SQL tab).

## Order

1. `001_create_users.sql`
2. `002_create_adoration_schedules.sql`
3. `003_create_attendance_logs.sql`
4. `004_create_email_preferences.sql`
5. `005_create_admins.sql`
6. `006_create_email_logs.sql`
7. `seed.sql` — inserts test data (1 admin, 5 adorers with schedules)

## Test Credentials (after seeding)

| Role   | Email                              | Password       |
|--------|------------------------------------|----------------|
| Admin  | admin@stanthonyadoration.com       | admin123       |
| Adorer | test1@stanthonyadoration.com       | password123    |
| Adorer | test2@stanthonyadoration.com       | password123    |
| Adorer | test3@stanthonyadoration.com       | password123    |
| Adorer | test4@stanthonyadoration.com       | password123    |
| Adorer | test5@stanthonyadoration.com       | password123    |

## Notes

- These migration files create tables without `IF NOT EXISTS` — they will error if run twice. This is intentional to prevent accidental data loss.
- **Delete all test accounts before go-live** (see Phase 11.1 of the dev plan).
- Staging and production share the same database — run migrations only once.
