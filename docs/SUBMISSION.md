# Submission checklist

Everything INKCODE asks for at hand-in. Go through it top to bottom before sending the email. Items marked _(auto)_ come out of the codebase; the rest need you.

## 1. What to send

- [ ] **Repository link**: https://github.com/mohamadkheiredine/INKCODE-Ecommerce (public, `frontend/` + `backend/`)
- [ ] **Setup guide**: the root `README.md` (exact install steps, supported versions, example env vars)
- [ ] **Demo access details** for the local/test environment: URLs and the seeded demo logins for each role (local-only credentials, never real ones)
- [ ] **API documentation**: `docs/api/openapi.json` (OpenAPI) and the Postman collection export, plus the live docs URL `/docs/api` when running locally
- [ ] **Walkthrough**: a short screen recording (or screenshots) covering the main screens and all six required scenarios
- [ ] **Engineering note**: `docs/engineering-note.md` (architecture decisions, AI tools used, known limitations, what you implemented yourself or reviewed and corrected)

## 2. What must be inside the repository

- [ ] Descriptive commit history (one feature per series of commits, conventional messages; no single giant commit)
- [ ] README with exact installation steps, supported versions, example environment variables, no real secrets committed
- [ ] MySQL migrations and seeders (200 products, 1,000 orders, 300 customers, 3 warehouses, variants, mixed statuses, payments, refunds, stock movements, staff roles)
- [ ] API documentation (OpenAPI and/or Postman collection)
- [ ] Screenshots or walkthrough link
- [ ] Production build commands and deployment notes
- [ ] **Executed test results** (paste the output of `composer test`, `npm test`, `npm run test:e2e` into `docs/test-results.md`)
- [ ] A clearly labeled list of incomplete items and assumptions (README "Status" section)

## 3. Acceptance checklist (INKCODE will verify)

- [ ] Every primary navigation entry opens a working module, no placeholders
- [ ] Create/read/update workflows persist through the API into MySQL
- [ ] Product variants, stock reservation, fulfillment, returns and refunds behave correctly
- [ ] KPIs and reports come from stored data, not hard-coded numbers
- [ ] Roles are enforced on the backend, not only hidden in the UI
- [ ] Frontend production build and backend tests both run successfully
- [ ] Responsive layouts, light and dark themes, Arabic RTL and English LTR

## 4. The six live scenarios to rehearse (and record)

1. Product with 3 colors × 4 sizes: save, edit, filter, check SKU/stock per variant
2. Simultaneous orders on limited stock: no overselling, no double deduction
3. Order lifecycle: create → confirm → prepare → ship → deliver; invalid jumps rejected
4. Return one line item: no restock until received + approved; refund ≤ captured amount
5. Discount + tax + shipping: order, payments and sales report reconcile
6. Least-privilege user blocked by the API; loading, empty, validation and error states

## 5. Final checks before sending

- [ ] Fresh clone on a clean machine follows the README without extra steps
- [ ] `docker compose up -d`, `composer setup`, `npm ci && npm run build` all succeed
- [ ] `composer check` and `npm run check` are green; CI on GitHub is green
- [ ] No `.env`, keys or real credentials in the repo or its history
- [ ] Engineering note is honest about limitations and AI usage
