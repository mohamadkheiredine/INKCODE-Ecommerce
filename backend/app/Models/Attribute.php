<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Models\Concerns\Audited;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A shared dimension Variants differ by (Size, Color, Material), with its values in
 * display order.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AttributeValue> $values
 */
#[Fillable(['name'])]
class Attribute extends Model implements Auditable
{
    use Audited;

    /** @use HasFactory<AttributeFactory> */
    use HasFactory;

    /**
     * @return HasMany<AttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('position')->orderBy('id');
    }

    /**
     * The name and the list of values as "Red (RED)", so the trail shows what the list was.
     *
     * @return array<string, mixed>
     */
    public function auditValues(): array
    {
        return [
            'name' => $this->name,
            'values' => $this->values()->get()
                ->map(fn (AttributeValue $value): string => "{$value->name} ({$value->code})")
                ->all(),
        ];
    }
}
