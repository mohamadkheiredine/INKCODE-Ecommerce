# Requirements tracker

Working checklist for the INKCODE E-Commerce Management Dashboard assessment. Tick an item only when it works end to end (UI → `/api/v1` → MySQL) and has tests. Grouped by the assessment's suggested phases; verify each phase before moving on.

Scoring weights: UI/UX 25% · functional completeness 25% · backend architecture 20% · database integrity 15% · security & testing 10% · deployment & docs 5%.

## Phase 1: Foundation, auth, app shell, design system

- [x] Monorepo: `backend/` (Laravel 13, PHP 8.4) and `frontend/` (React 19 + TS strict + Vite 8)
- [x] MySQL 8.4 via Docker Compose, separate `inkcode_testing` database
- [x] Sanctum installed, API under `/api/v1`, JSON errors for API routes
- [x] Tooling: Pest, Larastan, Pint, Vitest, Playwright, oxlint, Prettier, CI
- [x] Sign in / sign out / current User (Sanctum SPA cookies, CSRF), rate-limited per email and per IP (ticket 01, #6)
- [x] Seven Roles and the permission matrix, seeded idempotently; exactly one Role per User; 403 for every other Role (#6)
- [x] Demo accounts (one per Role) and one-click sign-in, only in demo mode (#6)
- [x] Consistent API error envelope (`message`, `code`, `errors`), localized in English and Arabic (#6)
- [x] Profile: change own password (current password, minimum strength, other sessions signed out), language and theme kept on the User (#7)
- [x] Shared pagination and list query format: `search`, `filter[...]`, `sort`, `page`, `per_page`; `data` + `meta` + `links`; list state kept in the URL (#9)
- [x] Design tokens: light + dark themes, typography for Latin + Arabic (#7)
- [x] i18n (English LTR / Arabic RTL) with runtime switching, all strings translated; Western digits in both (#7)
- [ ] App shell: collapsible sidebar, breadcrumbs, user menu, language/theme kept on the User (#7), notifications bell (#8); global search and quick actions to come
- [x] Shared states: loading skeletons, empty states with actions, error states, toasts, form validation (#7)
- [x] Responsive: desktop / tablet / mobile navigation (sidebar, icon rail, drawer) (#7)
- [x] Motion: GSAP (`useGSAP`) + Lenis on the GSAP ticker, respecting `prefers-reduced-motion` (#7)

## Phase 2: Catalog, customers, inventory, orders

- [ ] Products CRUD: images, categories, brands, SKU, price, cost, SEO fields, tags, status (Categories in two levels and Brands with logos are done, #9)
- [ ] Variant editor (size / color / other attributes), each variant with its own SKU, price, stock
- [ ] Server-side filtering, sorting, pagination, bulk actions (TanStack Table)
- [ ] Customers: list, profile, addresses, order history, lifetime value, notes, segments, activity/value filters
- [ ] Warehouses (3+): on-hand, reserved, available per variant per warehouse
- [ ] Stock movements, manual adjustments, transfers, receiving, low-stock alerts, valuation
- [ ] Reserve on confirmation, release on eligible cancellation, deduct at the configured fulfillment stage, never twice
- [ ] Concurrency safe: no overselling under simultaneous orders (row locks + transactions)
- [ ] Orders list: customer, total, payment status, fulfillment status, shipping, date, assigned employee
- [ ] Order detail: line items, variant details, discounts, taxes, shipping, billing/delivery addresses, timeline, notes, tracking, printable invoice
- [ ] Status flow enforced: Pending → Confirmed → Preparing → Ready for Shipping → Shipped → Delivered, plus valid cancellation paths
- [ ] Order, payment and shipment statuses kept separate
- [ ] Orders locked against unauthorized edits once preparation starts
- [ ] Partial fulfillment, partial cancellation
- [ ] Order items store historical snapshots (name, SKU, price, attributes)

## Phase 3: Payments, shipping, promotions, returns

- [ ] Payment transaction ledger, manual payment, cash on delivery, reconciliation
- [ ] Mock payment gateway adapter (no live gateway) with idempotency keys
- [ ] Coupons: percentage / fixed, validity dates, usage limits, eligible items, stacking rules
- [ ] Shipping zones, carriers, rates, tracking references, shipment history, partial shipments
- [ ] Returns: request → approval → physical receipt → restock decision → eligible refund (no automatic restock)
- [ ] Refunds can never exceed the captured amount; partial refunds supported

## Phase 4: Reporting, permissions, notifications, settings

- [ ] Executive dashboard: revenue, net sales, orders, pending fulfillment, AOV, customers, cancellations, returns, low stock
- [ ] Time ranges + period comparison, revenue chart, order-status breakdown, top products, recent orders, alerts
- [ ] Conversion rate shown only if visit/session tracking exists (otherwise omitted)
- [ ] Reports: sales, customers, products, categories, returns, inventory, with date filters, comparisons, CSV/Excel export
- [ ] Every KPI and report computed from database records, never hard-coded
- [ ] Roles: Admin, Operations, Inventory, Sales, Support, Finance, Viewer, with granular permissions enforced by Policies
- [x] In-app notifications: a bell with the unread count, newest first, mark one or all as read, link to the record, 30 s polling paused in hidden tabs, messages in English and Arabic (#8). Events arrive with their modules: low stock (#12), Order assigned (#17), card payment declined (#19), Return requested (#21)
- [ ] Searchable audit log: every change is recorded with User, action, record and before/after values (#9); the log viewer comes with #26
- [x] Settings: store profile and logo, Store currency (code + symbol), VAT rate, Deduction stage, Return window, default Low-stock threshold, default language and theme for new Users; typed, cached, audited, Admin-only to change, public store profile for every User (#15)

## Phase 5: Testing, polish, deployment

- [ ] Seeders: 200 products, 1,000 orders, 300 customers, 3 warehouses, variants, mixed statuses, payments, refunds, stock movements, staff roles, all internally consistent
- [ ] Laravel tests: auth, authorization, variants, inventory, order workflow, returns, refunds, calculations
- [ ] Vitest component/unit tests
- [ ] Playwright end-to-end tests for the required demo scenarios
- [ ] OpenAPI docs exported (`docs/api/openapi.json`) + Postman collection
- [ ] Production build + deployment notes
- [ ] Screenshots / walkthrough recording

## Required demo scenarios

| # | Scenario | Must show |
|---|---|---|
| 1 | Product & variants | Create a product with 3 colors × 4 sizes; save, edit, filter; SKU and stock per variant |
| 2 | Inventory concurrency | Simultaneous orders on limited stock: no overselling, no double deduction, consistent reservations |
| 3 | Order lifecycle | Create → confirm → prepare → ship → deliver; invalid transitions and unauthorized edits rejected |
| 4 | Return & partial refund | Return one line item; no restock before receipt + approved restock; refund ≤ captured amount |
| 5 | Financial consistency | Discount + tax + shipping; order, payments and sales report reconcile |
| 6 | Permissions & resilience | Least-privilege role denied by the API; loading, empty, validation and API-error states |
