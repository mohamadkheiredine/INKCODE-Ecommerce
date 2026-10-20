<?php

namespace App\Http\Requests\Settings;

use App\Enums\DeductionStage;
use App\Enums\Locale;
use App\Enums\Permission;
use App\Enums\Theme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The whole settings form, saved at once. Only the Admin (settings.manage) may change it.
 * The limits keep every value one the other modules can work with: VAT in cents of a
 * percent, whole-day Return windows, whole-unit thresholds.
 */
class UpdateStoreSettingsRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:120'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'currency_code' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'currency_symbol' => ['required', 'string', 'max:8'],
            'vat_rate' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
            'deduction_stage' => ['required', Rule::enum(DeductionStage::class)],
            'return_window_days' => ['required', 'integer', 'between:1,365'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000'],
            'default_locale' => ['required', Rule::enum(Locale::class)],
            'default_theme' => ['required', Rule::enum(Theme::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        /** @var array<string, string> */
        return __('settings.fields');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['currency_code.regex' => __('settings.currency_code_format')];
    }

    /**
     * @return array{name: string, contact_email: string|null, contact_phone: string|null, address: string|null, currency_code: string, currency_symbol: string, vat_rate: string, deduction_stage: DeductionStage, return_window_days: int, low_stock_threshold: int, default_locale: Locale, default_theme: Theme}
     */
    public function values(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'contact_email' => $this->optionalString('contact_email'),
            'contact_phone' => $this->optionalString('contact_phone'),
            'address' => $this->optionalString('address'),
            'currency_code' => $this->string('currency_code')->toString(),
            'currency_symbol' => $this->string('currency_symbol')->trim()->toString(),
            'vat_rate' => $this->string('vat_rate')->toString(),
            'deduction_stage' => $this->enum('deduction_stage', DeductionStage::class) ?? DeductionStage::Shipped,
            'return_window_days' => $this->integer('return_window_days'),
            'low_stock_threshold' => $this->integer('low_stock_threshold'),
            'default_locale' => $this->enum('default_locale', Locale::class) ?? Locale::En,
            'default_theme' => $this->enum('default_theme', Theme::class) ?? Theme::Light,
        ];
    }

    private function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }
}
