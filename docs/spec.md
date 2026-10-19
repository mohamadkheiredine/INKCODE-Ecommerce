# Agreed specification

The decisions from the planning (grilling) session on 19 October 2026. Terms in **bold** are defined in [GLOSSARY.md](../GLOSSARY.md). [requirements.md](requirements.md) remains the PDF checklist; this file says *how* each requirement will behave.

## Delivery

- **Deadline:** submit by **Wednesday 21 October 2026** (confirmed with INKCODE).
- **Depth:** every module is built deep and complete: API, screens, tests, both languages, both themes, all screen sizes. Within each phase, the paths used by the six demo scenarios are built first.
- **Order of work:** the PDF's phases. 1) auth, app shell, design system; 2) catalog, customers, inventory, orders; 3) payments, shipping, coupons, returns; 4) dashboard, reports, roles, settings, notifications, audit log; 5) seed data, end-to-end tests, docs.
- **Work items:** tickets created with `/to-tickets` as GitHub Issues; commits close them (`closes #n`).
- **Quality checkpoints:** three, at the end of 19 Oct, the end of 20 Oct, and before submission on 21 Oct. At each one the user runs `/improve-codebase-architecture`, the agent runs the `clean-code` skill and `/security-review` (plus `composer audit` / `npm audit`), then the user runs `/retro`. The agent announces each one with "⏸ CHECKPOINT".
- **Running it:** reviewers run the whole stack locally with one Docker Compose command (MySQL + API + built SPA); no paid hosting. The README has a step-by-step "How to run" section and a "Demo accounts" table.
- **Walkthrough:** at the very end, the agent generates screenshots of every main screen (light/dark, English/Arabic) and a 3-minute recording script; the user records the video.

## Users, roles and access

- Only **Users** (staff) sign in, with email and password via Sanctum SPA cookies. **Customers** never sign in. There is no storefront: Users create **Orders**.
- Seven fixed **Roles** (Admin, Operations, Inventory, Sales, Support, Finance, Viewer), one per User. Permissions are fixed in code and seeders, enforced by the API on every request, and shown in a read-only "Roles & permissions" matrix.
- The Admin creates Users (role + temporary password), changes roles, and deactivates Users rather than deleting them (audit history stays). Users change their own password in their profile. No "forgot password" email.
- Demo mode: one demo account per Role (`admin@inkcode.test` … `viewer@inkcode.test`, password `password`, local-only). The login page shows quick-login buttons only in demo mode.

## Store settings

- **Store currency:** one currency, USD by default; code and symbol editable; no exchange rates.
- **VAT rate:** one store-wide rate, 5% by default. Prices are entered without VAT; each Product is taxable or not.
- Also in Settings: store profile (name, logo, contact details, address), **Deduction stage** (default Shipped), **Return window** (default 14 days), default **Low-stock threshold** (5), default language and theme.

## Catalog

- Product data (names, descriptions) is entered once, in one language; the interface around it is fully bilingual.
- Product fields: name, slug, description, category, brand, images (several; the first is the main image; uploads stored on the server), base price, SEO title and description, tags, **Product status** (Draft / Active / Archived), taxable flag.
- **Variant** builder: pick values from shared **Attributes** (Size, Color, Material…). All combinations are generated (3 colors × 4 sizes = 12 Variants). Each Variant has an auto-generated, editable, unique SKU (`TSHIRT-RED-M`), its own price (pre-filled from the base price), **Average cost**, **Low-stock threshold**, and stock per **Warehouse**.
- Products that appear on any Order can only be Archived, never deleted.
- Categories and Brands: full create / edit / list / filter.
- Lists: server-side search, filters, sorting, pagination and bulk actions (change status, archive, delete where allowed).

## Inventory

- Per Variant per Warehouse: **On-hand**, **Reserved**, **Available**. At least 3 Warehouses.
- Every change is a **Stock movement**; balances always equal the sum of movements.
- Order rules: **Confirm** reserves; Cancel (full or partial) before the **Deduction stage** releases; reaching the Deduction stage moves the reservation out of On-hand. Never deducted twice.
- Confirming is refused with a clear message when the **Fulfilling warehouse** lacks Available stock. Concurrent confirmations for the last units: exactly the available quantity succeeds, the rest are refused (row locks inside transactions).
- **Adjustments** require a reason: count correction, damaged, lost, found.
- **Transfers** run in two steps: Send (leaves the source, in transit), then Receive (arrives at the destination); they can be cancelled before sending.
- **Receipts**: warehouse, Variants, quantities, unit cost, supplier reference. Each Receipt re-weights the Variant's Average cost. No suppliers or purchase-order module.
- **Stock value** = On-hand × Average cost.
- A low-stock alert fires when a Variant's Available stock in a Warehouse drops to its threshold or below.

## Orders

- Created by a User for a Customer. Each Order has one **Fulfilling warehouse**, pre-selected as one with enough Available stock. Order numbers look like `ORD-2026-000123`.
- **Order status** flow: Pending → Confirmed → Preparing → Ready for shipping → Shipped → Delivered, one step forward at a time. Cancel is allowed any time before Shipped. After Shipped, only a Return is possible. The API refuses skipped or backward moves (409).
- Order, Payment and Shipment statuses are separate.
- **Locked order**: from Preparing onward, items, quantities, addresses and discount cannot change; notes, tracking and status moves are still allowed. Before that, editing is limited to Roles with permission.
- **Partial cancellation**: cancel some lines or quantities before they ship. **Partial fulfillment**: several Shipments.
- Lines keep a snapshot of name, SKU, attributes, unit price, discount share, VAT rate and VAT amount, so later Product edits never change Orders.
- Money math: VAT is calculated per line on the price after its share of the Coupon discount, rounded to cents per line. Shipping is not taxed. Total = Σ(discounted line + its VAT) + shipping.
- Each Order has an **Assignee** (an active Admin, Operations or Sales User), a timeline (status history, notes, payments, shipments, returns), and a browser-printed **Invoice** page.
- If an Order is cancelled (fully or partially) or reduced after money was received, the dialog shows "Refund needed: $X" and records the **Refund** in the same step.

## Shipping

- **Shipping zones** (groups of countries or cities), each with flat-price **Shipping methods** (for example Standard, Express).
- **Carriers** (for example Aramex, DHL).
- **Shipments**: Carrier, tracking number, the lines and quantities they contain, and a status history. Several Shipments per Order are allowed.

## Payments

- **Payment methods**: Cash on delivery, Manual (bank transfer or cash), Card (a mock gateway that approves or declines; no card data is stored).
- Every payment and Refund is a row in the **Payment ledger**. **Payment status** (Unpaid / Partially paid / Paid / Partially refunded / Refunded) is derived from the ledger.
- Payment, refund and stock-changing requests take an `Idempotency-Key`; a repeated request is recorded once.
- Refunds can never exceed the **Captured amount**. Reconciliation view: Order totals against ledger totals.

## Coupons

- Percentage or fixed amount, start and end dates, a total usage limit, a per-Customer limit, a minimum order amount, and optional eligible categories or products.
- At most one **Coupon** per Order: this is the stacking rule.

## Returns and refunds

- Flow: Requested (choose lines and quantities from a Delivered Order, with a reason: damaged, wrong item, not as described, changed mind, other) → Approved or Rejected → Received → **Restock decision** per item (back to On-hand, or **Write-off**) → Refund.
- Requests are only possible within the **Return window**. Stock changes only at the Restock decision, never automatically.
- The suggested refund equals what the Customer actually paid for the returned items (price − their share of the Coupon + their VAT). Staff may lower it and may tick "also refund shipping". The total can never exceed Captured amount − earlier Refunds.

## Customers

- List, profile, several addresses (default billing and shipping), Order history, notes, **Tags**, and **Lifetime value**.
- Automatic **Segments**: New, VIP (Lifetime value ≥ $1,000), At risk (no Order for 90 days), Regular. Filters by activity, value, Segment and Tag.

## Dashboard and reports

- Only **Counted orders** are included, dated by the day the Order was placed.
- KPIs: **Revenue**, **Net sales**, Orders, pending fulfillment, **Average order value**, Customers, cancellations, Returns, low-stock items. No conversion rate, because visits are not tracked. Date-range picker with comparison to the previous period, Revenue chart, Order-status breakdown, top Products, recent Orders, alerts.
- Six reports: Sales (day/week/month), Products, Categories, Customers (top spenders, new vs returning), Returns (by reason, return rate), Inventory (levels, low stock, Stock value). Each has date filters and a previous-period comparison, and exports to CSV and Excel (.xlsx).
- Example to keep the maths consistent: items $100, Coupon −$10, VAT $4.50, shipping $5, $20 refunded → Revenue $99.50, Net sales $70.

## Notifications and audit log

- In-app bell only (no email), refreshed every 30 seconds, with mark-as-read. Events: low stock, new Return request, declined card payment, Order assigned to you.
- The audit log records every create, update, delete and status change (who, when, before/after), and is searchable by User, record type, action and date.

## App shell and design

- Visual direction: the PDF's reference, rebuilt independently. Indigo/violet accent; white and soft-lavender surfaces; deep-navy dark mode; rounded cards; KPI cards with colored icon chips; our own gradient logo mark and the name "Inkcode Commerce".
- Fonts: Inter (English) and IBM Plex Sans Arabic (Arabic), self-hosted.
- Sidebar groups, each item shown only when the Role allows it: Dashboard · Sales (Orders with count badge, Returns, Payments, Customers) · Catalog (Products, Categories, Brands) · Operations (Inventory, Shipping) · Promotions (Coupons) · Insights (Reports) · Admin (Users & Roles, Audit log, Settings). No "Marketing" item.
- Header: global search (Ctrl+K) across Orders, Products and Customers; a "+" quick-actions menu (New order, New product, New customer, Receive stock); breadcrumbs; theme and language switches; notifications; user menu.
- Defaults: English, light theme, remembered per User. Switching language flips the whole layout instantly. Western digits in both languages, with Arabic month names and labels.
- Motion: subtle GSAP (fade-in, count-up KPIs, charts drawing in, sidebar collapse) and Lenis smooth scrolling, all off under `prefers-reduced-motion`.
- Every data screen has a loading skeleton, an empty state with an action, an error state with retry, form validation, and toasts.

## Demo data and demo tools

- Seeders: 200 Products (with Variant combinations), 1,000 Orders across the last 12 months with mixed statuses, 300 Customers, 3 Warehouses, Payments, Refunds, Returns, Stock movements, and Users for every Role. Internally consistent: stock equals the sum of movements, and payment statuses match the ledgers.
- Demo product images: generated locally (colored tile + initials), with no internet or copyrighted photos.
- `php artisan demo:race`: fires 20 simultaneous confirmations at a Variant with 5 Available and prints the outcome (5 succeed, 15 refused, stock correct).

## Defaults assumed without a question (change them if needed)

- Categories have two levels (parent → child).
- A new Customer can be created from inside the Order form.
- The mock card gateway decides approve or decline through a test option; it never asks for or stores card numbers.
