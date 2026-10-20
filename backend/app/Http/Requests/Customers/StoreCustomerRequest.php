<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use App\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new Customer: a name, an email or a phone (or both), and Tags by name. Unknown Tags
 * are made on the fly; the same Tag typed twice, in any case, counts once.
 */
class StoreCustomerRequest extends FormRequest
{
    /** Digits with the usual separators, optionally starting with "+". */
    public const string PHONE_PATTERN = '/^\+?[0-9][0-9 ().\-]{5,29}$/';

    public const int MAX_TAGS = 20;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Input is already trimmed (TrimStrings); emails are kept in lowercase.
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'string', 'email:rfc', 'max:255', Rule::unique('customers', 'email')->ignore($this->customer())],
            'phone' => ['nullable', 'required_without:email', 'string', 'regex:'.self::PHONE_PATTERN],
            'tags' => ['sometimes', 'array', 'max:'.self::MAX_TAGS],
            'tags.*' => ['required', 'string', 'max:'.Tag::MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => __('customers.email_or_phone'),
            'phone.required_without' => __('customers.email_or_phone'),
            'phone.regex' => __('customers.phone_format'),
        ];
    }

    /**
     * The Customer being edited; none while creating one.
     */
    public function customer(): ?Customer
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer ? $customer : null;
    }

    /**
     * @return array{name: string, email: string|null, phone: string|null}
     */
    public function details(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->filled('email') ? $this->string('email')->toString() : null,
            'phone' => $this->filled('phone') ? $this->string('phone')->toString() : null,
        ];
    }

    /**
     * The Tag names to carry, each once whatever its case; null when the request leaves the
     * Tags as they are.
     *
     * @return list<string>|null
     */
    public function tagNames(): ?array
    {
        if (! $this->has('tags')) {
            return null;
        }

        /** @var list<string> $names */
        $names = $this->validated('tags', []);

        return collect($names)
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values()
            ->all();
    }
}
