<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use App\Support\LogoFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The store's logo (see {@see LogoFile::rules()}). Only the Admin may change it.
 */
class StoreStoreLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsManage->value) ?? false;
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
        return ['logo' => __('settings.fields.logo')];
    }

    public function logo(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('logo');
    }
}
