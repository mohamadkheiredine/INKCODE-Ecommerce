<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many units of one Order line a Shipment carries.
 *
 * @property int $id
 * @property int $shipment_id
 * @property int $order_line_id
 * @property int $quantity
 * @property-read OrderLine $orderLine
 */
#[Fillable(['order_line_id', 'quantity'])]
class ShipmentLine extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /**
     * @return BelongsTo<OrderLine, $this>
     */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }
}
