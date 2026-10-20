<?php

namespace App\Http\Requests\Customers;

use App\Enums\AddressPurpose;
use App\Models\Customer;
use App\Support\Countries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An address for a Customer: who receives it, where, and whether it becomes the default
 * billing or shipping address. The country is an ISO code ("LB"), in any case.
 */
class StoreCustomerAddressRequest extends FormRequest
{
    private const array FIELDS = ['label', 'recipient_name', 'phone', 'country_code', 'city', 'line1', 'line2', 'postal_code'];

    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && ($this->user()?->can('update', $customer) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['country_code' => Countries::normalize($this->input('country_code'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:'.StoreCustomerRequest::PHONE_PATTERN],
            'country_code' => ['required', 'string', Countries::rule()],
            'city' => ['required', 'string', 'max:100'],
            'line1' => ['required', 'string', 'max:150'],
            'line2' => ['nullable', 'string', 'max:150'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            AddressPurpose::Billing->defaultColumn() => ['sometimes', 'boolean'],
            AddressPurpose::Shipping->defaultColumn() => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => __('customers.phone_format')];
    }

    /**
     * The address itself, blanks saved as null.
     *
     * @return array<string, string|null>
     */
    public function fields(): array
    {
        $fields = [];
        foreach (self::FIELDS as $field) {
            $fields[$field] = $this->filled($field) ? $this->string($field)->toString() : null;
        }

        return $fields;
    }

    /**
     * For each purpose: true to make this the default, false to stop being it, null when not sent.
     *
     * @return array<value-of<AddressPurpose>, bool|null>
     */
    public function defaults(): array
    {
        $defaults = [];
        foreach (AddressPurpose::cases() as $purpose) {
            $column = $purpose->defaultColumn();
            $defaults[$purpose->value] = $this->has($column) ? $this->boolean($column) : null;
        }

        return $defaults;
    }
}
