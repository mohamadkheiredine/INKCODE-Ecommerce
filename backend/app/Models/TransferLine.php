<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Variant's quantity on a Transfer.
 *
 * @property int $id
 * @property int $transfer_id
 * @property int $variant_id
 * @property int $quantity
 * @property-read Variant $variant
 */
#[Fillable(['variant_id', 'quantity'])]
class TransferLine extends Model
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
     * @return BelongsTo<Variant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }
}
