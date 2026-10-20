<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use App\Models\CustomerNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A note about a Customer, in plain text.
 */
class StoreCustomerNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && ($this->user()?->can('update', $customer) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.CustomerNote::MAX_LENGTH],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }
}
