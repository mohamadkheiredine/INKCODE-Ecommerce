<?php

namespace App\Http\Requests\Inventory;

use App\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new Warehouse: a unique name, a unique short code (stored in capitals, for example
 * "BEY-01") and an address. It starts active unless `is_active` says otherwise.
 */
class StoreWarehouseRequest extends FormRequest
{
    public const string CODE_PATTERN = '/^[A-Z0-9-]{2,16}$/';

    public function authorize(): bool
    {
        return $this->user()?->can('create', Warehouse::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper($this->input('code'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('warehouses', 'name')->ignore($this->warehouse())],
            'code' => ['required', 'string', 'regex:'.self::CODE_PATTERN, Rule::unique('warehouses', 'code')->ignore($this->warehouse())],
            'address' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => __('inventory.warehouses.code_format')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('inventory.attributes.code'),
            'address' => __('inventory.attributes.address'),
        ];
    }

    /**
     * The Warehouse being edited; none while creating one.
     */
    public function warehouse(): ?Warehouse
    {
        $warehouse = $this->route('warehouse');

        return $warehouse instanceof Warehouse ? $warehouse : null;
    }

    /**
     * @return array{name: string, code: string, address: string, is_active: bool}
     */
    public function values(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'code' => $this->string('code')->toString(),
            'address' => $this->string('address')->toString(),
            'is_active' => $this->boolean('is_active', $this->warehouse()->is_active ?? true),
        ];
    }
}
