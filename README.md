# INKCODE Commerce: E-Commerce Management Dashboard

A full-stack e-commerce administration platform built for the INKCODE engineering assessment: a React + TypeScript single-page app (static build) talking to a versioned Laravel REST API backed by MySQL.

> **Status:** Phase 1 in progress. Project foundation, tooling and CI are in place; feature modules are being built phase by phase. See [docs/requirements.md](docs/requirements.md) for the live checklist and the [Status](#status) section below for what is incomplete.

## Tech stack

| Layer | Technology |
|---|---|
| Frontend | React 19, TypeScript 7 (strict), Vite 8, Tailwind CSS 4, shadcn/ui 4 (Radix), React Router 8, TanStack Query 5, TanStack Table 9, React Hook Form + Zod 4, Recharts 3, Axios, i18next (English / Arabic RTL), GSAP + Lenis |
| Backend | Laravel 13 (PHP 8.4), Sanctum (SPA cookie auth), API Resources, Form Requests, Policies, spatie/laravel-permission 8, Scramble (OpenAPI) |
| Database | MySQL 8.4 (migrations, foreign keys, indexes, seeders) |
| Quality | Pest 4, Larastan, Pint, Vitest 5, Testing Library, Playwright, oxlint, Prettier, GitHub Actions CI, Dependabot |

## Supported versions

| Tool | Version |
|---|---|
| PHP | 8.4 (8.3+ supported by Laravel 13) with `pdo_mysql`, `mbstring`, `intl`, `bcmath`, `gd`, `zip`, `fileinfo`, `openssl`, `curl` |
| Composer | 2.x |
| Node.js | 24 LTS (npm 11) |
| MySQL | 8.4 (via Docker Compose, or any local MySQL 8+) |
| Docker | Docker Desktop / Engine with Compose v2 (only needed for MySQL) |

## Installation

### 1. Clone and start MySQL

```bash
git clone https://github.com/mohamadkheiredine/INKCODE-Ecommerce.git
cd INKCODE-Ecommerce
docker compose up -d mysql
```

This starts MySQL 8.4 on port 3306 with a local-only `inkcode` / `secret` user, the `inkcode` database and an `inkcode_testing` database for the test suite. To use your own MySQL instead, create both databases and update `backend/.env`.

### 2. Backend API

```bash
cd backend
composer setup        # composer install, copy .env, generate key, migrate + seed
composer dev          # http://localhost:8000
```

`composer setup` copies `.env.example` to `.env`. Key variables:

| Variable | Example | Purpose |
|---|---|---|
| `APP_URL` | `http://localhost:8000` | API base URL |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | MySQL connection |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `inkcode` / `inkcode` / `secret` | Local-only credentials |
| `FRONTEND_URL` | `http://localhost:5173` | Allowed CORS origin (credentials enabled) |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173,127.0.0.1:5173` | Hosts that get cookie-based SPA auth |
| `SESSION_DOMAIN` | `null` locally, `.example.com` in production | Session cookie scope |

### 3. Frontend SPA

```bash
cd frontend
npm ci
cp .env.example .env.local   # VITE_API_URL=http://localhost:8000
npm run dev                  # http://localhost:5173
```

### Demo access

Seeded demo accounts (one per role) will be listed here once the seeders land. They are **local-only** credentials.

## Testing

```bash
# Backend: Pest feature + unit + architecture tests on MySQL (inkcode_testing)
cd backend && composer test

# Backend: full gate (Pint + Larastan + tests)
cd backend && composer check

# Frontend: unit/component tests
cd frontend && npm test

# Frontend: full gate (typecheck + oxlint + Prettier + Vitest)
cd frontend && npm run check

# End-to-end (Playwright, desktop + mobile); needs the backend running
cd frontend && npx playwright install chromium && npm run test:e2e
```

Latest executed results are recorded in `docs/test-results.md` (added before submission).

## API documentation

- Interactive OpenAPI docs: http://localhost:8000/docs/api (Scramble, local environment only)
- Exported spec: `composer api-docs` writes [docs/api/openapi.json](docs/api/) (importable into Postman)
- All endpoints are versioned under `/api/v1`.

## Production build

```bash
# Frontend: static files in frontend/dist, deployable to any static host / CDN
cd frontend && npm ci && npm run build

# Backend
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Deployment notes: the SPA and API must share a registrable domain for Sanctum cookie auth (for example `admin.example.com` + `api.example.com`). See [ADR 0002](docs/adr/0002-sanctum-spa-cookie-authentication.md). Full deployment notes will be added in Phase 5.

## Project documents

- [docs/requirements.md](docs/requirements.md): requirement checklist by phase
- [docs/SUBMISSION.md](docs/SUBMISSION.md): submission checklist
- [docs/engineering-note.md](docs/engineering-note.md): architecture decisions, AI tools used, limitations
- [docs/adr/](docs/adr/): architecture decision records

## Status

### Done

- Repository structure, Docker MySQL, Laravel API foundation (`/api/v1`, Sanctum, permissions, OpenAPI), React SPA foundation (Tailwind, shadcn/ui with RTL, routing/data/form/i18n/animation libraries), test tooling and CI.

### Incomplete / assumptions

- All feature modules (Phases 1–5 in [docs/requirements.md](docs/requirements.md)) are not yet implemented.
- Conversion rate will only be shown if visit/session tracking is implemented; otherwise it is intentionally omitted.
- Payments use a mock gateway adapter. No live payment provider is integrated (per the brief).
