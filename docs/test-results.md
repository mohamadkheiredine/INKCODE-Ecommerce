# Executed test results

Last full run: **21 October 2026**, on Windows 11 with PHP 8.4, Node 24 and MySQL 8.4 in Docker, from `main` before submission.

## Backend: `composer check` (Pint, Larastan, Pest)

| Gate | Result |
|---|---|
| Pint (code style) | passed |
| Larastan, level 6 | passed, 0 errors |
| Pest, in parallel on MySQL (`inkcode_testing_test_N`) | **762 passed**, 3,867 assertions, 0 failed (about 110 s) |

The Pest suite covers authentication and session security, the permission matrix for every Role (403 for each forbidden action), Product Variants and SKUs, stock ledger and Warehouses, the Order flow and its invalid transitions (409), Shipments and partial fulfillment, Payments and Refunds (never above the captured amount), Coupons, Returns and Restock decisions, Customer Lifetime value and Segments, dashboard KPIs and reports (which must agree), exports, audit trail, settings and search. Architecture tests are in `tests/Unit/ArchTest.php`. Real multi-process concurrency tests are in `tests/Concurrency`, including simultaneous Orders on limited stock (no overselling, no double deduction) and simultaneous Coupon redemptions.

## Frontend: `npm run check` (TypeScript, oxlint, Prettier, Vitest)

| Gate | Result |
|---|---|
| TypeScript, strict (`tsc -b`) | passed |
| oxlint | passed (2 existing warnings in the Returns card, no errors) |
| Prettier | passed |
| Vitest with Testing Library and MSW | **27 files, 223 passed**, 0 failed |

## Frontend production build: `npm run build`

Type-checked and built by Vite into static files in `frontend/dist`.

## End-to-end: `npm run test:e2e` (Playwright)

The specs in `frontend/e2e` (sign-in, app shell, catalog, Products with Variants, inventory, Customers, Orders, shipping, settings, notifications, profile, Users, audit log) run against the real API and were run with their tickets. They were not re-run on the full-scale demo data on submission day.

## Demo data consistency: `docs/demo-data-checks.sql`

Run against a freshly seeded database (`php artisan migrate:fresh --seed` in demo mode):

| Check | Result |
|---|---|
| Volumes | 200 Products, 1,105 Variants, 1,000 Orders, 300 Customers, 3 active Warehouses, 7 staff Roles |
| Stock balances equal the sum of their movements | 0 mismatches |
| No negative stock, reservations within On-hand | 0 invalid balances |
| Unbroken stock ledger (each movement continues the last) | 0 broken rows |
| Order totals add up (lines, discount, VAT, shipping) | 0 wrong totals |
| Captured and refunded amounts equal the Payment ledger | 0 mismatches |
| Refunded more than captured | 0 Orders |
| Units shipped above units ordered, Delivered Orders with open Shipments | 0 and 0 |
| Order lines without their historical snapshot | 0 |
| Coupons used past their limit | 0 |
| Orders in the future, before their Customer joined, or paid before they were placed | 0, 0 and 0 |

CI on GitHub runs the same backend and frontend gates. It is currently blocked by a billing lock on the GitHub account (jobs stop before any step), so these local runs are the evidence.
