<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Enums\CouponType;
use App\Enums\OrderStatus;
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
 * @property-read Collection<int, Shipment> $shipments
 */
#[Fillable([
    'number', 'status', 'customer_id', 'warehouse_id', 'shipping_method_id', 'coupon_id', 'coupon_code', 'coupon_type', 'coupon_value',
    'billing_address', 'shipping_address', 'shipping_method_name', 'currency_code', 'vat_rate',
    'items_total', 'discount_total', 'vat_total', 'shipping_total', 'total', 'notes', 'user_id', 'assignee_id', 'placed_at',
])]
class Order extends Model implements Auditable
{
    use Audited;

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
     * Loads what one Order's page shows: Customer, Warehouse, creator, Assignee, lines,
     * status history, notes and Shipments.
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
            'statusChanges.user:id,name',
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
