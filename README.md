# St. Anthony Adoration — Chapel Registration & Attendance PWA

A Progressive Web App for managing Adoration Chapel registration and attendance at St. Anthony of Padua Parish.

## Tech Stack

- **Frontend:** React + Vite + TypeScript + Tailwind CSS v4 + shadcn/ui components
- **Backend:** PHP + MySQL (PDO). Composer deps (PHPMailer, firebase/php-jwt)
  are installed in CI and shipped in the deploy artifact, so the shared host
  never needs Composer itself.
- **Hosting:** GoDaddy (staging at `staging.stanthonyadoration.com`)

## Project Structure

```
sap/
├── frontend/          # React + Vite + Tailwind + shadcn/ui
│   ├── src/
│   │   ├── api/       # Axios API client
│   │   ├── components/ui/  # shadcn/ui primitives (button, input, card, switch, ...)
│   │   ├── lib/       # Utilities (cn helper)
│   │   ├── pages/     # Route pages
│   │   ├── App.tsx    # Router setup
│   │   └── index.css  # Tailwind + theme tokens
│   ├── .env           # Local dev API URL
│   └── .env.staging   # Staging API URL
├── backend/           # PHP API, deployed to /api
│   ├── handlers/      # Endpoint handlers
│   ├── lib/           # Database, Response, Router
│   ├── config/        # Env loader
│   ├── cron/          # Cron job scripts (Phase 6)
│   ├── migrations/    # SQL migration + seed files (repo only, never deployed)
│   ├── index.php      # Front controller
│   └── .htaccess      # URL rewriting + CORS + HTTPS
├── docs/              # Setup and deployment guides
├── .github/workflows/ # CI: build + FTPS deploy to staging
└── stanthonyadoration-dev-plan.md  # Full development plan
```

Routes are registered relative to the mount point, so `'/health'` in
`backend/index.php` is served at `https://<host>/api/health`.

## Local Development

### Frontend

```bash
cd frontend
npm install
npm run dev      # Starts Vite dev server at http://localhost:5173
```

The dev server reads `frontend/.env` for `VITE_API_BASE_URL`. By default it points to `http://localhost:8000/api`. To develop against the live staging backend, copy `.env.staging` to `.env`:

```bash
cp frontend/.env.staging frontend/.env
```

### Backend

The PHP backend runs on GoDaddy shared hosting. For local development, serve it
with PHP's built-in server. Because routes resolve relative to the mount point,
serve it from a directory where the API sits at `api/`:

```bash
cd backend
php -S localhost:8000 index.php
```

Then visit `http://localhost:8000/health` to verify it's running. A local
`.env` is required for the database check to pass.

### Database

See `backend/migrations/README.md` for migration instructions. Migrations are
applied manually via phpMyAdmin and are never deployed to the server.

## Deployment

Pushing to `main` triggers `.github/workflows/deploy-staging.yml`, which:

1. Installs dependencies, lints, and builds the frontend
2. Assembles a deploy tree (SPA at root, PHP API under `api/`)
3. Syncs it to `public_html/staging/` over FTPS
4. Polls `/api/health` to confirm the deploy is live

The server's `.env` and the `migrations/` directory are excluded from the sync.

At go-live, change `server-dir` from `./public_html/staging/` to
`./public_html/` and update `VITE_API_BASE_URL` — no code changes needed.

See `docs/godaddy-staging-setup.md` for first-time server setup.

## API Endpoints

All paths are relative to `/api`. Responses follow
`{ "success": true, "data": ... }` or `{ "success": false, "error": ... }`.
Authenticated routes expect `Authorization: Bearer <token>`; adorer and admin
tokens are not interchangeable.

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/health` | — | Service and database status |
| GET | `/schedule/options` | — | Selectable days and hourly slots |
| POST | `/auth/register` | — | Create an adorer account |
| POST | `/auth/login` | — | Adorer sign in |
| GET | `/auth/me` | adorer | Validate token, return adorer |
| POST | `/admin/login` | — | Administrator sign in |
| GET | `/admin/me` | admin | Validate token, return administrator |
| GET | `/adorer/dashboard` | adorer | Hours, last check-in, history, totals |
| POST | `/adorer/checkin` | adorer | Record a check-in (`manual` or `qr`) |
| GET | `/adorer/attendance` | adorer | Paginated history (`page`, `per_page`) |
| GET | `/adorer/preferences` | adorer | Read notification toggles |
| PUT | `/adorer/preferences` | adorer | Replace notification toggles |
| GET | `/admin/stats` | admin | Dashboard overview, trend, peak heatmap |
| GET | `/admin/adorers` | admin | Paginated, searchable adorer roster |
| GET | `/admin/adorer?id=` | admin | One adorer's full profile |
| PUT | `/admin/adorer` | admin | Edit adorer details and schedules |
| GET | `/admin/attendance` | admin | All check-ins, filtered and paginated |
| GET | `/admin/missed` | admin | Missed-hour report (derived, not stored) |
| POST | `/admin/missed/followup` | admin | Mark/clear a missed-hour follow-up |
| GET | `/admin/coverage` | admin | Day/hour grid with gaps highlighted |
| GET | `/admin/export?type=` | admin | CSV export (attendance, adorers, missed) |
| POST | `/admin/email/preview` | admin | Preview recipient count for a group |
| POST | `/admin/email/send` | admin | Send bulk announcement |
| GET | `/admin/email/history` | admin | Paginated log of past announcements |

### Check-in behaviour

- A repeat check-in inside `CHECKIN_WINDOW_MINUTES` (default 60) returns
  **409** with the earlier check-in time rather than creating a duplicate.
- A check-in is linked to an `adoration_schedules` row only when it falls
  inside that scheduled hour. Visits outside it are stored with a null
  `schedule_id`, which keeps off-schedule visits distinguishable and lets
  missed-hour reporting stay accurate.
- Timestamps are written from PHP in `APP_TIMEZONE` (default
  `America/Vancouver`). The database server runs UTC, and the day/hour
  matching above is only correct in parish local time.
