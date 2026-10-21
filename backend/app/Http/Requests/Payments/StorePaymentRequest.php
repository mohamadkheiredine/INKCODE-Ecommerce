<?php

namespace App\Http\Requests\Payments;

use App\Enums\CardTestOutcome;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A payment on an Order: its Payment method and amount, with an optional note. A Card
 * charge also names the mock gateway's test option (approve or decline); card details are
 * refused outright, so none can ever be sent or stored.
 */
class StorePaymentRequest extends FormRequest
{
    public const string MAX_AMOUNT = '9999999999.99';

    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo(Permission::PaymentsRecord->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:'.self::MAX_AMOUNT],
            'card_outcome' => [
                Rule::requiredIf($this->input('method') === PaymentMethod::Card->value),
                Rule::prohibitedIf($this->input('method') !== PaymentMethod::Card->value),
                Rule::enum(CardTestOutcome::class),
            ],
            'note' => ['nullable', 'string', 'max:500'],
            'card_number' => ['prohibited'],
            'cvc' => ['prohibited'],
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
            'card_outcome' => __('payments.attributes.card_outcome'),
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

    public function cardOutcome(): ?CardTestOutcome
    {
        return $this->filled('card_outcome') ? CardTestOutcome::from($this->string('card_outcome')->toString()) : null;
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
