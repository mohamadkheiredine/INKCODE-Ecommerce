# Engineering note

> Required by the assessment. Keep it concise and honest. Update it as each feature lands; finalize before submission.

## Key architectural decisions

- Static React SPA and Laravel API in one repository, deployed separately ([ADR 0001](adr/0001-separate-static-spa-and-laravel-api.md)).
- Sanctum SPA cookie authentication with CSRF instead of bearer tokens in `localStorage` ([ADR 0002](adr/0002-sanctum-spa-cookie-authentication.md)).
- Feature tests run on MySQL so row locks and transactions behave like production ([ADR 0003](adr/0003-feature-tests-run-on-mysql.md)).
- One fulfilling warehouse per order ([ADR 0004](adr/0004-one-fulfilling-warehouse-per-order.md)).
- Weighted average cost for stock valuation ([ADR 0005](adr/0005-weighted-average-cost-valuation.md)).
- Reports count confirmed orders by the date they were placed ([ADR 0006](adr/0006-reports-count-confirmed-orders-by-placed-date.md)).
- The full set of agreed behaviours is in [spec.md](spec.md), and the domain language is in [GLOSSARY.md](../GLOSSARY.md).
- **Permission model:** the `Role` enum is the single source of the Role → permission matrix; a seeder syncs it idempotently into spatie/laravel-permission, and routes and Policies check permissions on every request. "Exactly one Role per User" is enforced in `User::assignRole()` (assigning replaces; two at once is refused), and a User with no Role cannot sign in.
- **One error envelope:** every `/api` error is `{ message, code, errors? }` with a stable `code`. Framework default texts (which can leak routes or model classes) are replaced by translated messages, while deliberate reasons such as a Policy's `Response::deny('…')` pass through. Messages follow the SPA's language (`Accept-Language`).
- **Sign-in protection:** failed attempts are limited per email+IP (5/min) and per IP across all emails (20/min); deactivated Users and Users without a Role are refused only after the password is verified, so nothing is revealed to guessers.
- **Session expiry in the SPA:** any 401 from any request re-checks the current User, and returning to the tab always re-checks it; the route guard then sends the User to sign-in and back to the page they were on.
- **One sidebar registry:** each module registers one entry (page, group, route, icon, permission) in `src/app/navigation.ts`; a pure `visibleNavigation()` filters it by the User's permissions and drops empty groups. A module adds its entry only when it works, so the menu never shows placeholders.
- **Preferences live on the User:** language and theme are saved through `PATCH /auth/me/preferences` and applied whenever a session starts in a browser (at sign-in, and when a remembered session loads), so they follow the User to any device. The account wins over whatever that browser used before.
- **Changing a password ends the User's other sessions:** Sanctum's `AuthenticateSession` compares the password hash on every request, and `logoutOtherDevices()` re-issues only this device's "keep me signed in" cookie. A shared `Password::defaults()` sets the minimum strength (10+ characters, mixed case, a number) for every password a User chooses; attempts are limited to 5 per minute.
- **Formatting:** `Intl` with `en` and `ar-u-nu-latn`, so Arabic shows Arabic month names with Western digits; money arrives as decimal strings and is never turned into a float.
- **Motion:** GSAP entrances through `useGSAP` + `gsap.matchMedia()`; Lenis is mounted once at the root on `gsap.ticker`, paused while a dialog or sheet locks the page, and not mounted at all under reduced motion.
- **Notifications store values, not text:** each notification is a Laravel database notification whose `type` is a stable `NotificationType` enum value (not a class name). It keeps the values its message needs and a link to its record, and the API writes the message in the language of the request. A User who switches to Arabic reads every earlier notification in Arabic too. The SPA caches the list per language.
- **A User's notifications are theirs alone:** every lookup goes through the signed-in User's own notifications, so another User's notification answers 404, not 403, and its existence is never revealed. Every Role has a bell, so no Role is refused. The Pest suite checks each Role is allowed and that cross-User reads and marks fail.
- **Polling without waste:** the bell asks for the latest page every 30 s only while the tab is visible (TanStack Query pauses the interval in hidden tabs) and catches up as soon as the User returns. Closing the bell forgets pages loaded with "Show older", because an infinite query refetches every page it holds.
- **Where events come from:** this ticket builds the mechanism, the bell and a welcome greeting for demo accounts. The four events in the spec arrive with their modules: low stock (#12), Order assigned (#17), card payment declined (#19), Return requested (#21). Each adds one `InAppNotification` subclass, one enum case and its two messages.
- **Read state is not audited:** marking a notification as read changes only the User's own reading state, not a store record, so it will not write audit entries when the audit trail lands (#9). The spec's audit log covers creating, updating, deleting and changing the status of records.
- **One audit trail, written explicitly** ([ADR 0007](adr/0007-explicit-audit-trail-and-one-list-convention.md)): each action calls `AuditTrail` inside its own transaction, so an entry exists exactly when the change does. Updates record only the values that changed, and nothing at all when nothing changed. Hidden attributes such as passwords are never recorded, and entries can't be edited or deleted. Model observers were rejected because they also fire for seeders and can't describe actions that aren't saves.
- **One list convention** (ADR 0007): `search`, `filter[...]`, `sort`/`-sort`, `page` and `per_page`. Each list's Form Request declares what it allows, and an unknown sort or filter gets a 422 that names the allowed ones, so a typo never silently returns everything. The search treats `%` and `_` as plain characters, and the id breaks ties so rows never jump between pages. The SPA keeps the same parameters in its URL.
- **Refusals with their own codes:** `BusinessRuleViolation` carries a stable code (`category_has_children`, `in_use`) through the shared error envelope. Deletes that other rows still reference are refused by restricting foreign keys and come back as a 409.
- **Default addresses guarded twice:** the address actions keep exactly one default billing and one default shipping address per Customer, under a lock on the Customer row, and two generated columns (the Customer's id only on the default row) carry unique indexes, so the database refuses a second default even if a bug slipped through. A default is never simply switched off (422); another address takes it over, and removing a default hands it to the oldest remaining address. The demoted address's change is audited too.
- **Tags belong to Customers:** a `tags` table with a case-insensitive unique name and a `customer_tag` pivot (Products keep their own JSON tags, #10). Tags are created on the fly with `firstOrCreate` (which falls back to insert-or-select), so two Users typing the same new Tag share one row. Tagging is recorded on the Customer's audit trail as a `tags` value, not as separate entries.
- **Notes are append-only:** a note keeps its author (set to null if that User is ever removed, shown as "Former User") and its time. Notes are a record of what was said, so there is no edit or delete.
- **Shared country list:** addresses store an ISO 3166-1 alpha-2 `country_code` validated by `App\Support\Countries` (shared with Shipping, #14); the SPA shows names in the interface language through `Intl.DisplayNames`, so nothing is translated by hand.
- **Bulk demo seeding:** the 300 demo Customers are generated with a fixed Faker seed and inserted in a few bulk queries (about 1 s instead of a minute of row-by-row inserts through Docker on Windows).
- **Shipping quote, city before country (#14):** a zone covers places, each a country or one city in it, stored as `shipping_zone_locations` with a generated `city_key` (trimmed, lower-case, `''` for the whole country). A unique index on `(country_code, city_key)` makes "one place, one zone" a database guarantee (a nullable `city` alone can't be unique in MySQL), and the quote is one indexed lookup: `city_key IN (city, '')` ordered so the city wins. `QuoteShipping` is the single entry point; Order creation (#16) calls it and takes the chosen method through `ShippingQuote::method()`, which refuses an inactive or other-zone method with 422 `shipping_method_unavailable`.
- **Flat prices stay decimal:** `DECIMAL(10,2)` with `decimal:2` casts; the request normalises the typed price with `bcadd`, never through a float. The spec has no free-shipping threshold or per-weight rates, so quoting needs no arithmetic and takes only the address.
- **Shipping is read by Order Roles, changed by shipping.manage:** Policies let anyone with `orders.view` read zones and Carriers (Shipments, #18, also need Carriers), the quote needs `orders.create` or `shipping.manage`, and every write needs `shipping.manage`. A simultaneous duplicate (name or place) caught by a unique index answers 422 on the right field through `DatabaseErrors::refuseDuplicateOnIndexes()`.
- **Settings are one typed row, read through one service:** `store_settings` holds exactly one row (a `CHECK (id = 1)` constraint, created by its migration with every default), one column per setting with its own type, and a `CHECK` on the VAT range. A key-value table was rejected: it can't type or constrain values, and every reader would have to parse strings. Other modules read only `App\Services\Settings` (`vatRate()` as a decimal string for BCMath, `deductionStage()`, `returnWindowDays()`, `lowStockThreshold()`, `currencyCode()`, `current()`). It is cached and memoized per request, and the two actions that change settings drop the cache after their transaction commits. Updates lock the row, so concurrent saves each audit the true before-values.
- **Public versus full settings:** `GET /store` returns only the store name, logo and currency, for every signed-in User. `GET/PUT /settings` and the logo endpoints need `settings.manage` (Admin). The SPA puts the Store currency in a React context at the shell, so `useFormatters().money()` writes every amount with the chosen symbol and always to the cent, whatever the currency's usual decimals.
- **Shared logo handling:** Brand and store logos now share `App\Support\LogoFile` (rules, URL, store-then-swap file replacement) and the SPA's `LogoTile` and `logo-file` rules, instead of copying #9's Brand code.
- _(add: order state machine, idempotency, stock service as they land)_

## AI tools used

- **Claude Code**: scaffolding, implementation, tests, documentation, reviews. Every change was reviewed before commit.
- **Context7 MCP**: current library documentation (Laravel 13, React Router 8, TanStack Table 9, shadcn/ui 4 and others).
- **Laravel Boost** (dev-only): MCP server with version-matched Laravel docs search and read-only database schema/query tools, plus Laravel coding guidelines for the agent.
- **Skills**: `frontend-design`, `clean-code`, mattpocock skills (`tdd`, `improve-codebase-architecture`, `code-review`, `retro`, etc.), `security-review`.
- **21st.dev** (CLI, agent skills and MCP server): UI inspiration and component code, always adapted to the project's tokens, i18n and RTL rules.
- **21st.dev review** (`21st review`): automated UI audit of every changed screen (it caught a removed focus outline on the main landmark).
- **Parallel Claude Code sessions:** later tickets were built at the same time, one session per ticket in its own git worktree. Each worktree runs in a "slot" with its own MySQL databases and dev-server ports (`scripts/use-slot.mjs`), so one session's Pest run or e2e seeding never wipes another's. Branches merge one at a time, after every gate passes on the merged code.
- _(add others as they are used, e.g. React Bits, Playwright MCP)_

## What I implemented personally or reviewed and corrected

_(Fill this in honestly per feature: which logic you designed, which AI output you changed and why, bugs you caught.)_

**Product decisions made by me before any code:** the 48 decisions in [spec.md](spec.md) (scope, Roles, order flow, stock rules, money and reporting definitions, design direction), agreed in a structured planning session.

**Ticket 01 (sign-in): problems caught by review and fixed before merging**

- A two-axis code review (standards + spec) found that sign-out showed "signed out" even when the request failed, which would let the session survive. It now only confirms after the server ends the session, and a failure keeps the User signed in with an error message.
- "Exactly one Role" was only a convention; a User without a Role could sign in and a second Role could be added. Both are now enforced and tested.
- The error renderer swallowed deliberate denial reasons; it now keeps them while still hiding framework internals.
- Sign-in throttling was per email only; an IP-wide limit was added against guessing across many emails.
- Browser verification found a console error from `next-themes` (a `<script>` tag rejected by React 19); it was replaced by a small theme provider plus a pre-paint script.
- MSW 3 renamed `onUnhandledRequest` to `onUnhandledFrame`; the old option was silently ignored, so "fail on unmocked requests" was off. Found by the type checker and fixed.

**Ticket 02 (app shell, design system, profile): problems caught by review and fixed before merging**

- A multi-device Pest test first passed for the wrong reason: Laravel's test client only sends cookies on JSON requests after `withCredentials()`, and the in-memory session carried data from one simulated browser to the next. The `asBrowser()` helper now isolates each browser, and a deliberately broken implementation was used to prove the test fails when it should.
- I suspected sessions could survive a password change if they had made no request since signing in, and added code to close the gap. Reading Laravel 13's `SessionGuard::login()` showed it already records the password hash, so that code was removed again.
- After switching to Arabic, the "Preferences saved" toast still appeared in English, because the callback held the translation function from before the switch. It now reads the current language at the moment it shows.
- A Playwright journey found that success toasts covered the header's user menu; toasts now sit in the bottom corner on the reading side.
- The visual pass found the full sidebar squeezing tablet screens, header separators sitting at the top, and the email in the profile card hugging the wrong edge in Arabic. All three were fixed.
- The two-axis code review found the word "account" in copy and names (the glossary says "User"/"profile"), duplicated avatar and preference-saving code, phones losing the breadcrumb trail, and header switches saving without a confirming toast (story 31). All were fixed.
- The frontend for this ticket was written before its tests, which goes against the TDD workflow. To compensate, the shell tests were checked by breaking the implementation on purpose (removing "apply saved preferences" and "save the language") and confirming the tests fail.

**Ticket 03 (notifications bell): how it was built, and what review caught**

- Built test-first in vertical slices: one failing Pest test per rule (list, own only, language, paging, unread count, mark one, mark all, every Role, guests, demo welcome), then the Vitest + MSW bell tests before the bell existed. The migration, translations and the e2e journeys were written alongside the code, not before it.
- Every important test was checked by breaking the code on purpose: listing or counting everyone's notifications, marking another User's, marking all for everyone, oldest first, overwriting the first read time, no polling, polling in hidden tabs, one cache for both languages, no refresh after marking, and opening without marking. Each broke exactly the test that names the rule. The first attempt at three of these mutants was invalid (the shell ate the class name's backslashes, so the tests failed for the wrong reason); they were redone and checked.
- Two tests first passed or failed for the wrong reason. The polling tests started counting before the first request had arrived. The tab-visibility helper fired an event that didn't bubble to `window`, where TanStack Query listens, and restoring visibility after each test made the previous test's app refetch into the next test. All three were fixed.
- Laravel's architecture preset rejected custom controller actions (`markAsRead`), so marking one and marking all became their own resource controllers.
- The two-axis code review found: `morphs()` adding an index the two composite indexes already cover (removed); "Show older" pages being refetched on every 30 s check (now forgotten when the bell closes, with a test); and the language fallback reimplemented instead of `asLanguage()` (reused). It also flagged that a stored type with no enum case would break the list. Types can only be written from enum cases, so the enum now documents that cases are never removed.
- The visual pass found the Arabic welcome using the Latin product name; it now uses the Arabic one, like the rest of the Arabic interface.
- The security review found no vulnerabilities. `21st review` found nothing, and the free daily 21st component-code quota was used up, so the bell is built from the project's own shadcn primitives.

**Ticket 04 (Categories, Brands, shared list/form/audit): how it was built, and what review caught**

- **The backend was built test-first, one slice at a time:** list, create with audit, show/update/delete, Brands with logos, then the profile audit entries.
- **Not everything was test-first.** `ListRequest`/`ListParameters` and the frontend's shared list, table and form code were written just ahead of their tests. To compensate, their tests were checked by breaking the code on purpose:
  - no `LIKE` escaping, no tie-breaker, no query string on links;
  - a filter that keeps the page, a search that pushes history, sort not kept in the URL;
  - server errors never placed on fields, controls shown to every Role, no route guard, no logo size check.
  
  Each mutant failed the test named for its rule.
- **Some tests and mutants were invalid at first:**
  - The first tie-breaker test passed without the tie-breaker, because MySQL returns indexed rows in key order anyway. It now sorts by an unindexed count.
  - One frontend mutant was invalid: it caused a syntax error, so every test failed for the wrong reason. It was redone.
  - Two tests were too weak: the filter test started on page 1, and the search test never checked that history was replaced. Both were tightened.
- **The two-axis code review found:**
  - The two-level rule ran in the Form Request, outside the transaction, so a concurrent move and add-child could create a third level. It now runs in the actions with the rows locked.
  - A duplicate slug from two saves at once returned a 500. It is now a 422.
  - Nothing clamped a page past the last one. Such a page now shows the last page.
  - The same eager-load was copied into four places. It is now one model method.
  - New domain terms were missing from the glossary.
- **The visual pass (Playwright screenshots, both themes, both languages, three widths) found:**
  - Latin names and slugs sat on the wrong side in Arabic, and initials like "&C" appeared. Names now use `dir="auto"`.
  - A filter label was cut off on tablets.
- **Two test setups needed workarounds:**
  - Node's form-data parser refuses jsdom's `File`, so the MSW logo mock reads the multipart body as text.
  - The Vite dev server kept a stale import after a file was renamed, which showed up as a blank page in Playwright.
- **Reviews and audits:** `21st review` found nothing. `composer audit` was clean, and `npm audit` showed only the 7 known shadcn-CLI dev advisories. The security review was done inline:
  - upload type, size and generated file names, with SVG refused;
  - allow-listed sort columns and escaped `LIKE` terms;
  - Policies on every write, and no mass-assignable `logo_path`.
- **Shipping (#14) review:** the quote was written test-first (city match, country match, no match, inactive methods hidden). The inline security review checked that tracking link templates must be http(s) addresses with the placeholder (`javascript:` is refused) and are only ever shown as text, that every write goes through a Policy, and that list sorts and filters are allow-listed. `composer audit` was clean; `npm audit` still shows only the known `braces` dev advisory. The visual pass (English/Arabic, light/dark, phone/desktop) found nothing to fix.

- **Customers (#13), reviewed and corrected while building:**
  - The first demo seeder inserted row by row and took about a minute; it now builds rows in memory and inserts them in bulk.
  - Trimming input by hand duplicated Laravel's `TrimStrings` middleware, so it was removed.
  - Security pass (inline, as for #9): every route authorizes through a Form Request or a Policy check, address routes use scoped bindings (another Customer's address is a 404), only listed fields are mass-assignable, `LIKE` terms are escaped, and `mailto:`/`tel:` links only carry validated values. `composer audit` was clean; `npm audit` is unchanged (the known shadcn-CLI dev advisories).

## Known limitations

_(List anything incomplete, simplified or assumed. Mirror the README "Status" section.)_

- Locally, `php artisan serve` handles one request at a time on Windows, so Playwright runs one browser at a time there; CI and the Docker stack can run in parallel.
- Ending an active session when an Admin deactivates a User arrives with Users & Roles administration (#25).
- Refusing to delete a Customer who has Orders relies on the restricting foreign key from the Orders table (#16); `DeleteCustomer` already turns it into a 409, and the Pest test for it lands with Orders.
- Refusing to delete a Category or Brand that Products use relies on restricting foreign keys from the Products table (#10); the 409 path has no backend test until then.
- `npm audit` reports 7 high advisories, all inside the `shadcn` CLI's development-only dependencies (`braces` via `fast-glob`); nothing from them ships in the SPA, and the suggested fix is a downgrade, so they are left until the CLI updates.
