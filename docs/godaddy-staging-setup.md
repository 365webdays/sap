# GoDaddy Staging Setup Guide

This guide walks you through setting up the blank GoDaddy hosting account for the St. Anthony Adoration app. You only need to do this once.

## Prerequisites

- GoDaddy cPanel access
- Domain `stanthonyadoration.com` pointed to the hosting account
- The backend files from this repo (in the `backend/` directory)

---

## Step 1 — Create the MySQL Database

1. Log in to **cPanel**
2. Go to **MySQL Database Wizard**
3. **Create a database** — name it something like `stanthony_adoration` (cPanel will prefix it with your account username, e.g. `username_stanthony_adoration`)
4. **Create a user** — set a strong password, note it down (you'll need it for `.env`)
5. **Assign privileges** — check **All Privileges**
6. Note down:
   - Database name: `username_stanthony_adoration`
   - Database user: `username_dbuser`
   - Password: (what you set)

---

## Step 2 — Create the Staging Subdomain

1. In cPanel, go to **Subdomains**
2. Create a new subdomain:
   - **Subdomain:** `staging`
   - **Domain:** `stanthonyadoration.com`
   - **Document Root:** `staging` (this creates a `staging` folder under `public_html`)
3. Click **Create**

The staging site will be accessible at `https://staging.stanthonyadoration.com` (after SSL is set up in Step 6).

---

## Step 3 — Deploy the Application Files

Deployment is automated. Pushing to `main` triggers the
`.github/workflows/deploy-staging.yml` workflow, which builds the frontend and
FTPS-syncs everything to `public_html/staging/`.

The resulting layout is:

```
public_html/staging/
  ├── index.html        (React SPA — deployed)
  ├── assets/           (React JS/CSS — deployed)
  ├── .htaccess         (SPA fallback routing — deployed)
  └── api/
      ├── index.php     (API front controller — deployed)
      ├── .htaccess     (API routing — deployed)
      ├── lib/          (deployed)
      ├── config/       (deployed)
      ├── handlers/     (deployed)
      ├── cron/         (deployed)
      └── .env          (NOT deployed — lives only on the server)
```

This is the same tree used in production, so go-live only changes the
workflow's `server-dir`.

Two things the deploy deliberately never touches:

- **`.env`** — holds real credentials, excluded from the sync so it is never
  uploaded or deleted.
- **`migrations/`** — SQL stays in the repo and is applied by hand via
  phpMyAdmin (see Step 4), so schema changes are never automated.

### Creating the `.env` file (one time)

1. In cPanel File Manager, navigate to `public_html/staging/api/`
2. Click **+ File**, name it `.env`
   - Right-click → **Edit**, and paste the following (fill in your real values from Step 1):
     ```
     APP_ENV=staging
     APP_BASE_URL=https://staging.stanthonyadoration.com

     DB_HOST=localhost
     DB_NAME=username_stanthony_adoration
     DB_USER=username_dbuser
     DB_PASS=your_db_password

     JWT_SECRET=generate_a_random_string_here

     SMTP_HOST=
     SMTP_USER=
     SMTP_PASS=
     SMTP_PORT=586
     SMTP_FROM_EMAIL=noreply@stanthonyadoration.com
     SMTP_FROM_NAME=St. Anthony Adoration
     ```
   - For `JWT_SECRET`, generate a random string with `openssl rand -hex 32`
   - SMTP values can be left blank for now — they're needed in Phase 6

3. **Set permissions** (optional but recommended):
   - `.env` file: `600` (readable only by owner)
   - All other files: `644`
   - Directories: `755`

---

## Step 4 — Run the Database Migrations

> **Already done.** The staging database was migrated and seeded on 2026-09-02
> via a temporary HTTP migration runner. The 6 tables and test data are live.
> Skip to Step 5 unless you need to re-run migrations (e.g. after a DB reset).

1. In cPanel, go to **phpMyAdmin**
2. Select your database (`username_stanthony_adoration`) from the left sidebar
3. Click the **SQL** tab
4. Run each migration file **in order**. For each file:
   - Open the file from `backend/migrations/` in this repo
   - Copy the entire SQL content
   - Paste it into the phpMyAdmin SQL tab
   - Click **Go**
5. Run in this order:
   1. `001_create_users.sql`
   2. `002_create_adoration_schedules.sql`
   3. `003_create_attendance_logs.sql`
   4. `004_create_email_preferences.sql`
   5. `005_create_admins.sql`
   6. `006_create_email_logs.sql`
   7. `seed.sql` — inserts test data
6. After all migrations, you should see 6 tables in the left sidebar:
   - `admins`, `adoration_schedules`, `attendance_logs`, `email_logs`, `email_preferences`, `users`

---

## Step 5 — Verify the Backend

1. Visit `https://staging.stanthonyadoration.com/api/health` in your browser
2. You should see JSON like:
   ```json
   {
     "success": true,
     "data": {
       "status": "ok",
       "database": true,
       "timestamp": "2026-09-02T14:00:00+00:00",
       "environment": "staging"
     }
   }
   ```
3. If you see `database: false` or an error, check your `.env` DB credentials
4. If you get a 404 or redirect, check that `.htaccess` was uploaded and `mod_rewrite` is enabled

---

## Step 6 — Enable SSL

1. In cPanel, go to **SSL/TLS Status**
2. Click **Run AutoSSL** (or enable Let's Encrypt if available)
3. This should cover both `stanthonyadoration.com` and `staging.stanthonyadoration.com`
4. Verify by visiting `https://staging.stanthonyadoration.com/api/health` — it should load over HTTPS without certificate warnings
5. The `.htaccess` file already includes a rule to redirect all HTTP traffic to HTTPS

---

## Step 7 — Confirm PHP Version

1. In cPanel, go to **MultiPHP Manager**
2. Ensure `staging.stanthonyadoration.com` is set to **PHP 8.1** or higher (8.2+ preferred)
3. The backend code requires PHP 8.0+ (uses `str_contains`, `str_starts_with`, named args, etc.)

---

## Step 8 — Configure SMTP (required for Phase 6)

Before the cron emails can send, the SMTP mailbox must be set up and the `.env`
populated with real credentials.

1. In cPanel, go to **Email Accounts** and create a mailbox, e.g.
   `noreply@stanthonyadoration.com`
2. Note the password you set
3. Find the SMTP server name — on GoDaddy shared hosting it's typically
   `smtpout.secureserver.net`
4. Edit `public_html/staging/api/.env` and fill in the SMTP block:
   ```
   SMTP_HOST=smtpout.secureserver.net
   SMTP_USER=noreply@stanthonyadoration.com
   SMTP_PASS=your_mailbox_password
   SMTP_PORT=587
   SMTP_FROM_EMAIL=noreply@stanthonyadoration.com
   SMTP_FROM_NAME=St. Anthony Adoration
   ```
5. Test by sending a bulk email from the admin panel (Email → Compose) to a
   small group and confirming it arrives

---

## Step 9 — Run Migration 008 (Phase 6)

The `sent_reminders` table is needed by the cron scripts for dedup.

1. In cPanel, go to **phpMyAdmin**
2. Select your database
3. Click the **SQL** tab
4. Open `backend/migrations/008_phase6_reminders.sql` from this repo
5. Copy the entire SQL content, paste it into phpMyAdmin, click **Go**
6. You should now see a `sent_reminders` table in the left sidebar

---

## Step 10 — Configure Cron Jobs (Phase 6)

The automated reminder emails run as PHP cron scripts invoked by the cPanel
cron scheduler. Two jobs are needed:

### 10.1 Pre-Adoration Hour Reminder (every 15 minutes)

Sends a reminder email to adorers whose scheduled hour starts within the next
60 minutes. Running every 15 minutes gives each hour up to 4 chances to send,
with the `sent_reminders` table preventing duplicates.

1. In cPanel, go to **Cron Jobs**
2. Click **Add New Cron Job**
3. Set the schedule to **every 15 minutes**:
   - Minute: `*/15`
   - Hour: `*`
   - Day: `*`
   - Month: `*`
   - Weekday: `*`
4. Command:
   ```
   /usr/local/bin/php /home/USERNAME/public_html/staging/api/cron/hour_reminder.php
   ```
   Replace `USERNAME` with your cPanel account username, and verify the PHP
   path with `which php` in cPanel Terminal if unsure.
5. Click **Add New Cron Job**

### 10.2 Missed Attendance Notification (hourly)

Notifies adorers who missed their scheduled hour(s) yesterday. One email per
adorer listing all missed hours.

The cPanel cron daemon evaluates schedules in server time (UTC on GoDaddy),
but the script needs to fire after midnight **parish local time**
(`APP_TIMEZONE`, e.g. `America/Vancouver`). To avoid manual DST schedule
changes twice a year, the cron runs hourly year-round and the script itself
exits early unless the current local hour is 0 (midnight). Exactly one firing
per day will land in that window — automatically correct under both PDT
(UTC-7, fires at 07:00 UTC) and PST (UTC-8, fires at 08:00 UTC).

1. Click **Add New Cron Job**
2. Set the schedule to **every hour, on the hour**:
   - Minute: `0`
   - Hour: `*`
   - Day: `*`
   - Month: `*`
   - Weekday: `*`
3. Command:
   ```
   /usr/local/bin/php /home/USERNAME/public_html/staging/api/cron/missed_notification.php
   ```
4. Click **Add New Cron Job**

### 10.3 Test the Cron Scripts Manually

Before relying on the scheduler, run each script once by hand to confirm it
executes without errors. Use cPanel **Terminal** (or SSH if available):

```bash
php /home/USERNAME/public_html/staging/api/cron/hour_reminder.php
php /home/USERNAME/public_html/staging/api/cron/missed_notification.php
```

You should see a one-line summary like:
```
hour_reminder: sent=0 skipped=0 failed=0 window=09:55-10:55 day=Friday
missed_notification: outside local midnight window (hour=14), skipping
```

The missed-notification script will report `outside local midnight window`
unless it is currently the 00:00 hour in parish local time. That is the
expected output outside the midnight window — it confirms the guard is
working. To verify the send path, either wait until midnight local time or
temporarily comment out the hour guard in the script and re-run.

If you see `SMTP not configured — skipping`, return to Step 8 and fill in the
SMTP credentials in `.env`.

---

## Troubleshooting

### Testing before DNS propagates

The domain must resolve to the cPanel hosting IP (`107.180.114.51`) for anything
to work. While DNS is still propagating you can bypass it entirely with curl's
`--resolve`, which pins a hostname to a specific IP for that one request:

```bash
curl -k --resolve staging.stanthonyadoration.com:443:107.180.114.51 \
  https://staging.stanthonyadoration.com/api/health
```

`-k` is needed because AutoSSL cannot issue a certificate for a hostname that
does not yet resolve to the server, so the presented cert will not match.

Note this only helps command-line testing. A browser uses system DNS, so the
app is not reachable in a browser until the real records are correct.

### Parked "stanthonyadoration.com" page instead of the app

If the site shows a GoDaddy placeholder page (title `stanthonyadoration.com`),
DNS is pointing at GoDaddy's **domain parking / forwarding** service rather than
at the hosting account. Those records resolve to AWS Global Accelerator IPs
(e.g. `13.248.243.5`, `15.197.225.128`), which is how you can tell:

```bash
dig +short stanthonyadoration.com @1.1.1.1   # want 107.180.114.51
dig +short -x <returned-ip> @1.1.1.1         # *.awsglobalaccelerator.com = parked
```

Fix it in **DNS Management**: point the `@` A record — and a `staging` A record —
at `107.180.114.51`, and delete any domain forwarding rule for `staging`.

Domain forwarding is not a substitute for a subdomain. It only issues an HTTP
redirect, so it creates no Apache VirtualHost, no document root, and no TLS
certificate.

### Do not serve staging from a subdirectory

Redirecting `staging.stanthonyadoration.com` to `stanthonyadoration.com/staging`
appears to work — the API responds, because the router derives its mount point
from `SCRIPT_NAME` — but the React app breaks. Vite builds absolute asset paths
(`/assets/index-*.js`), which resolve against the domain root and 404 under a
subdirectory, leaving a blank page.

Use a real subdomain whose document root is `staging`. That serves the app at
its own root, so the absolute paths are correct and staging matches production
exactly.

### "Not found" (404) when visiting /api/health
- Ensure `.htaccess` was uploaded to `public_html/staging/`
- In cPanel → **MultiPHP Manager**, confirm `AllowOverride` is enabled (it should be by default)
- Check that `mod_rewrite` is active (it is on GoDaddy by default)

### Database connection error in /api/health
- Double-check `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` in the `.env` file
- `DB_HOST` should be `localhost` on GoDaddy shared hosting
- Verify the database user has **All Privileges** on the database

### 500 Internal Server Error
- Check cPanel → **Errors** for PHP error logs
- Ensure PHP version is 8.0+
- Verify all files were uploaded completely (especially `lib/` and `config/`)

### .env file not being read
- Ensure the file is named exactly `.env` (with the dot, no extension)
- In cPanel File Manager, enable **Show Hidden Files** (Settings gear → check "Show Hidden Files")
