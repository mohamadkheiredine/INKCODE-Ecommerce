<?php

namespace App\Services;

use App\Contracts\Auditable;
use App\Enums\AuditAction;
use App\Models\AuditEntry;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Writes the audit trail: who changed which record, how, and the values before and
 * after. Every action that changes a record calls it inside the same transaction as the
 * change, so an entry exists exactly when the change does.
 *
 *     $before = $category->auditValues();
 *     $category->update($values);
 *     $auditTrail->updated($category, $before);
 */
final class AuditTrail
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function created(Auditable $record): AuditEntry
    {
        return $this->record(AuditAction::Created, $record, null, $record->auditValues());
    }

    /**
     * Records only the values that changed. Nothing is written when nothing changed.
     *
     * @param  array<string, mixed>  $before  the record's {@see Auditable::auditValues()} before the change
     */
    public function updated(Auditable $record, array $before): ?AuditEntry
    {
        $after = $record->auditValues();
        $changed = array_keys(array_filter(
            $after,
            fn (mixed $value, string $key): bool => ! array_key_exists($key, $before) || $before[$key] !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changed === []) {
            return null;
        }

        return $this->record(
            AuditAction::Updated,
            $record,
            array_intersect_key($before, array_flip($changed)),
            array_intersect_key($after, array_flip($changed)),
        );
    }

    public function deleted(Auditable $record): AuditEntry
    {
        return $this->record(AuditAction::Deleted, $record, $record->auditValues(), null);
    }

    /**
     * Any other action (a status change, a password change…). Values are optional.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(AuditAction $action, Auditable $record, ?array $before = null, ?array $after = null): AuditEntry
    {
        $userId = $this->auth->guard()->id();

        return AuditEntry::query()->create([
            'user_id' => $userId === null ? null : (int) $userId,
            'action' => $action,
            'record_type' => $record->auditRecordType(),
            'record_id' => (int) $record->getKey(),
            'record_label' => $record->auditLabel(),
            'before' => $before,
            'after' => $after,
        ]);
    }
}
