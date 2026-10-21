<?php

namespace App\Actions\Returns;

use App\Enums\AuditAction;
use App\Enums\ReturnStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\OrderReturn;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Approves or rejects a Requested Return, or marks an Approved one Received when the
 * items arrive. (Completed is reached only by {@see RefundReturn}.) Stock does not change.
 */
final class MoveReturn
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @throws BusinessRuleViolation 409 `invalid_return_transition`
     */
    public function handle(OrderReturn $return, ReturnStatus $to, ?string $note = null): OrderReturn
    {
        return DB::transaction(function () use ($return, $to, $note): OrderReturn {
            $return = OrderReturn::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            $from = $return->status;

            if ($to === ReturnStatus::Completed || ! $from->canMoveTo($to)) {
                throw self::invalidTransition($return, $to);
            }

            $return->update([
                'status' => $to,
                ...($to === ReturnStatus::Received
                    ? ['received_at' => now()]
                    : ['decided_at' => now(), 'decision_note' => $note]),
            ]);
            $this->auditTrail->record(AuditAction::StatusChanged, $return, ['status' => $from->value], ['status' => $to->value, 'note' => $note]);

            return $return;
        });
    }

    public static function invalidTransition(OrderReturn $return, ReturnStatus $to): BusinessRuleViolation
    {
        return new BusinessRuleViolation('invalid_return_transition', __('returns.invalid_transition', [
            'number' => $return->number,
            'from' => __("returns.statuses.{$return->status->value}"),
            'to' => __("returns.statuses.$to->value"),
        ]));
    }
}
