<?php

namespace App\Actions\Catalog;

use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\Variant;
use App\Services\AuditTrail;
use App\Support\DatabaseErrors;
use App\Support\Sku;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generates a Product's Variants from chosen Attribute values: every combination (one
 * value per Attribute) that does not exist yet. Existing Variants and their data are
 * kept. Each new Variant gets a readable, unique SKU built from the Product and value
 * codes ("CLASSIC-TEE-RED-M") and the Product's base price.
 *
 * A Product's Variants all differ by the same Attributes, so values of other Attributes
 * are refused until its Variants are deleted. No values at all makes the one Variant of
 * a simple Product.
 */
final class GenerateVariants
{
    private const int PRODUCT_CODE_LENGTH = 30;

    public function __construct(private readonly AuditTrail $auditTrail) {}

    /**
     * @param  list<int>  $valueIds
     * @return int how many Variants were created
     */
    public function handle(Product $product, array $valueIds): int
    {
        return DatabaseErrors::refuseDuplicate(fn (): int => DB::transaction(function () use ($product, $valueIds): int {
            // One generation per Product at a time, so two never add the same combination.
            Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $valuesByAttribute = AttributeValue::query()
                ->with('attribute:id,name')
                ->whereKey($valueIds)
                ->orderBy('attribute_id')->orderBy('position')->orderBy('id')
                ->get()
                ->groupBy('attribute_id');

            $this->refuseOtherAttributes($product, $valuesByAttribute->keys()->all());

            $existing = $product->variants()->pluck('combination_key')->flip();
            $position = $existing->isEmpty() ? -1 : (int) $product->variants()->max('position');
            $productCode = Sku::codeFrom($product->name, self::PRODUCT_CODE_LENGTH) ?: "P{$product->id}";
            $created = 0;

            foreach ($this->combinations($valuesByAttribute->values()->all()) as $combination) {
                $key = Variant::combinationKey($combination->modelKeys());
                if ($existing->has($key)) {
                    continue;
                }

                $variant = $product->variants()->create([
                    'sku' => $this->uniqueSku(implode('-', [$productCode, ...$combination->pluck('code')])),
                    'combination_key' => $key,
                    'price' => $product->base_price,
                    'average_cost' => '0',
                    'low_stock_threshold' => null,
                    'position' => ++$position,
                ]);
                $variant->attributeValues()->attach($combination->modelKeys());

                $this->auditTrail->created($variant);
                $created++;
            }

            return $created;
        }), 'attribute_value_ids', __('catalog.variants.try_again'));
    }

    /**
     * @param  list<int>  $attributeIds  the chosen values' Attributes, in id order
     */
    private function refuseOtherAttributes(Product $product, array $attributeIds): void
    {
        $firstVariant = $product->variants()->with('attributeValues.attribute:id,name')->first();
        if ($firstVariant === null) {
            return;
        }

        $existing = $firstVariant->attributeValues->pluck('attribute');
        if ($existing->pluck('id')->sort()->values()->all() === $attributeIds) {
            return;
        }

        throw ValidationException::withMessages(['attribute_value_ids' => $existing->isEmpty()
            ? __('catalog.variants.simple_product')
            : __('catalog.variants.other_attributes', ['attributes' => $existing->pluck('name')->join(', ')]),
        ]);
    }

    /**
     * Every way to pick one value from each group; one empty combination for no groups.
     *
     * @param  list<Collection<int, AttributeValue>>  $groups
     * @return list<Collection<int, AttributeValue>>
     */
    private function combinations(array $groups): array
    {
        $combinations = [new Collection];
        foreach ($groups as $values) {
            $next = [];
            foreach ($combinations as $combination) {
                foreach ($values as $value) {
                    $next[] = (clone $combination)->push($value);
                }
            }
            $combinations = $next;
        }

        return $combinations;
    }

    /**
     * The SKU itself, or with "-2", "-3"… when another Variant already has it.
     */
    private function uniqueSku(string $base): string
    {
        $base = rtrim(substr($base, 0, Sku::MAX_LENGTH - 4), '-');
        $taken = Variant::query()
            ->where(fn ($query) => $query->where('sku', $base)->orWhere('sku', 'like', $base.'-%'))
            ->pluck('sku')
            ->flip();

        $sku = $base;
        for ($suffix = 2; $taken->has($sku); $suffix++) {
            $sku = "$base-$suffix";
        }

        return $sku;
    }
}
