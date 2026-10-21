<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step in a Shipment's status history: from which status (null when it was created) to
 * which, by whom and when.
 *
 * @property int $id
 * @property int $shipment_id
 * @property ShipmentStatus|null $from_status
 * @property ShipmentStatus $to_status
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property-read User|null $user
 */
#[Fillable(['from_status', 'to_status', 'user_id'])]
class ShipmentStatusChange extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => ShipmentStatus::class,
            'to_status' => ShipmentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
