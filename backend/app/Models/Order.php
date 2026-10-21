<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Models\Concerns\Audited;
use App\Services\OrderPricing;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A Customer's purchase, entered by a User and shipped from one Fulfilling warehouse
 * (ADR 0004). It keeps a snapshot of its addresses, Shipping method, VAT rate, Store
 * currency and amounts, so later edits elsewhere never change it. Its status only changes
 * through the Order actions, which follow {@see OrderStatus::canMoveTo()}.
 *
 * @property int $id
 * @property string $number
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property int $customer_id
 * @property int $warehouse_id
 * @property int $shipping_method_id
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property CouponType|null $coupon_type
 * @property string|null $coupon_value
 * @property array{recipient_name: string, phone: string|null, country_code: string, city: string, line1: string, line2: string|null, postal_code: string|null} $billing_address
 * @property array{recipient_name: string, phone: string|null, country_code: string, city: string, line1: string, line2: string|null, postal_code: string|null} $shipping_address
 * @property string $shipping_method_name
 * @property string $currency_code
 * @property string $vat_rate
 * @property string $items_total
 * @property string $discount_total
 * @property string $vat_total
 * @property string $shipping_total
 * @property string $total
 * @property string $captured_amount
 * @property string $refunded_amount
 * @property string|null $notes
 * @property int|null $user_id
 * @property int|null $assignee_id
 * @property Carbon $placed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read Warehouse $warehouse
 * @property-read ShippingMethod $shippingMethod
 * @property-read Coupon|null $coupon
 * @property-read User|null $user
 * @property-read User|null $assignee
 * @property-read Collection<int, OrderLine> $lines
 * @property-read Collection<int, OrderStatusChange> $statusChanges
 * @property-read Collection<int, OrderNote> $orderNotes
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, Shipment> $shipments
 * @property-read Collection<int, OrderReturn> $returns
 */
#[Fillable([
    'number', 'status', 'payment_status', 'customer_id', 'warehouse_id', 'shipping_method_id', 'coupon_id', 'coupon_code', 'coupon_type', 'coupon_value',
    'billing_address', 'shipping_address', 'shipping_method_name', 'currency_code', 'vat_rate',
    'items_total', 'discount_total', 'vat_total', 'shipping_total', 'total', 'captured_amount', 'refunded_amount', 'notes', 'user_id', 'assignee_id', 'placed_at',
])]
class Order extends Model implements Auditable
{
    use Audited;

    /**
     * A new Order has an empty Payment ledger.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'payment_status' => 'unpaid',
        'captured_amount' => '0.00',
        'refunded_amount' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'vat_rate' => 'decimal:2',
            'items_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'captured_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'coupon_type' => CouponType::class,
            'coupon_value' => 'decimal:2',
            'placed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The Fulfilling warehouse.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * The Coupon it uses, if any (its code, type and value are also kept on the Order).
     *
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * The Coupon's terms as {@see OrderPricing::reprice()} takes them.
     *
     * @return array{type: CouponType, value: string}|null
     */
    public function couponTerms(): ?array
    {
        return $this->coupon_type === null || $this->coupon_value === null
            ? null
            : ['type' => $this->coupon_type, 'value' => $this->coupon_value];
    }

    /**
     * The User who created it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The User responsible for handling it (an active Admin, Operations or Sales User).
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * The notes Users added to its timeline, oldest first (`notes` is the column the
     * Order was created with).
     *
     * @return HasMany<OrderNote, $this>
     */
    public function orderNotes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class)->orderBy('id');
    }

    /**
     * Its status history, oldest first.
     *
     * @return HasMany<OrderStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(OrderStatusChange::class)->orderBy('id');
    }

    /**
     * Its Payment ledger, oldest first.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * What the Customer owes: the total, or nothing once the Order is Cancelled.
     */
    public function amountOwed(): string
    {
        return $this->status === OrderStatus::Cancelled ? '0.00' : $this->total;
    }

    /**
     * The Captured amount minus Refunds: what the store still holds, and the most a new
     * Refund may be.
     */
    public function netCaptured(): string
    {
        return bcsub($this->captured_amount, $this->refunded_amount, 2);
    }

    /**
     * What is still to be paid (never negative); a payment may be at most this.
     */
    public function amountDue(): string
    {
        $due = bcsub($this->amountOwed(), $this->netCaptured(), 2);

        return bccomp($due, '0', 2) > 0 ? $due : '0.00';
    }

    /**
     * Its Shipments, oldest first.
     *
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->orderBy('id');
    }

    /**
     * Whether at least one unit shipped and every unit still ordered is in a Shipment.
     */
    public function isFullyShipped(): bool
    {
        return $this->lines->sum('shipped_quantity') > 0
            && $this->lines->every(fn (OrderLine $line): bool => $line->shipped_quantity === $line->quantity);
    }

    /**
     * Its Returns, oldest first.
     *
     * @return HasMany<OrderReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class)->orderBy('id');
    }

    /**
     * When it became Delivered (the latest time, from its status history), or null.
     */
    public function deliveredAt(): ?Carbon
    {
        $changes = $this->relationLoaded('statusChanges') ? $this->statusChanges : $this->statusChanges()->get();

        return $changes->where('to_status', OrderStatus::Delivered)->sortByDesc('id')->first()?->created_at;
    }

    /**
     * The last moment a Return may be Requested: delivery plus the Return window, or null
     * when it is not Delivered.
     */
    public function returnWindowEndsAt(int $windowDays): ?Carbon
    {
        return $this->status === OrderStatus::Delivered ? $this->deliveredAt()?->copy()->addDays($windowDays) : null;
    }

    /**
     * Whether a Completed Return already refunded its shipping.
     */
    public function shippingRefunded(): bool
    {
        return $this->returns()->where('refund_shipping', true)->where('status', ReturnStatus::Completed)->exists();
    }

    /**
     * Loads what one Order's page shows: Customer, Warehouse, creator, Assignee, lines (with
     * their Returns), status history, notes, Shipments, Returns and Payment ledger.
     */
    public function loadForDisplay(): static
    {
        return $this->load([
            'customer:id,name,email,phone',
            'warehouse:id,name,code',
            'user:id,name',
            'assignee:id,name',
            'orderNotes.user:id,name',
            'lines.variant:id,product_id',
            'lines.returnLines.orderReturn:id,status',
            'returns:id,order_id,number,status,reason,refund_shipping,requested_at',
            'statusChanges.user:id,name',
            'payments.user:id,name',
            'payments.orderReturn:id,number,refund_id',
            'shipments.carrier:id,name,tracking_url_template',
            'shipments.lines',
            'shipments.statusChanges.user:id,name',
        ]);
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
            ...$this->only(
                'number', 'customer_id', 'warehouse_id', 'shipping_method_id', 'billing_address', 'shipping_address',
                'coupon_code', 'vat_rate', 'items_total', 'discount_total', 'vat_total', 'shipping_total', 'total', 'notes', 'assignee_id',
            ),
            'status' => $this->status->value,
            'lines' => $this->lines
                ->map(fn (OrderLine $line): array => $line->only('variant_id', 'sku', 'quantity', 'cancelled_quantity', 'unit_price', 'vat_amount', 'line_total'))
                ->all(),
        ];
    }
}
