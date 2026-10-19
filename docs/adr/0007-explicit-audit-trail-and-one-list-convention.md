# An explicit audit trail and one list convention

Two shared pieces every module builds on, both hard to change once many modules use them.

**Audit trail.** Every action that changes a record calls `App\Services\AuditTrail` inside the same database transaction as the change: `created($record)`, `updated($record, $before)` (only the changed values; nothing when nothing changed), `deleted($record)` or `record($action, $record)` for other actions such as a password change. Each entry in `audit_entries` keeps the User, the action (`App\Enums\AuditAction`), a stable record type (`category`, `brand`, `user`), the record id, the record's label as it was, and the before/after values. Models opt in with `implements Auditable` + `use Audited`; hidden attributes (passwords, tokens) are never recorded. Entries cannot be updated or deleted.

**List convention.** Every list endpoint takes `search`, `filter[name]=value`, `sort=field` / `sort=-field`, `page` and `per_page` (at most 100), validated by a Form Request extending `App\Http\Requests\ListRequest`, which declares the allowed sorts and filters and refuses anything else with a 422 naming them. It answers with Laravel's paginated resource shape: `data`, `meta`, `links`. The SPA keeps exactly the same parameters in the page URL (`useListQuery`), so a list's address can be shared and back/forward walk through its states.

## Considered options

- **Model events (observers) for the audit trail:** they cannot be forgotten, but they also fire for seeders and system jobs, cannot express actions that are not saves (a status change, a password change, a Refund) and do not know which business operation caused a change. Explicit calls inside each action's transaction keep the entry and the change atomic and readable; each ticket's tests check its entries.
- **A query-builder package (spatie/laravel-query-builder):** would add a dependency for a few dozen lines, and its loose defaults ignore unknown parameters silently. Our own `ListRequest` refuses typos with a clear message, in both languages.
- **List state in component state or local storage:** links could not be shared and back would leave the list.

## Consequences

- Every later mutating action must call `AuditTrail` in its transaction, and its tests must check the entry.
- Deletes that other rows still reference are refused by restricting foreign keys and become 409 `in_use` (`DatabaseErrors::refuseDeleteInUse`); later tables that reference Categories or Brands (Products, #10) must use `restrictOnDelete()`.
