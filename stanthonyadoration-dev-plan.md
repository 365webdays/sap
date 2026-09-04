# St. Anthony of Padua Parish
## Adoration Chapel Registration & Attendance Web Application
### Development Plan for Devin AI

**Client:** Janet Laguio (jplaguio@gmail.com)
**Developer:** Michael Peralta (365webdays@gmail.com)
**Domain:** https://stanthonyadoration.com
**Staging URL:** https://staging.stanthonyadoration.com *(active development happens here until go-live)*
**Staging Folder:** `stanthonyadoration.com/staging` (physical folder on GoDaddy hosting)
**Stack:** React + Vite + Tailwind CSS (Frontend) · PHP + MySQL (Backend) · GoDaddy Hosting

---

## Project Overview

Build a Progressive Web App (PWA) for managing Adoration Chapel registration and attendance at St. Anthony of Padua Parish. Adorers can register, log in, check in manually or via QR code, and manage notification preferences. Administrators can manage users, view analytics, run reports, and send bulk email announcements.

---

## Phase 1 — Project Setup & Infrastructure

### 1.1 Repository & Tooling
- [ ] Initialize Git repository
- [ ] Scaffold frontend with `npm create vite@latest` (React + TypeScript)
- [ ] Install and configure Tailwind CSS
- [ ] Set up ESLint + Prettier
- [ ] Create `/frontend` and `/backend` directory structure

### 1.2 Backend Bootstrap
- [ ] Set up PHP project structure with clean URL routing (e.g. `.htaccess` rewrites or a lightweight router)
- [ ] Create MySQL database and configure connection via PDO
- [ ] Set up `.env` file for DB credentials, mail config, app secret/JWT key
- [ ] Configure CORS headers for frontend-backend communication
- [ ] Set up a consistent JSON response format: `{ "success": true, "data": ... }` or `{ "success": false, "error": "..." }`

### 1.3 Hosting & Deployment Prep
- [ ] Point domain `stanthonyadoration.com` to GoDaddy hosting
- [ ] Configure `staging.stanthonyadoration.com` as a subdomain in GoDaddy cPanel, pointing to the `/staging` folder
- [ ] Configure SSL certificate to cover both the root domain and the staging subdomain (enforce HTTPS, redirect all HTTP to HTTPS)
- [ ] Set up deployment workflow (FTP or Git-based deploy)
- [ ] Configure GoDaddy cPanel cron jobs for scheduled tasks (Phase 6)

### 1.4 Staging Environment
- [ ] Staging is served at `https://staging.stanthonyadoration.com` — this subdomain points to the `/staging` folder on the server
- [ ] All development and testing is done on staging — **do not touch the root domain until go-live**
- [ ] **Shared database:** staging and production use the same single MySQL database — no separate DB or table prefixes needed
- [ ] Configure a staging `.env` file: same DB credentials as production, but with `APP_ENV=staging` and `APP_BASE_URL=https://staging.stanthonyadoration.com`
- [ ] Set `VITE_API_BASE_URL=https://staging.stanthonyadoration.com/api` in the frontend staging `.env`
- [ ] Because the DB is shared, any adorers or admins registered on staging are real records — use clearly named test accounts (e.g. `test@stanthonyadoration.com`) and clean them up before go-live
- [ ] QR code generated during development should point to `https://staging.stanthonyadoration.com/checkin` — regenerate pointing to the production URL at go-live
- [ ] Once client signs off on staging, go-live is a simple file copy — see Phase 11

---

## Phase 2 — Database Design

### 2.1 Schema

```sql
users
  - id, full_name, email, mobile_number, password_hash,
    email_verified_at, privacy_consent, is_active, created_at

adoration_schedules
  - id, user_id, day_of_week (e.g. "Monday"), time_slot (e.g. "08:00:00"), created_at

attendance_logs
  - id, user_id, schedule_id (nullable), check_in_at, method (manual | qr), created_at

email_preferences
  - id, user_id, hour_reminders (bool), announcements (bool),
    attendance_notifications (bool), updated_at

admins
  - id, name, email, password_hash, created_at

email_logs
  - id, subject, body, recipient_group, sent_by_admin_id, sent_at
```

### 2.2 Migrations
- [ ] Write SQL migration scripts for all tables
- [ ] Seed database with test data (1 admin, 5 sample adorers with schedules)

---

## Phase 3 — Authentication

### 3.1 User Registration
- [x] Registration form: Full Name, Email Address, Mobile Number, Password, Assigned Adoration Day, Assigned Adoration Time, Privacy Consent checkbox
- [x] Backend: validate all fields, hash password with `password_hash()` (bcrypt), save to `users` table
- [x] Send welcome/confirmation email on successful registration
- [x] Return JWT token on success

### 3.2 User Login
- [x] Login form: Email + Password
- [x] Backend: verify credentials, return signed JWT
- [x] Store token in `localStorage` (or `httpOnly` cookie)
- [x] Protect all authenticated routes on the frontend (redirect to login if no valid token)

### 3.3 Admin Login
- [x] Separate admin login page at `/admin/login`
- [x] Backend: verify credentials against `admins` table, return admin-scoped JWT
- [x] Role-based route guards — adorer routes and admin routes are completely separate

### 3.4 Logout
- [x] Clear token from storage, redirect to login

---

## Phase 4 — Adorer Features

### 4.1 Adorer Dashboard
- [x] Show adorer's name and assigned adoration schedule (day + time)
- [x] Show last check-in timestamp
- [x] Show recent attendance history (date, time, method)
- [x] Quick-access check-in button

### 4.2 Attendance Check-In
- [x] Check-in button on adorer dashboard
- [x] Backend: insert record into `attendance_logs` with current timestamp and `method = 'manual'`
- [x] Show confirmation message after successful check-in
- [x] Prevent duplicate check-ins within a configurable window (e.g. same hour)

### 4.3 QR Code Check-In
**Feasibility: Yes — straightforward to implement.**
- [x] Generate a static QR code image that encodes the URL `https://stanthonyadoration.com/checkin`
- [x] Use a PHP QR code library (e.g. `endroid/qr-code` or `phpqrcode`) to generate the image server-side, or a frontend JS library (e.g. `qrcode` npm package) to render it
- [x] When scanned with any phone camera or QR scanner app, it opens the check-in URL in the browser — no special scanner SDK needed
- [x] If the user is not logged in, redirect to login first, then forward back to `/checkin` after authentication (use a `?redirect=/checkin` query param)
- [x] Backend: log check-in with `method = 'qr'`
- [x] Admin can download/print the QR code image from the admin dashboard for placement at the chapel entrance

### 4.4 Scheduled Adoration Hours
- [x] Display each adorer's assigned day(s) and time slot(s)
- [x] Show attendance history per scheduled hour

### 4.5 Notification Preferences
- [x] Settings page with toggles: Hour Reminders, Chapel Announcements, Attendance-Related Notifications
- [x] Backend: update `email_preferences` table on save
- [x] Preferences can be updated by the adorer at any time

---

## Phase 5 — Admin Features

### 5.1 Admin Dashboard — Overview
- [x] Summary cards: Total Registered Adorers, Active Adorers, Today's Attendance, This Week's Attendance, This Month's Attendance
- [x] Attendance trend chart (daily / weekly / monthly toggle)
- [x] Peak attendance periods visualization

### 5.2 Adorer Management
- [x] Paginated, searchable list of all adorers
- [x] Filter by: active/inactive, assigned day, assigned time slot
- [x] View individual adorer profile: personal details, schedule, full attendance history
- [x] Edit adorer details (schedule, active status)
- [x] Deactivate / reactivate accounts

### 5.3 Attendance Records
- [x] Table view: all check-ins with date, time, adorer name, check-in method (manual/QR)
- [x] Filter by: date range, adorer name, day, time slot, method
- [x] Export to CSV (or Excel)

### 5.4 Missed Attendance Reports
- [x] Auto-detect adorers who did not check in during their assigned scheduled hour
- [x] Report view: missed date, adorer name, scheduled time
- [x] Filter by date range
- [x] Admin can mark individual records as "followed up"

### 5.5 Attendance Analytics
- [x] Daily, weekly, monthly attendance counts
- [x] Total registered vs. active adorers
- [x] Attendance trends over time (chart)
- [x] Peak attendance periods (heatmap or bar chart by day/time)

### 5.6 Export Reports
- [x] Export attendance records to CSV or Excel
- [x] Export missed attendance report to CSV
- [x] Export registered adorers list to CSV

### 5.7 Bulk Email Messaging
- [x] Compose form: Subject, Message Body
- [x] Recipient group selector:
  - All Adorers
  - Active Adorers
  - Inactive Adorers
  - Adorers who missed their scheduled hour
- [x] Preview recipient count before sending
- [x] Backend: send via PHPMailer + SMTP, log in `email_logs`
- [x] View history of all sent announcements

### 5.8 Scheduled Adoration Hours Management
- [x] View all time slots (day + time) with a list of assigned adorers per slot
- [x] Assign or reassign adorers to time slots
- [x] View which slots have no assigned adorer (gaps in coverage)

### 5.9 QR Code Management
- [x] Display the current chapel check-in QR code in the admin dashboard
- [x] Provide a download button for the QR code image (PNG or PDF, print-ready)

---

## Phase 6 — Automated Emails & Reminders

All jobs run as PHP cron scripts called from GoDaddy cPanel cron scheduler.

### 6.1 Pre-Adoration Hour Reminder
- [x] Cron runs every hour (or every 15 min for finer granularity)
- [x] Query adorers with `hour_reminders = true` whose scheduled hour starts within the next 60 minutes
- [x] Send reminder email via PHPMailer + SMTP
- [x] Log sent reminders to prevent duplicate sends in the same window

### 6.2 Missed Attendance Notification
- [x] Cron runs once daily (e.g. end of day)
- [x] Detect adorers who missed their scheduled hour that day and have `attendance_notifications = true`
- [x] Send a gentle missed-attendance notification email

### 6.3 Email Templates
- [x] Welcome / registration confirmation
- [x] Hour reminder
- [x] Missed attendance notification
- [x] Chapel announcement (used by bulk email)
- [x] All templates should be HTML emails with the parish name and branding

---

## Phase 7 — PWA Configuration

### 7.1 Core PWA Setup
- [ ] Add `manifest.json`: app name "St. Anthony Adoration," icons (192px, 512px), theme color, `display: standalone`
- [ ] Register a Service Worker for offline shell caching
- [ ] App loads and shows a meaningful screen even with no internet connection

### 7.2 "Add to Home Screen" Button (Pseudo-App Install)
The install experience differs by platform.

- [ ] **Detect install state:** on load, check `window.matchMedia('(display-mode: standalone)').matches` and iOS `navigator.standalone`; if already installed, hide the prompt entirely
- [ ] **Android (Chrome / Edge / Samsung Internet):** capture the `beforeinstallprompt` event and show a custom "Add to Home Screen" button; on click, call `deferredPrompt.prompt()` and handle the user's choice — one-tap native install
  ```js
  let deferredPrompt;

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    showInstallButton();
  });

  installButton.addEventListener('click', async () => {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    deferredPrompt = null;
    hideInstallButton();
  });
  ```
- [ ] **iPhone (iOS Safari):** Apple does not support `beforeinstallprompt` — there is no programmatic one-tap install on iOS. Detect iOS Safari via user agent and show a short instructional modal: "Tap the Share icon (□↑), then tap 'Add to Home Screen'" with an icon illustration
- [ ] **Dismiss-and-remember:** if the user dismisses the prompt or instructions, store the dismissal in `localStorage` and don't show again for 7 days
- [ ] Place the install prompt on the login/registration page and in account settings so it's easy to find

**Summary:**
- Android: ✅ True one-tap install
- iPhone: ⚠️ Manual 2-step process (Share → Add to Home Screen) with in-app instructions
- Once installed on either platform: full-screen, no browser chrome, icon on home screen — behaves like a native app

---

## Phase 8 — Security & Privacy

- [ ] All passwords hashed with `password_hash()` — never stored in plain text
- [ ] HTTPS enforced sitewide (HTTP redirects to HTTPS)
- [ ] All API inputs validated and sanitized server-side
- [ ] SQL injection prevention: PDO prepared statements throughout
- [ ] CSRF protection on all state-changing endpoints
- [ ] JWT tokens with expiry; invalid/expired tokens return 401
- [ ] Privacy consent checkbox recorded with timestamp at registration
- [ ] Data handling in accordance with applicable Canadian privacy requirements (PIPEDA)
- [ ] Admin routes fully protected — unauthenticated access redirects to admin login
- [ ] Rate limiting on login endpoint to prevent brute-force attacks

---

## Phase 9 — Responsive Design & UI Polish

This is a **mobile-first application** — the primary experience is designed for phones. Desktop is a first-class citizen too, not an afterthought, but every layout, tap target, font size, and interaction is designed for a phone screen first and then scaled up gracefully.

### 9.1 Mobile-First Principles
- [ ] All layouts built mobile-first using Tailwind CSS (`sm:`, `md:`, `lg:` breakpoints scale up from mobile base)
- [ ] Touch targets minimum 44×44px (buttons, links, toggles) — no tiny tap targets
- [ ] Forms use large inputs, full-width on mobile, appropriately constrained on desktop
- [ ] Bottom-anchored primary action buttons on mobile (e.g. check-in button within thumb reach)
- [ ] No hover-only interactions — everything accessible by tap
- [ ] Font sizes readable without zooming on a 375px screen (minimum 16px for body text)
- [ ] Avoid horizontal scrolling at any viewport width

### 9.2 Desktop Enhancements
- [ ] On tablet and desktop, layouts expand into multi-column views where appropriate (e.g. dashboard cards in a grid, admin tables with more visible columns)
- [ ] Sidebar navigation on desktop; bottom tab bar or hamburger menu on mobile
- [ ] Admin dashboard in particular should shine on desktop — wider tables, visible charts, side-by-side panels
- [ ] Adequate whitespace and max-width containers so the app doesn't stretch uncomfortably on large screens (e.g. `max-w-screen-xl mx-auto`)

### 9.3 General UI Quality
- [ ] Consistent design language throughout: spacing, color palette, typography, button styles
- [ ] Parish branding: St. Anthony of Padua name and colors used consistently
- [ ] Loading states and spinners for all async API calls
- [ ] Error messages displayed inline — never just in the browser console
- [ ] Empty states with helpful messaging for tables and reports with no data
- [ ] Accessible forms: proper `<label>` elements, ARIA attributes, keyboard navigability

### 9.4 Device Testing Targets
- [ ] Android phone — Chrome (primary target)
- [ ] iPhone — Safari (primary target)
- [ ] Tablet — portrait and landscape
- [ ] Desktop — Windows (Chrome, Edge)
- [ ] Desktop — Mac (Chrome, Safari)

---

## Phase 10 — Testing & QA

### 10.1 Functional Testing
- [ ] Full registration → login → check-in flow (manual)
- [ ] QR code check-in end-to-end (scan → login redirect → check-in → confirmation)
- [ ] Admin dashboard data accuracy (counts match raw DB records)
- [ ] Missed attendance report logic (schedule a user, don't check in, verify report)
- [ ] Bulk email sending and logging
- [ ] Cron reminder email delivery
- [ ] Notification preference toggles respected by cron jobs

### 10.2 Edge Cases
- [ ] Adorer checks in more than once in the same hour
- [ ] Adorer has no assigned schedule but tries to check in
- [ ] QR code scanned by unauthenticated user (should prompt login, then redirect back to check-in)
- [ ] Admin sends bulk email to a group with 0 members (should show warning, not send)
- [ ] Adorer tries to register with an already-used email address

### 10.3 Cross-Device Testing
- [ ] Android phone — Chrome (primary target)
- [ ] iPhone — Safari (primary target)
- [ ] Tablet — portrait and landscape
- [ ] Desktop — Windows (Chrome, Edge)
- [ ] Desktop — Mac (Chrome, Safari)
- [ ] Verify no horizontal scroll at any breakpoint
- [ ] Verify touch targets are comfortably tappable on all mobile devices

---

## Phase 11 — Go-Live Deployment & Handover

Because staging and production share the same database, go-live is straightforward — there is no data migration, no DB export/import, and no seed data step. It is essentially a file copy and a URL swap.

### 11.1 Pre-Launch Cleanup
- [ ] Final client sign-off on staging
- [ ] Delete all test accounts from the shared database (e.g. any `test@...` or dummy adorer records created during development)
- [ ] Verify the admin account(s) intended for production are in place and passwords are set

### 11.2 Frontend Build & Deploy
- [ ] Update the production `.env`: set `VITE_API_BASE_URL=https://stanthonyadoration.com/api`
- [ ] Run `npm run build` to generate the production `/dist`
- [ ] Copy `/dist` contents to GoDaddy `public_html` root (not the `/staging` subfolder used during development)

### 11.3 Backend Deploy
- [ ] Copy PHP backend files from the `/staging` folder to the production root path
- [ ] Update the production `.env` on the server: set `APP_ENV=production` and `APP_BASE_URL=https://stanthonyadoration.com`
- [ ] Verify `.htaccess` routing rules work correctly at the root domain

### 11.4 Cron Jobs
- [ ] Update cron job paths in GoDaddy cPanel from staging script paths to production script paths
- [ ] Run each cron script manually once to confirm it executes without errors on production

### 11.5 QR Code
- [ ] Regenerate the QR code pointing to `https://stanthonyadoration.com/checkin` (production URL)
- [ ] Download print-ready PNG/PDF from the admin dashboard
- [ ] Replace any staging QR codes posted at the chapel entrance

### 11.6 Final Smoke Tests on Production
- [ ] Register a new adorer and confirm welcome email arrives
- [ ] Log in as adorer and perform a manual check-in
- [ ] Scan the new production QR code and confirm check-in flow
- [ ] Log in as admin and verify dashboard shows live data
- [ ] Send a test bulk email from the admin panel
- [ ] Confirm cron reminder fires correctly (or manually trigger and verify)

### 11.7 Handover
- [ ] Provide client with admin login credentials
- [ ] Provide production QR code image file for chapel entrance
- [ ] Provide brief usage guide (PDF or Google Doc)
- [ ] Staging at `staging.stanthonyadoration.com` can remain live for reference or be taken down — client's preference

---

## Milestones Summary

| Phase | Description | Deliverable |
|-------|-------------|-------------|
| 1–2 | Setup, Staging env, DB Schema & Migrations | Working dev environment at staging.stanthonyadoration.com |
| 3 | Authentication | Registration, Login, Admin Login |
| 4 | Adorer Features | Check-in (manual + QR), dashboard, preferences |
| 5 | Admin Features | Full admin panel, reports, QR download |
| 6 | Automated Emails | Cron reminders + notifications |
| 7 | PWA + Install Button | Installable app on Android & iPhone |
| 8–9 | Security + UI Polish | Production-ready, fully responsive |
| 10 | QA & Testing | Test report, edge cases covered |
| 11 | Go-Live Deployment | Promoted from staging to stanthonyadoration.com |

---

## Notes for Devin AI

- **Mobile-first UI:** design and build every screen for a phone screen first (375px base), then scale up with Tailwind breakpoints for tablet and desktop — desktop should look great, not just functional; admin views especially benefit from wider layouts and visible data density on larger screens
- Use **React Router v6** for client-side routing
- Use **Axios** for all API calls; set base URL from `VITE_API_BASE_URL` in `.env`
- **Staging:** all development targets `https://staging.stanthonyadoration.com` (served from the `/staging` folder on the server) — set `VITE_API_BASE_URL=https://staging.stanthonyadoration.com/api` in the staging `.env`; switch to the root domain only at go-live (Phase 11)
- **Shared database:** staging and production share one MySQL database — use clearly named test accounts during development and clean them up before go-live; do not use DB prefixes or separate schemas
- Keep adorer and admin sections in separate route trees: `/app/*` for adorers, `/admin/*` for admins
- PHP API must return consistent JSON on every response: `{ "success": true, "data": ... }` or `{ "success": false, "error": "..." }`
- Use **PDO with prepared statements** for all DB queries — no raw string interpolation in SQL
- Use **PHPMailer** for all outgoing email
- All cron scripts live in `/backend/cron/` and are called directly via GoDaddy cPanel cron scheduler (e.g. `php /home/username/backend/cron/send_reminders.php`)
- QR code points to `https://stanthonyadoration.com/checkin` — generate once, no dynamic per-user QR codes needed
- The "Add to Home Screen" install button uses `beforeinstallprompt` on Android and a manual instruction modal on iOS — see Phase 7.2 for full implementation detail
- Do not use any external AI/LLM API — all reports and notifications are rule-based SQL queries and PHP cron jobs
