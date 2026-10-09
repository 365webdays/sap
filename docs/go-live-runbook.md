# Go-Live Runbook — stanthonyadoration.com

Ordered steps to promote the app from `staging.stanthonyadoration.com` to the
root domain and retire staging. Every step is manual in cPanel / GoDaddy DNS
except the deploy itself, which runs from GitHub Actions on push to `main`.

**Order matters.** Deploy and verify the production tree *before* flipping
DNS, so the root domain never serves a half-built site. The first workflow run
is expected to fail at the health-check step (see Step 2).

Hosting IP: `107.180.114.51`
Current root DNS (pre-cutover): `13.248.243.5` = GoDaddy Website Builder (wrong)

---

## Step 0 — Pre-flight

- [ ] Client sign-off on staging (QA checklist complete)
- [ ] Code changes merged: workflow targets `./public_html/`, API URL
      `https://stanthonyadoration.com/api`
- [ ] GitHub secrets `FTP_HOST`, `FTP_USERNAME`, `FTP_PASSWORD` present (same
      hosting account as staging — no change needed)

## Step 1 — cPanel prep (DNS still pointing at the builder)

1. **SSL/TLS Status** — confirm a valid certificate covers both
   `stanthonyadoration.com` and `www.stanthonyadoration.com`. The deploy
   health check validates TLS; a missing cert fails every deploy.
2. **MultiPHP Manager** — root domain on PHP 8.1 or newer.
3. **File Manager** → `public_html/` → **+ Folder** `api` → inside it
   **+ File** `.env` (enable *Show Hidden Files* in Settings). Paste the
   staging `.env` and change only:
   ```
   APP_ENV=production
   APP_BASE_URL=https://stanthonyadoration.com
   ```
   Keep `DB_*` as-is (one shared database). Keep `JWT_SECRET` the same so
   existing sessions stay valid, or rotate it to force everyone to sign in
   again. Keep the SMTP block as-is.
4. Set `.env` permissions to `600`.

## Step 2 — First production deploy

Push (or merge) to `main`. The workflow builds, syncs to `public_html/`, then
polls `https://stanthonyadoration.com/api/health`.

**Expected: the health step fails** — DNS still resolves to the Website
Builder, which answers with HTML. The files are already on the server; only
the final check is red.

Do not weaken the health check to get a green run here.

## Step 3 — Verify without DNS

Pin the hostname to the hosting IP for the request:

```bash
curl --resolve stanthonyadoration.com:443:107.180.114.51 \
  https://stanthonyadoration.com/api/health
# want: {"success":true,"data":{"status":"ok","database":true,...,"environment":"production"}}

curl -s --resolve stanthonyadoration.com:443:107.180.114.51 \
  https://stanthonyadoration.com/ | head -5
# want: the SPA index.html (<!doctype html> ... St. Anthony Adoration)
```

If `environment` is not `production`, the `.env` from Step 1 is missing or in
the wrong directory (`public_html/api/.env`).

## Step 4 — DNS flip (GoDaddy → Domain → DNS Management)

| Record | Name  | Value              | Action |
|--------|-------|--------------------|--------|
| A      | `@`   | `107.180.114.51`   | edit (was `13.248.243.5`) |
| CNAME  | `www` | `@`                | edit / add |
| A      | `staging` | `107.180.114.51` | leave until Step 7 |

- Remove any **Forwarding** rules and disconnect the Website Builder from
  this domain if it offers to keep forwarding.
- **Do not change MX, SPF (TXT), or DKIM records** — `noreply@` mail
  delivery depends on them.
- Lower the TTL beforehand if you want a faster switch; otherwise allow up to
  an hour.

Verify:
```bash
dig +short stanthonyadoration.com @1.1.1.1      # 107.180.114.51
dig +short www.stanthonyadoration.com @1.1.1.1  # ends in 107.180.114.51
curl -sI https://www.stanthonyadoration.com/ | head -3   # 301 → https://stanthonyadoration.com/
curl -sI https://stanthonyadoration.com/.ftp-deploy-sync-state.json | head -1  # 403
```

## Step 5 — Green deploy

GitHub → Actions → *Deploy to Production* → **Re-run all jobs**. The health
check should now pass. All future pushes to `main` deploy straight to
production.

## Step 6 — Cron jobs (cPanel → Cron Jobs)

Replace `USERNAME` with the cPanel account name (`which php` in Terminal
confirms the PHP path).

| Schedule         | Command |
|------------------|---------|
| `*/15 * * * *`   | `/usr/local/bin/php /home/USERNAME/public_html/api/cron/hour_reminder.php` |
| `0 * * * *`      | `/usr/local/bin/php /home/USERNAME/public_html/api/cron/missed_notification.php` |

- **Delete the two staging cron entries** (`.../public_html/staging/api/cron/...`).
  Both environments share the database; the `sent_reminders` dedup table
  prevents most double-sends, but two schedulers firing in the same minute
  can race.
- Run each script once from cPanel Terminal and confirm a clean summary line:
  ```bash
  php /home/USERNAME/public_html/api/cron/hour_reminder.php
  php /home/USERNAME/public_html/api/cron/missed_notification.php
  ```
  `missed_notification: outside local midnight window` is the expected
  output outside 00:00 Vancouver time.

## Step 7 — Retire staging (after at least one stable day)

1. cPanel → **Cron Jobs**: confirm no staging entries remain (Step 6).
2. cPanel → **Subdomains**: remove `staging.stanthonyadoration.com`.
3. cPanel → **File Manager**: delete `public_html/staging/` (this also removes
   the staging `.env`).
4. DNS: delete the `staging` A record.

Keeping the folder until this point is the rollback path (see below).

## Step 8 — Production smoke tests

- [ ] `https://stanthonyadoration.com` loads over HTTPS with no warnings;
      `www` redirects to the bare domain
- [ ] Register a new adorer → welcome email arrives (check spam)
- [ ] Log in as the adorer → manual check-in → appears in history
- [ ] Admin login at `/admin/login` with `bonetp168@gmail.com`
- [ ] Admin dashboard → QR panel shows `https://stanthonyadoration.com/checkin`;
      download the PNG
- [ ] Scan the new QR with a phone → check-in recorded with method `qr`
- [ ] Send a bulk email to a 1–2 person group → arrives, logged in history
- [ ] Dashboard counts and coverage grid render with live data
- [ ] Install prompt works on Android (Chrome) and iOS (Safari share sheet)

## Step 9 — Handover

- [ ] Production QR code PNG for the chapel entrance (replace any staging QR)
- [ ] Admin credentials to the parish admin
- [ ] `docs/qa-checklist.md` (now pointing at the production URL) as the
      usage/verification reference

---

## Rollback

Until Step 7 runs, the staging tree is intact. To revert the public site:
repoint the `@` and `www` DNS records back to their previous values. The
production files in `public_html/` can stay; nothing in the database needs
undoing because staging and production share it.

## Post-launch notes

- **Test accounts kept.** `test1@…test5@stanthonyadoration.com` remain in the
  database by decision. They inflate *Total/Active Adorers*, occupy coverage
  slots, and will receive cron and bulk emails to mailboxes that do not exist
  (bounces land at `noreply@`; bulk sends show `failed_count`). Deactivating
  them in Admin → Adorers removes them from reminders and active counts; a
  later `DELETE FROM users WHERE email LIKE 'test%@stanthonyadoration.com'`
  cascades cleanly through all related tables.
- **Login rate limit is per IP** (5 failures / 15 minutes, adorer and admin
  tracked separately). On shared parish Wi-Fi every device appears as one IP,
  so one person's typos can briefly lock out everyone on that network.
- **HSTS.** The old Website Builder sent `Strict-Transport-Security` with
  `includeSubDomains`. Browsers that visited it will refuse any subdomain
  without a valid certificate — one more reason staging (self-signed) is
  retired rather than kept.
- **PHP errors** go to cPanel → *Errors* / `error_log`, never to the client.
