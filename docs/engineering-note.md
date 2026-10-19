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
- _(add: order state machine, idempotency, stock service as they land)_

## AI tools used

- **Claude Code**: scaffolding, implementation, tests, documentation, reviews. Every change was reviewed before commit.
- **Context7 MCP**: current library documentation (Laravel 13, React Router 8, TanStack Table 9, shadcn/ui 4 and others).
- **Laravel Boost** (dev-only): MCP server with version-matched Laravel docs search and read-only database schema/query tools, plus Laravel coding guidelines for the agent.
- **Skills**: `frontend-design`, `clean-code`, mattpocock skills (`tdd`, `improve-codebase-architecture`, `code-review`, `retro`, etc.), `security-review`.
- **21st.dev** (CLI, agent skills and MCP server): UI inspiration and component code, always adapted to the project's tokens, i18n and RTL rules.
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

## Known limitations

_(List anything incomplete, simplified or assumed. Mirror the README "Status" section.)_

- Locally, `php artisan serve` handles one request at a time on Windows, so Playwright runs one browser at a time there; CI and the Docker stack can run in parallel.
- Ending an active session when an Admin deactivates a User arrives with Users & Roles administration (#25).
