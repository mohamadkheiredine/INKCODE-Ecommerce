<?php

namespace App\Http\Requests\Returns;

use App\Enums\Permission;
use App\Http\Requests\Payments\StorePaymentRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Completing a Return with its Refund (refunds.issue): the amount (zero completes it
 * without one) and whether the Order's shipping is refunded too.
 */
class RefundReturnRequest extends FormRequest
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
            'amount' => ['required', 'decimal:0,2', 'min:0', 'max:'.StorePaymentRequest::MAX_AMOUNT],
            'refund_shipping' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => __('returns.attributes.amount'),
            'refund_shipping' => __('returns.attributes.refund_shipping'),
        ];
    }

    public function amount(): string
    {
        return bcadd($this->string('amount')->toString(), '0', 2);
    }

    public function refundShipping(): bool
    {
        return $this->boolean('refund_shipping');
    }

    public function idempotencyKey(): ?string
    {
        $key = (string) $this->header('Idempotency-Key', '');

        return $key === '' ? null : $key;
    }
}
