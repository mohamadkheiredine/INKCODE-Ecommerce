<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentMethod;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A Refund on an Order: how it goes back (Payment method), how much, and an optional note.
 */
class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::RefundsIssue->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:'.StorePaymentRequest::MAX_AMOUNT],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'method' => __('payments.attributes.method'),
            'amount' => __('payments.attributes.amount'),
            'note' => __('payments.attributes.note'),
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->string('method')->toString());
    }

    public function amount(): string
    {
        return bcadd($this->string('amount')->toString(), '0', 2);
    }

    public function note(): ?string
    {
        return $this->filled('note') ? $this->string('note')->trim()->toString() : null;
    }

    public function idempotencyKey(): ?string
    {
        $key = (string) $this->header('Idempotency-Key', '');

        return $key === '' ? null : $key;
    }
}
