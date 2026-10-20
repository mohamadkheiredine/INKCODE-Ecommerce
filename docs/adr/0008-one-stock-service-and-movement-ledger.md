# One stock service over an append-only movement ledger

All stock changes go through `App\Services\Stock`. `move($type, $source, list<StockChange>, $reason)` is the one primitive, and `receive($receipt, $lines)` builds on it. Each call runs in one transaction that locks rows in a fixed order: the Warehouses receiving units (shared lock), then the Variants (by id, `FOR UPDATE`), then their `stock_balances` rows (by id). It then checks On-hand ≥ 0, Reserved ≥ 0 and Reserved ≤ On-hand, refusing with 409 `insufficient_stock`, updates the balances, and appends one `stock_movements` row per change with its deltas, the balance after it, the type, the reason, the User and the source record. Movements are never updated or deleted. CHECK constraints refuse invalid balances even if a code path skipped the service.

Locking the Variant first means a missing balance row can be created without an insert race. It also serializes Average cost re-weighting (ADR 0005). Rows that reference a Variant (Receipt lines, Order lines) must be inserted *after* `Stock` has locked it. Inserting them first takes shared foreign-key locks, and two transactions upgrading those locks deadlock. A real multi-process test (`tests/Concurrency`) caught this.

Mutating endpoints accept an `Idempotency-Key` through the `idempotent` route middleware (`EnsureIdempotency`). The key row is claimed in the same transaction as the request's changes and stores the successful response. A retry replays that response, a retry that arrives during the first request waits on the key's row lock, a refused request leaves the key free, and a key reused with a different body gets 409 `idempotency_key_reused` (changed from 422 with the Orders ticket, whose acceptance criteria treat it as a conflict with the first request).

## Consequences

- Later tickets (Adjustments, Transfers, Orders, Returns) add a `StockMovementType` case and call `Stock::move()`. They never write balances themselves.
- Balances always equal the sum of their movements, which the invariant test checks.
