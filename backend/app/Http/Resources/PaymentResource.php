<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One Payment ledger row. `amount` is a decimal string in the Order's currency; `order`
 * is there when the Order was loaded (the payments list).
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'method' => $this->method->value,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'gateway_reference' => $this->gateway_reference,
            'note' => $this->note,
            'idempotency_key' => $this->idempotency_key,
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user?->only('id', 'name')),
            'order' => $this->whenLoaded('order', fn (): array => [
                'id' => $this->order->id,
                'number' => $this->order->number,
                'currency_code' => $this->order->currency_code,
                'payment_status' => $this->order->payment_status->value,
            ]),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
