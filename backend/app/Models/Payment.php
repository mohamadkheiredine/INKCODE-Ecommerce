<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentOutcome;
use App\Models\Concerns\Audited;
use App\Services\PaymentLedger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row of an Order's Payment ledger: a payment or a Refund, by Payment method, with
 * whether it succeeded. Rows are only ever added ({@see PaymentLedger}).
 *
 * @property int $id
 * @property int $order_id
 * @property PaymentKind $kind
 * @property PaymentMethod $method
 * @property string $amount
 * @property PaymentOutcome $status
 * @property string|null $gateway_reference
 * @property string|null $note
 * @property int|null $user_id
 * @property string|null $idempotency_key
 * @property Carbon $created_at
 * @property-read Order $order
 * @property-read User|null $user
 */
#[Fillable(['order_id', 'kind', 'method', 'amount', 'status', 'gateway_reference', 'note', 'user_id', 'idempotency_key', 'created_at'])]
class Payment extends Model implements Auditable
{
    use Audited;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PaymentKind::class,
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'status' => PaymentOutcome::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The User who recorded it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditLabel(): string
    {
        return $this->order->number;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditValues(): array
    {
        return [
            ...$this->only('order_id', 'amount', 'gateway_reference', 'note', 'idempotency_key'),
            'kind' => $this->kind->value,
            'method' => $this->method->value,
            'status' => $this->status->value,
        ];
    }
}
