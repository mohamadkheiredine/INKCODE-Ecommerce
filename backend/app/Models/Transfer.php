<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Enums\TransferStatus;
use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Stock moving between two Warehouses in two steps: sending takes it out of the source
 * (it is then in transit), receiving puts it into the destination. Only a Draft can be
 * cancelled.
 *
 * @property int $id
 * @property string|null $number
 * @property int $source_warehouse_id
 * @property int $destination_warehouse_id
 * @property TransferStatus $status
 * @property string|null $notes
 * @property int|null $user_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $received_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Warehouse $sourceWarehouse
 * @property-read Warehouse $destinationWarehouse
 * @property-read User|null $user
 * @property-read Collection<int, TransferLine> $lines
 */
#[Fillable(['source_warehouse_id', 'destination_warehouse_id', 'status', 'notes', 'user_id', 'sent_at', 'received_at', 'cancelled_at'])]
class Transfer extends Model implements Auditable
{
    use Audited;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TransferLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(TransferLine::class)->orderBy('id');
    }

    /**
     * Gives a new Transfer its number, TRF-<year>-<id padded to six digits>.
     */
    public function assignNumber(): void
    {
        $this->number = sprintf('TRF-%s-%06d', ($this->created_at ?? now())->format('Y'), $this->id);
        $this->save();
    }

    public function auditLabel(): string
    {
        return (string) $this->number;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditValues(): array
    {
        return [
            'number' => $this->number,
            'source_warehouse_id' => $this->source_warehouse_id,
            'destination_warehouse_id' => $this->destination_warehouse_id,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'lines' => $this->lines
                ->map(fn (TransferLine $line): array => $line->only('variant_id', 'quantity'))
                ->all(),
        ];
    }
}
