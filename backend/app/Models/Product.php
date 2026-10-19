<?php

namespace App\Models;

use App\Contracts\Auditable;
use App\Enums\ProductStatus;
use App\Models\Concerns\Audited;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Something the store sells, described once and sold through its Variants. Prices are
 * entered without VAT; `taxable` says whether the store's VAT rate applies.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $category_id
 * @property int|null $brand_id
 * @property string $base_price
 * @property bool $taxable
 * @property list<string> $tags
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property ProductStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category $category
 * @property-read Brand|null $brand
 * @property-read Collection<int, ProductImage> $images
 * @property-read ProductImage|null $mainImage
 * @property-read Collection<int, Variant> $variants
 */
#[Fillable([
    'name', 'slug', 'description', 'category_id', 'brand_id', 'base_price', 'taxable',
    'tags', 'seo_title', 'seo_description', 'status',
])]
class Product extends Model implements Auditable
{
    use Audited;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'taxable' => 'boolean',
            'tags' => 'array',
            'status' => ProductStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * In display order; the first is the main image.
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasOne<ProductImage, $this>
     */
    public function mainImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany('position', 'min');
    }

    /**
     * @return HasMany<Variant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Everything the Product screen shows: Category, Brand, images and each Variant with
     * its Attribute values.
     */
    public function loadForDisplay(): static
    {
        return $this->load([
            'category:id,name,slug',
            'brand:id,name,slug',
            'images',
            'variants.attributeValues.attribute:id,name',
        ]);
    }

    /**
     * The Attributes its Variants differ by, each with the values its Variants use, in
     * the Attribute's own order. Needs the Variants loaded with their values.
     *
     * @return list<array{id: int, name: string, value_ids: list<int>}>
     */
    public function variantAttributes(): array
    {
        return $this->variants
            ->flatMap(fn (Variant $variant) => $variant->attributeValues)
            ->unique('id')
            ->sortBy([['position', 'asc'], ['id', 'asc']])
            ->groupBy('attribute_id')
            ->sortKeys()
            ->map(fn ($values): array => [
                'id' => $values->first()->attribute->id,
                'name' => $values->first()->attribute->name,
                'value_ids' => $values->pluck('id')->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The image paths in order, as the audit trail records an image change.
     *
     * @return array{images: list<string>}
     */
    public function imageAuditValues(): array
    {
        return ['images' => $this->images()->pluck('path')->all()];
    }
}
