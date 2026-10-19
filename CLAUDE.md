# INKCODE Commerce: E-Commerce Management Dashboard

Technical assessment for INKCODE: a real, working e-commerce admin platform. React SPA (static build) + Laravel REST API + MySQL. Every screen must be connected to real `/api/v1` endpoints and persistent MySQL data; nothing is mocked or hard-coded.

- What to build, tracked by phase: [docs/requirements.md](docs/requirements.md)
- What to hand in: [docs/SUBMISSION.md](docs/SUBMISSION.md). Remind the user of it whenever they talk about submitting.
- Decisions: [docs/adr/](docs/adr/) · Engineering note (required deliverable): [docs/engineering-note.md](docs/engineering-note.md)

INKCODE judges **working, provably correct software over quantity of code**. Depth on the six demo scenarios beats breadth. Scoring: UI/UX 25%, functional completeness 25%, backend architecture 20%, database integrity 15%, security & testing 10%, deployment & docs 5%.

## Non-negotiable rules

- The frontend compiles to static files and deploys separately. No Next.js, Inertia or server-rendered React.
- Every API route is under `/api/v1` (`apiPrefix` in `backend/bootstrap/app.php`). The frontend never touches the database directly.
- No fake numbers: dashboard KPIs and reports are computed from database records. Demo data comes only from seeders.
- Authorization is enforced on the server (Policies/Gates + spatie permissions). Hiding a button is never the only guard.
- Payment and inventory events run inside DB transactions with idempotency protection.
- Money: `DECIMAL` columns, `decimal:2` casts, BCMath / `brick/math` for arithmetic. Never floats.
- Orders store historical item snapshots (name, SKU, attributes, unit price, tax) so later product edits never change past orders or reports.
- Never commit real secrets. Demo credentials are local-only.

## Repository layout

```
backend/    Laravel 13 API (PHP 8.4), Sanctum, Pest, Larastan
frontend/   React 19 + TypeScript strict + Vite 8 SPA, Tailwind 4, shadcn/ui (Radix, RTL)
docker/     MySQL init scripts (creates inkcode_testing)
docs/       requirements, submission checklist, ADRs, engineering note, api/ (OpenAPI export)
docker-compose.yml   MySQL 8.4 for local dev
.github/    CI (backend + frontend jobs), Dependabot
```

## Commands

Start MySQL first: `docker compose up -d mysql` (Docker Desktop must be running).

| Task | Backend (`cd backend`) | Frontend (`cd frontend`) |
|---|---|---|
| First setup | `composer setup` | `npm ci` |
| Dev server | `composer dev` (http://localhost:8000) | `npm run dev` (http://localhost:5173) |
| Tests | `composer test` (Pest, MySQL `inkcode_testing`) | `npm test` (Vitest) · `npm run test:e2e` (Playwright) |
| Lint / format | `composer lint` · `composer format` (Pint) | `npm run lint` (oxlint) · `npm run format` (Prettier) |
| Static analysis / types | `composer analyse` (Larastan level 6) | `npm run typecheck` |
| Everything before a commit | `composer check` | `npm run check` |
| API docs | `/docs/api` (Scramble) · `composer api-docs` exports `docs/api/openapi.json` | |
| Fresh demo data | `php artisan migrate:fresh --seed` | |

Run both `check` commands before every commit. CI runs the same checks on GitHub.

## Library versions: always check current docs

Several dependencies are new majors that postdate most training data. Look up their current docs with the Context7 MCP before writing code against them: **Laravel 13**, **spatie/laravel-permission 8**, **Scramble 0.13**, **Pest 4**, **React 19**, **React Router 8**, **TanStack Query 5**, **TanStack Table 9**, **Vite 8**, **Vitest 5**, **TypeScript 7** (no `baseUrl`; `strict` is on), **Tailwind CSS 4**, **shadcn/ui 4** (`radix-nova` style, `cn` package), **Zod 4**, **i18next 26**, **GSAP 3.15**, **Lenis 1.3**.

## Laravel Boost (AI tooling for the backend)

Laravel Boost is installed as a dev dependency. Its MCP server (`laravel-boost`, registered in the root `.mcp.json`) gives you:

- `search-docs`: version-matched Laravel ecosystem docs. Use it before writing Laravel, Sanctum, Pest or Scramble code.
- `database-schema` / `database-query` (read-only) / `database-connections`: inspect the real MySQL schema and data.
- `application-info`, `last-error`, `read-log-entries`, `browser-logs`, `get-absolute-url`.

Boost's own guidelines live in `backend/CLAUDE.md` (mirrored to `backend/AGENTS.md`). Its skills (`laravel-best-practices`, `testing-best-practices`, `infer-conventions`, `deploying-to-cloud`) are in `backend/.claude/skills/`. Re-run `php artisan boost:install --guidelines --skills --mcp` from `backend/` after upgrading Laravel packages, then copy `backend/CLAUDE.md` over `backend/AGENTS.md`.

Where Boost's generic guidelines conflict with this file, **this file wins**. In particular:

- The per-feature workflow below counts as the user's explicit request for documentation updates.
- Run the full test suite yourself (`composer check`) before committing. Don't just ask the user to run it.
- Boost's notes about Blade and Vite bundling don't apply: the frontend is the separate React SPA.

## Backend conventions (Laravel)

Request flow: route → thin controller → **Form Request** (validation + `authorize()`) → **Policy** → **service / action class** (business rules, `DB::transaction`) → **API Resource** (response shape).

- Controllers hold no business logic and no queries beyond simple lookups. Business rules live in `app/Services` or `app/Actions`, one public entry point each, unit-testable.
- Statuses are PHP backed enums (`app/Enums`). The order state machine (allowed transitions, who may trigger them) lives in one place and is the only way status changes.
- Order, payment and shipment statuses are separate fields with separate transitions.
- Stock: on-hand / reserved / available per variant per warehouse. Reserve on confirm, release on eligible cancel, deduct at the configured fulfillment stage. Lock balance rows with `lockForUpdate()` inside the transaction; every change writes a stock movement row; balances must always equal the sum of movements.
- Idempotency: payment, refund and stock-changing endpoints accept an `Idempotency-Key` header and are safe to retry.
- Returns never restock automatically: request → approve → physical receipt → restock decision → refund. Refund total ≤ captured amount.
- Every mutating action writes an audit log entry (who, what, before/after).
- Lists: server-side filtering, sorting and pagination with one shared query-parameter convention (`filter[...]`, `sort=-created_at`, `page`, `per_page`).
- Errors: one JSON shape for every error (`message`, `errors` for validation, a stable `code`). Correct HTTP status (401, 403, 404, 409 for invalid transitions or conflicts, 422).
- Migrations: foreign keys, unique constraints, indexes on filter/sort columns. Seeders are deterministic and internally consistent.
- Tests: Pest feature tests for every endpoint and business rule (happy path, validation, 403 for each role, edge cases), including concurrency tests for stock. Arch tests live in `tests/Unit/ArchTest.php`.

## Frontend conventions (React)

- Feature folders: `src/features/<module>/{api,components,hooks,routes,schemas}`. Shared UI in `src/components`, the API client and helpers in `src/lib`.
- Data: TanStack Query for all server state (query-key factories per feature). Axios client with `withCredentials` + `withXSRFToken` against `VITE_API_URL`. No `fetch` calls scattered in components.
- Tables: TanStack Table in manual mode (server-side sort/filter/pagination), one reusable data-table component.
- Forms: React Hook Form + Zod schemas (`@hookform/resolvers` 5.x). Show server 422 errors on the matching fields.
- Every query-driven screen handles four states: loading skeleton, empty state with an action, error with retry, data. Mutations give toast feedback (`sonner`).
- Routing: React Router 8, lazy-loaded route modules, route guards from the current user's permissions (the backend is still the real guard).
- i18n: every user-facing string goes through `react-i18next` with `en` and `ar` locales. No hard-coded copy. Format numbers, currency and dates with `Intl` for the active locale.
- RTL: set `dir`/`lang` on `<html>` and wrap the app in shadcn's `DirectionProvider`. Use logical utilities only (`ms-`/`me-`, `ps-`/`pe-`, `start-`/`end-`, `text-start`). Flip directional icons with `rtl:rotate-180`. Pass `dir` to portal content (popover, tooltip, dropdown).
- Theming: light + dark through the shadcn CSS variables in `src/index.css`. No hard-coded colors.
- Do not hand-edit `src/components/ui/*` beyond adapting it. Add components with `npx shadcn@latest add <name>`.

## Design workflow

The UI is 25% of the score: aim for a premium, consistent SaaS admin, not a template.

- Load the `frontend-design` skill before designing or building any screen.
- Components: shadcn/ui first. For richer pieces use **21st.dev** (its MCP server when connected, or the CLI: `npx @21st-dev/cli@latest search "<query>"`, then `get <id>` / `add <user>/<slug>`), or **React Bits** (`npx shadcn@latest add https://reactbits.dev/r/<Component>-TS-TW`). Adapt copied code: i18n strings, logical RTL classes, project tokens.
- Motion: GSAP through `useGSAP` (scoped, auto-cleanup), with `gsap.matchMedia()` and `prefers-reduced-motion`. Lenis is mounted once at the root, driven by `gsap.ticker`. Put `data-lenis-prevent` on inner scroll areas (tables, dialogs, sheets). Keep admin motion subtle (0.2–0.6s, `power2.out`).
- Check every screen in light and dark, English and Arabic, at phone, tablet and desktop widths.

## Per-feature workflow (definition of done)

For each feature or module:

1. **Align.** Restate scope against `docs/requirements.md`. For non-trivial features the user may run `/grill-with-docs`.
2. **Test first.** Use the `tdd` skill (mattpocock) for business rules: write failing Pest feature tests, then implement.
3. **Build.** Backend first (migration → model → policy → service → request → resource → controller → route), then frontend.
4. **Verify.** `composer check`, `npm run check`, a Playwright flow for user journeys, and a real browser pass (themes, languages, widths).
5. **Quality pass.** Do these in this order:
   - `/improve-codebase-architecture`: **user-invoked**. Remind the user to type it.
   - `clean-code` skill: run it after the architecture pass.
   - Security: run `/security-review`, plus `composer audit` and `npm audit`. **Strix is not used**: it needs a paid LLM key, which the user does not have. Do not install or run it.
   - `/retro`: **user-invoked**. Remind the user to type it at the end.
6. **Document.** Update `docs/requirements.md` ticks, README status, `docs/engineering-note.md` (decisions, AI usage, what was reviewed/corrected), new ADRs for hard-to-reverse decisions, and the OpenAPI export.
7. **Commit.** Small, descriptive Conventional Commits (`feat(orders): enforce status transitions`). The commit history is graded. Push to `origin main` when the user asks.

## Git

- Remote: https://github.com/mohamadkheiredine/INKCODE-Ecommerce (`main`). The `gh` CLI is authenticated for this account.
- CI (GitHub Actions) only runs once the account's GitHub billing lock is cleared. Until then, every job fails with "account is locked due to a billing issue" before any step runs. That isn't a code failure, so verify locally with the `check` commands.
- Conventional Commits, one logical change per commit, never `--no-verify`.
- `.env` files stay untracked. Only `.env.example` files are committed.

## Agent skills

mattpocock skills are enabled for this project in `.claude/settings.json`. Issue tracker, triage labels and domain docs get configured by `/setup-matt-pocock-skills`, which only the user can run. It writes `docs/agents/*` and fills in this section.
