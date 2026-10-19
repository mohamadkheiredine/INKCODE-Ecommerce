<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

/**
 * New images for a Product (multipart): PNG, JPEG or WebP, at most 2 MB each, and at
 * most ten per Product in all. SVG is refused, because an SVG served from our own
 * origin can carry scripts.
 */
class StoreProductImagesRequest extends FormRequest
{
    public const int MAX_IMAGES = 10;

    public const int MAX_KILOBYTES = 2048;

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
            'images' => ['required', 'array', 'min:1', 'max:'.self::MAX_IMAGES],
            'images.*' => [File::types(['png', 'jpg', 'jpeg', 'webp'])->max(self::MAX_KILOBYTES), 'image'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && $this->product()->images()->count() + count($this->images()) > self::MAX_IMAGES) {
                $validator->errors()->add('images', __('catalog.images.too_many', ['max' => self::MAX_IMAGES]));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['images.*' => __('catalog.images.image')];
    }

    public function product(): Product
    {
        /** @var Product */
        return $this->route('product');
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        /** @var list<UploadedFile> */
        return array_values((array) $this->file('images'));
    }
}
