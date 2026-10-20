<?php

namespace App\Models;

use App\Services\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One Variant's stock in one Warehouse. Only {@see Stock} changes it, and
 * always together with a Stock movement, so it always equals the sum of its movements.
 * Available (On-hand − Reserved) is a stored generated column.
 *
 * @property int $id
 * @property int $variant_id
 * @property int $warehouse_id
 * @property int $on_hand
 * @property int $reserved
 * @property int $available
 * @property Carbon|null $low_stock_alerted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Variant $variant
 * @property-read Warehouse $warehouse
 */
class StockBalance extends Model
{
    /** @var list<string> */
    protected $guarded = ['id', 'available'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'available' => 'integer',
            'low_stock_alerted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Variant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
