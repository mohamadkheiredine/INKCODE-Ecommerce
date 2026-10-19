<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A Product's images in their new order: each of its image ids exactly once; the first
 * becomes the main image.
 */
class ReorderProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->product()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image_ids' => ['required', 'array', 'max:'.StoreProductImagesRequest::MAX_IMAGES],
            'image_ids.*' => ['integer'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $given = $this->imageIds();
            $current = $this->product()->images()->pluck('id')->all();
            sort($given);
            sort($current);
            if ($given !== $current) {
                $validator->errors()->add('image_ids', __('catalog.images.order_mismatch'));
            }
        }];
    }

    public function product(): Product
    {
        /** @var Product */
        return $this->route('product');
    }

    /**
     * @return list<int>
     */
    public function imageIds(): array
    {
        return array_map(intval(...), array_values((array) $this->input('image_ids')));
    }
}
