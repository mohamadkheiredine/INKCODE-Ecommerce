<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Variant on a Receipt: how many units arrived and what each cost.
 *
 * @property int $id
 * @property int $receipt_id
 * @property int $variant_id
 * @property int $quantity
 * @property string $unit_cost
 * @property-read Variant $variant
 */
#[Fillable(['variant_id', 'quantity', 'unit_cost'])]
class ReceiptLine extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Variant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }
}
