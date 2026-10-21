<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Variant on an Order, with a snapshot of what it was sold as: the Product name, SKU,
 * options, unit price (without VAT), its share of the discount, and the VAT rate and
 * amount in force when it was priced. `line_total` = quantity × unit price − discount + VAT.
 *
 * @property int $id
 * @property int $order_id
 * @property int $variant_id
 * @property string $product_name
 * @property string $sku
 * @property list<array{attribute: string, value: string}> $options
 * @property int $quantity the units still ordered
 * @property int $cancelled_quantity the units removed by Partial cancellation
 * @property string $unit_price
 * @property string $discount_amount
 * @property bool $taxable
 * @property string $vat_rate
 * @property string $vat_amount
 * @property string $line_total
 * @property-read Order $order
 * @property-read Variant $variant
 */
#[Fillable([
    'variant_id', 'product_name', 'sku', 'options', 'quantity', 'cancelled_quantity', 'unit_price', 'discount_amount',
    'taxable', 'vat_rate', 'vat_amount', 'line_total',
])]
class OrderLine extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'quantity' => 'integer',
            'cancelled_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable' => 'boolean',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
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
     * @return BelongsTo<Variant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }
}
