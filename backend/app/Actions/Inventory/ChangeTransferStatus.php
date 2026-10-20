<?php

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Enums\TransferStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Transfer;
use App\Models\TransferLine;
use App\Services\AuditTrail;
use App\Services\Stock;
use App\Services\StockChange;
use Illuminate\Support\Facades\DB;

/**
 * The only way a Transfer's status changes: Draft → In transit (send: takes the lines out
 * of the source, checked against Available), In transit → Received (puts them into the
 * destination), or Draft → Cancelled. The Transfer row is locked, so a double click or a
 * retry cannot send or receive twice.
 */
final class ChangeTransferStatus
{
    public function __construct(
        private readonly Stock $stock,
        private readonly AuditTrail $auditTrail,
    ) {}

    /**
     * @throws BusinessRuleViolation 409 `invalid_transition`, `insufficient_stock` or `warehouse_inactive`
     */
    public function handle(Transfer $transfer, TransferStatus $to): Transfer
    {
        return DB::transaction(function () use ($transfer, $to): Transfer {
            $transfer = Transfer::query()->with('lines')->lockForUpdate()->findOrFail($transfer->id);
            $from = $transfer->status;

            $allowed = match ($to) {
                TransferStatus::InTransit, TransferStatus::Cancelled => $from === TransferStatus::Draft,
                TransferStatus::Received => $from === TransferStatus::InTransit,
                TransferStatus::Draft => false,
            };

            if (! $allowed) {
                throw new BusinessRuleViolation('invalid_transition', __('inventory.transfers.invalid_transition', [
                    'from' => __("inventory.transfers.status.{$from->value}"),
                    'to' => __("inventory.transfers.status.{$to->value}"),
                ]));
            }

            $before = $transfer->auditValues();

            if ($to === TransferStatus::InTransit) {
                $this->moveLines($transfer, StockMovementType::TransferOut, $transfer->source_warehouse_id, -1);
            } elseif ($to === TransferStatus::Received) {
                $this->moveLines($transfer, StockMovementType::TransferIn, $transfer->destination_warehouse_id, 1);
            }

            $transfer->update([
                'status' => $to,
                match ($to) {
                    TransferStatus::InTransit => 'sent_at',
                    TransferStatus::Received => 'received_at',
                    default => 'cancelled_at',
                } => now(),
            ]);
            $this->auditTrail->updated($transfer, $before);

            return $transfer;
        });
    }

    private function moveLines(Transfer $transfer, StockMovementType $type, int $warehouseId, int $sign): void
    {
        $this->stock->move($type, $transfer, $transfer->lines
            ->map(fn (TransferLine $line): StockChange => new StockChange($line->variant_id, $warehouseId, onHandDelta: $sign * $line->quantity))
            ->values()
            ->all());
    }
}
