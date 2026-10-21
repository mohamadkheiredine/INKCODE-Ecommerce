<?php

namespace App\Http\Requests\Coupons;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Services\CouponValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new Coupon: a unique code (letters, digits, - and _; kept in capitals), a percentage
 * (up to 100) or fixed amount, a start date and optional end date, optional total and
 * per-Customer usage limits, a minimum order amount, and optional eligible Products and
 * Categories (none means every line).
 */
class StoreCouponRequest extends FormRequest
{
    public const int MAX_ELIGIBLE = 200;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Coupon::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => CouponValidator::normalize($this->input('code'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($this->coupon())],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => [
                'required', 'numeric', 'decimal:0,2', 'gt:0',
                $this->input('type') === CouponType::Percentage->value ? 'max:100' : 'max:9999999999',
            ],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'minimum_order_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'product_ids' => ['nullable', 'array', 'max:'.self::MAX_ELIGIBLE],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'category_ids' => ['nullable', 'array', 'max:'.self::MAX_ELIGIBLE],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => __('coupons.code_format')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('coupons.attributes.code'),
            'type' => __('coupons.attributes.type'),
            'value' => __('coupons.attributes.value'),
            'starts_on' => __('coupons.attributes.starts_on'),
            'ends_on' => __('coupons.attributes.ends_on'),
            'usage_limit' => __('coupons.attributes.usage_limit'),
            'per_customer_limit' => __('coupons.attributes.per_customer_limit'),
            'minimum_order_amount' => __('coupons.attributes.minimum_order_amount'),
            'product_ids' => __('coupons.attributes.product_ids'),
            'product_ids.*' => __('coupons.attributes.product_ids'),
            'category_ids' => __('coupons.attributes.category_ids'),
            'category_ids.*' => __('coupons.attributes.category_ids'),
        ];
    }

    /**
     * The Coupon being edited; none while creating one.
     */
    public function coupon(): ?Coupon
    {
        $coupon = $this->route('coupon');

        return $coupon instanceof Coupon ? $coupon : null;
    }

    /**
     * @return array{code: string, type: CouponType, value: string, starts_on: string, ends_on: string|null, usage_limit: int|null, per_customer_limit: int|null, minimum_order_amount: string, product_ids: list<int>, category_ids: list<int>}
     */
    public function values(): array
    {
        return [
            'code' => $this->string('code')->toString(),
            'type' => $this->enum('type', CouponType::class) ?? CouponType::Percentage,
            'value' => $this->string('value')->toString(),
            'starts_on' => $this->string('starts_on')->toString(),
            'ends_on' => $this->filled('ends_on') ? $this->string('ends_on')->toString() : null,
            'usage_limit' => $this->filled('usage_limit') ? $this->integer('usage_limit') : null,
            'per_customer_limit' => $this->filled('per_customer_limit') ? $this->integer('per_customer_limit') : null,
            'minimum_order_amount' => $this->filled('minimum_order_amount') ? $this->string('minimum_order_amount')->toString() : '0',
            'product_ids' => self::ids($this->validated('product_ids')),
            'category_ids' => self::ids($this->validated('category_ids')),
        ];
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $ids): array
    {
        return is_array($ids) ? array_values(array_map(fn (mixed $id): int => (int) $id, $ids)) : [];
    }
}
