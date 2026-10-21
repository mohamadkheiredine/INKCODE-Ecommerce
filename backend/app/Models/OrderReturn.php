<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A Return: a Customer sending back some units of a Delivered Order's lines ("Return" is
 * a PHP keyword, hence the class name). Its status only changes through the Return
 * actions, which follow {@see ReturnStatus::canMoveTo()}; stock changes only at each
 * line's Restock decision.
 *
 * @property int $id
 * @property string $number
 * @property int $order_id
 * @property ReturnStatus $status
 * @property ReturnReason $reason
 * @property string|null $note
 * @property string|null $decision_note
 * @property bool $refund_shipping
 * @property int|null $refund_id
 * @property string|null $refund_amount
 * @property int|null $user_id
 * @property Carbon $requested_at
 * @property Carbon|null $decided_at
 * @property Carbon|null $received_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @property-read User|null $user
 * @property-read Payment|null $refund
 * @property-read Collection<int, ReturnLine> $lines
 */
#[Fillable([
    'number', 'order_id', 'status', 'reason', 'note', 'decision_note', 'refund_shipping', 'refund_id', 'refund_amount',
    'user_id', 'requested_at', 'decided_at', 'received_at', 'completed_at',
])]
class OrderReturn extends Model implements Auditable
{
    use Audited;

    protected $table = 'returns';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'reason' => ReturnReason::class,
            'refund_shipping' => 'boolean',
            'refund_amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * The User who requested it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Its Refund in the Order's Payment ledger, once recorded.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'refund_id');
    }

    /**
     * @return HasMany<ReturnLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ReturnLine::class, 'return_id')->orderBy('id');
    }

    /**
     * Whether every line has its Restock decision.
     */
    public function isFullyDecided(): bool
    {
        return $this->lines->every(fn (ReturnLine $line): bool => $line->restock_decision !== null);
    }

    /**
     * What the Customer paid for the returned units: for each line, its total (price minus
     * its Coupon share plus its VAT) in proportion to the units returned, rounded half up
     * to the cent; plus the Order's shipping when `$withShipping`.
     */
    public function suggestedRefund(bool $withShipping): string
    {
        $items = $this->lines->reduce(fn (string $sum, ReturnLine $line): string => bcadd($sum, $line->paidAmount(), 2), '0.00');

        return $withShipping ? bcadd($items, $this->order->shipping_total, 2) : $items;
    }

    public function auditRecordType(): string
    {
        return 'return';
    }

    public function auditLabel(): string
    {
        return $this->number;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditValues(): array
    {
        return [
            ...$this->only('number', 'order_id', 'note', 'decision_note', 'refund_shipping', 'refund_id', 'refund_amount'),
            'status' => $this->status->value,
            'reason' => $this->reason->value,
            'lines' => $this->lines
                ->map(fn (ReturnLine $line): array => [
                    ...$line->only('order_line_id', 'quantity'),
                    'restock_decision' => $line->restock_decision?->value,
                ])
                ->all(),
        ];
    }
}
