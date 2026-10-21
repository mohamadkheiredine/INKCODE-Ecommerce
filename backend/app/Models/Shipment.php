<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Enums\ShipmentStatus;
use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A parcel that leaves the Fulfilling warehouse with some units of an Order's lines, a
 * Carrier and a tracking number. Its number is the Order's number and its position
 * ("ORD-2026-00012-2"). Its status only changes through the Shipment actions.
 *
 * @property int $id
 * @property string $number
 * @property int $order_id
 * @property int $carrier_id
 * @property string $tracking_number
 * @property ShipmentStatus $status
 * @property int|null $user_id
 * @property Carbon $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @property-read Carrier $carrier
 * @property-read User|null $user
 * @property-read Collection<int, ShipmentLine> $lines
 * @property-read Collection<int, ShipmentStatusChange> $statusChanges
 */
#[Fillable(['number', 'order_id', 'carrier_id', 'tracking_number', 'status', 'user_id', 'shipped_at', 'delivered_at'])]
class Shipment extends Model implements Auditable
{
    use Audited;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * @return BelongsTo<Carrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    /**
     * The User who shipped it.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ShipmentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ShipmentLine::class)->orderBy('id');
    }

    /**
     * Its status history, oldest first.
     *
     * @return HasMany<ShipmentStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(ShipmentStatusChange::class)->orderBy('id');
    }

    /** The Carrier's tracking page for it, or null when the Carrier has no tracking link. */
    public function trackingUrl(): ?string
    {
        return $this->carrier->trackingUrl($this->tracking_number);
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
            ...$this->only('number', 'order_id', 'carrier_id', 'tracking_number'),
            'status' => $this->status->value,
            'lines' => $this->lines->map(fn (ShipmentLine $line): array => $line->only('order_line_id', 'quantity'))->all(),
        ];
    }
}
