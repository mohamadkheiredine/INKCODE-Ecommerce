<?php

namespace App\Http\Requests\Catalog;

use App\Models\Brand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

/**
 * A Brand's logo: a PNG, JPEG or WebP image of at most 1 MB. SVG is refused, because
 * an SVG served from our own origin can carry scripts.
 */
class StoreBrandLogoRequest extends FormRequest
{
    public const int MAX_KILOBYTES = 1024;

    public function authorize(): bool
    {
        $brand = $this->route('brand');

        return $brand instanceof Brand && ($this->user()?->can('update', $brand) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', File::types(['png', 'jpg', 'jpeg', 'webp'])->max(self::MAX_KILOBYTES), 'image'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['logo' => __('catalog.brands.logo')];
    }

    public function logo(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('logo');
    }
}
