<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Models\Concerns\Audited;
use Database\Factories\VariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One sellable version of a Product ("Red / M"): its own SKU, price (without VAT),
 * Average cost and Low-stock threshold. Its Attribute values are fixed when it is
 * generated; another combination is another Variant.
 *
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string $combination_key
 * @property string $price
 * @property string $average_cost
 * @property int|null $low_stock_threshold
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Collection<int, AttributeValue> $attributeValues
 * @property-read Collection<int, StockBalance> $stockBalances
 */
#[Fillable(['sku', 'combination_key', 'price', 'average_cost', 'low_stock_threshold', 'position'])]
class Variant extends Model implements Auditable
{
    use Audited;

    /** @use HasFactory<VariantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'average_cost' => 'decimal:4',
            'low_stock_threshold' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Its stock in each Warehouse that ever held it, in Warehouse order.
     *
     * @return HasMany<StockBalance, $this>
     */
    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class)->orderBy('warehouse_id');
    }

    /**
     * Ordered by Attribute, so "Color / Size" always reads the same way.
     *
     * @return BelongsToMany<AttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class)->orderBy('attribute_values.attribute_id');
    }

    /**
     * The combination as people read it, for example
     * `[['attribute' => 'Color', 'value' => 'Red'], ['attribute' => 'Size', 'value' => 'M']]`.
     * Order lines (#16) copy this into their snapshot.
     *
     * @return list<array{attribute: string, value: string}>
     */
    public function options(): array
    {
        return $this->attributeValues
            ->map(fn (AttributeValue $value): array => ['attribute' => $value->attribute->name, 'value' => $value->name])
            ->values()
            ->all();
    }

    public function auditLabel(): string
    {
        return $this->sku;
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['combination_key', 'position'];
    }

    /**
     * The key of a combination of Attribute value ids: sorted and joined ("4-9"), empty
     * for a Product without Attributes.
     *
     * @param  list<int>  $valueIds
     */
    public static function combinationKey(array $valueIds): string
    {
        sort($valueIds);

        return implode('-', $valueIds);
    }
}
