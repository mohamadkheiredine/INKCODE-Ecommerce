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
- _(add: order state machine, idempotency, stock service as they land)_

## AI tools used

- **Claude Code**: scaffolding, implementation, tests, documentation, reviews. Every change was reviewed before commit.
- **Context7 MCP**: current library documentation (Laravel 13, React Router 8, TanStack Table 9, shadcn/ui 4 and others).
- **Laravel Boost** (dev-only): MCP server with version-matched Laravel docs search and read-only database schema/query tools, plus Laravel coding guidelines for the agent.
- **Skills**: `frontend-design`, `clean-code`, mattpocock skills (`tdd`, `improve-codebase-architecture`, `code-review`, `retro`, etc.), `security-review`.
- **21st.dev** (CLI, agent skills and MCP server): UI inspiration and component code, always adapted to the project's tokens, i18n and RTL rules.
- **21st.dev review** (`21st review`): automated UI audit of every changed screen (it caught a removed focus outline on the main landmark).
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

## Known limitations

_(List anything incomplete, simplified or assumed. Mirror the README "Status" section.)_

- Locally, `php artisan serve` handles one request at a time on Windows, so Playwright runs one browser at a time there; CI and the Docker stack can run in parallel.
- Ending an active session when an Admin deactivates a User arrives with Users & Roles administration (#25).
- Profile changes (preferences, password) write audit entries once the audit trail exists (#9).
- `npm audit` reports 7 high advisories, all inside the `shadcn` CLI's development-only dependencies (`braces` via `fast-glob`); nothing from them ships in the SPA, and the suggested fix is a downgrade, so they are left until the CLI updates.
