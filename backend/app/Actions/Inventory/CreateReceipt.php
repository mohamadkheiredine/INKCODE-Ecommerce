<?php

namespace App\Actions\Inventory;

use App\Exceptions\BusinessRuleViolation;
use App\Models\Receipt;
use App\Services\AuditTrail;
use App\Services\Stock;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;

/**
 * Records a Receipt and receives its lines into the Warehouse's On-hand, re-weighting each
 * Variant's Average cost, all in one transaction.
 */
final class CreateReceipt
{
    public function __construct(
        private readonly Stock $stock,
        private readonly AuditTrail $auditTrail,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  array{warehouse_id: int, supplier_reference: string, notes: string|null, lines: list<array{variant_id: int, quantity: int, unit_cost: string}>}  $values
     *
     * @throws BusinessRuleViolation 409 `warehouse_inactive` when the Warehouse was deactivated meanwhile
     */
    public function handle(array $values): Receipt
    {
        return DB::transaction(function () use ($values): Receipt {
            $userId = $this->auth->guard()->id();
            $receipt = Receipt::query()->create([
                'warehouse_id' => $values['warehouse_id'],
                'supplier_reference' => $values['supplier_reference'],
                'notes' => $values['notes'],
                'user_id' => $userId === null ? null : (int) $userId,
            ]);
            $receipt->assignNumber();

            $this->stock->receive($receipt, $values['lines']);
            $this->auditTrail->created($receipt);

            return $receipt;
        });
    }
}
