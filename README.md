# St. Anthony Adoration — Chapel Registration & Attendance PWA

A Progressive Web App for managing Adoration Chapel registration and attendance at St. Anthony of Padua Parish.

## Tech Stack

- **Frontend:** React + Vite + TypeScript + Tailwind CSS v4 + shadcn/ui components
- **Backend:** PHP + MySQL (zero Composer dependencies, works on GoDaddy shared hosting)
- **Hosting:** GoDaddy (staging at `staging.stanthonyadoration.com`)

## Project Structure

```
sap/
├── frontend/          # React + Vite + Tailwind + shadcn/ui
│   ├── src/
│   │   ├── api/       # Axios API client
│   │   ├── components/ui/  # shadcn/ui components (button, input, label, card)
│   │   ├── lib/       # Utilities (cn helper)
│   │   ├── pages/     # Route pages
│   │   ├── App.tsx    # Router setup
│   │   └── index.css  # Tailwind + theme tokens
│   ├── .env           # Local dev API URL
│   └── .env.staging   # Staging API URL
├── backend/           # PHP API (zero Composer deps), deployed to /api
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
