<?php

namespace App\Actions\Inventory;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Services\AuditTrail;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;

/**
 * Saves a Draft Transfer; no stock moves until it is sent.
 */
final class CreateTransfer
{
    public function __construct(
        private readonly AuditTrail $auditTrail,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  array{source_warehouse_id: int, destination_warehouse_id: int, notes: string|null, lines: list<array{variant_id: int, quantity: int}>}  $values
     */
    public function handle(array $values): Transfer
    {
        return DB::transaction(function () use ($values): Transfer {
            $userId = $this->auth->guard()->id();
            $transfer = Transfer::query()->create([
                'source_warehouse_id' => $values['source_warehouse_id'],
                'destination_warehouse_id' => $values['destination_warehouse_id'],
                'status' => TransferStatus::Draft,
                'notes' => $values['notes'],
                'user_id' => $userId === null ? null : (int) $userId,
            ]);
            $transfer->assignNumber();
            $transfer->lines()->createMany($values['lines']);

            $this->auditTrail->created($transfer);

            return $transfer;
        });
    }
}
