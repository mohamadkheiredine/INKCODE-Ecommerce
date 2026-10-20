<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Stock arriving from a supplier into a Warehouse: its lines hold quantities and unit costs.
 * A Receipt is never edited or deleted; mistakes are corrected with an Adjustment.
 *
 * @property int $id
 * @property string|null $number
 * @property int $warehouse_id
 * @property string $supplier_reference
 * @property string|null $notes
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Warehouse $warehouse
 * @property-read User|null $user
 * @property-read Collection<int, ReceiptLine> $lines
 */
#[Fillable(['warehouse_id', 'supplier_reference', 'notes', 'user_id'])]
class Receipt extends Model implements Auditable
{
    use Audited;

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
     * @return HasMany<ReceiptLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ReceiptLine::class)->orderBy('id');
    }

    /**
     * Gives a new Receipt its number, RCV-<year>-<id padded to six digits>.
     */
    public function assignNumber(): void
    {
        $this->number = sprintf('RCV-%s-%06d', ($this->created_at ?? now())->format('Y'), $this->id);
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
            'warehouse_id' => $this->warehouse_id,
            'supplier_reference' => $this->supplier_reference,
            'notes' => $this->notes,
            'lines' => $this->lines
                ->map(fn (ReceiptLine $line): array => $line->only('variant_id', 'quantity', 'unit_cost'))
                ->all(),
        ];
    }
}
