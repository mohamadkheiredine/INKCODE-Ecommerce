<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Services\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One recorded change to a Variant's On-hand or Reserved quantity in a Warehouse, with its
 * cause (type, reason, User, source record) and the balance right after it. Rows are only
 * ever appended, by {@see Stock}; they are never changed or deleted.
 *
 * @property int $id
 * @property int $variant_id
 * @property int $warehouse_id
 * @property StockMovementType $type
 * @property int $on_hand_delta
 * @property int $reserved_delta
 * @property int $on_hand_after
 * @property int $reserved_after
 * @property string|null $reason
 * @property int|null $user_id
 * @property string $source_type
 * @property int $source_id
 * @property Carbon $created_at
 * @property-read Variant $variant
 * @property-read Warehouse $warehouse
 * @property-read User|null $user
 * @property-read Model|null $source
 */
class StockMovement extends Model
{
    public const null UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Stock movements are never changed.'));
        static::deleting(fn (): never => throw new LogicException('Stock movements are never deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'on_hand_delta' => 'integer',
            'reserved_delta' => 'integer',
            'on_hand_after' => 'integer',
            'reserved_after' => 'integer',
            'source_id' => 'integer',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
