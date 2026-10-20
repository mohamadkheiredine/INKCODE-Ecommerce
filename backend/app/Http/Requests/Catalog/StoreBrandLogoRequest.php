<?php

namespace App\Http\Requests\Catalog;

use App\Models\Brand;
use App\Support\LogoFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * A Brand's logo (see {@see LogoFile::rules()}).
 */
class StoreBrandLogoRequest extends FormRequest
{
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
        return ['logo' => LogoFile::rules()];
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
