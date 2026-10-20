<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Product. The list sends its main image, price range and Variant count; one Product
 * sends its images, Variants and the Attributes they differ by. Amounts are decimal
 * strings without VAT.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->whenLoaded('category', fn (): array => $this->category->only('id', 'name', 'slug')),
            'brand' => $this->whenLoaded('brand', fn (): ?array => $this->brand?->only('id', 'name', 'slug')),
            'base_price' => $this->base_price,
            'taxable' => $this->taxable,
            'tags' => $this->tags,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'status' => $this->status,
            'main_image_url' => $this->whenLoaded('mainImage', fn (): ?string => $this->mainImage?->url()),
            'price_min' => $this->whenHas('variants_min_price'),
            'price_max' => $this->whenHas('variants_max_price'),
            'variants_count' => $this->whenCounted('variants'),
            'images' => $this->whenLoaded('images', fn (): array => $this->images
                ->map(fn (ProductImage $image): array => ['id' => $image->id, 'url' => $image->url()])
                ->all()),
            'attributes' => $this->whenLoaded('variants', fn (): array => $this->variantAttributes()),
            'variants' => VariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
