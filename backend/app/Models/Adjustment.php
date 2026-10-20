<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Enums\AdjustmentReason;
use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A correction of one Variant's On-hand in one Warehouse, always with a reason. It is never
 * edited or deleted; a later Adjustment corrects a mistaken one.
 *
 * @property int $id
 * @property string|null $number
 * @property int $warehouse_id
 * @property int $variant_id
 * @property int $quantity_delta
 * @property AdjustmentReason $reason
 * @property string|null $notes
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Warehouse $warehouse
 * @property-read Variant $variant
 * @property-read User|null $user
 */
#[Fillable(['warehouse_id', 'variant_id', 'quantity_delta', 'reason', 'notes', 'user_id'])]
class Adjustment extends Model implements Auditable
{
    use Audited;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'reason' => AdjustmentReason::class,
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Variant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Gives a new Adjustment its number, ADJ-<year>-<id padded to six digits>.
     */
    public function assignNumber(): void
    {
        $this->number = sprintf('ADJ-%s-%06d', ($this->created_at ?? now())->format('Y'), $this->id);
        $this->save();
    }

    public function auditLabel(): string
    {
        return (string) $this->number;
    }
}
