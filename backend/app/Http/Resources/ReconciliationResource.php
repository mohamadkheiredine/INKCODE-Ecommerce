<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One reconciliation row: what the Order owes against what its Payment ledger holds.
 * `difference` is ledger total − amount owed: negative while money is still due, positive
 * when the ledger holds more than the Order owes. Amounts are decimal strings.
 *
 * @mixin Order
 */
class ReconciliationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'placed_at' => $this->placed_at->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn (): array => $this->customer->only('id', 'name')),
            'currency_code' => $this->currency_code,
            'total' => $this->total,
            'amount_owed' => self::cents($this->getAttribute('amount_owed')),
            'ledger_total' => self::cents($this->getAttribute('ledger_total')),
            'difference' => self::cents($this->getAttribute('difference')),
        ];
    }

    private static function cents(mixed $amount): string
    {
        return bcadd((string) $amount, '0', 2);
    }
}
