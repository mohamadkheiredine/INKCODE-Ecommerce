<?php

namespace App\Http\Requests\Orders;

use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\Variant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new Order: the Customer, two of that Customer's addresses (billing and shipping), an
 * active Fulfilling warehouse, a Shipping method, optional notes, and one line per Variant
 * of an Active Product. Whether the method delivers to the shipping address is checked
 * when the Order is priced. It is safe to retry with an `Idempotency-Key`.
 */
class StoreOrderRequest extends FormRequest
{
    public const int MAX_LINES = 100;

    public const int MAX_QUANTITY = 10000;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $customersAddress = Rule::exists('customer_addresses', 'id')->where('customer_id', $this->integer('customer_id'));

        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'billing_address_id' => ['required', 'integer', $customersAddress],
            'shipping_address_id' => ['required', 'integer', $customersAddress],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            ...self::lineRules(),
        ];
    }

    /**
     * The lines of an Order (also quoted before it exists): each Variant of an Active
     * Product, once, with a quantity.
     *
     * @return array<string, array<mixed>>
     */
    public static function lineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*' => ['array:variant_id,quantity'],
            'lines.*.variant_id' => ['required', 'integer', 'distinct', self::sellableVariant()],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'billing_address_id.exists' => __('orders.address_not_customers'),
            'shipping_address_id.exists' => __('orders.address_not_customers'),
            'warehouse_id.exists' => __('inventory.receipts.warehouse_unavailable'),
            ...self::lineMessages(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function lineMessages(): array
    {
        return ['lines.*.variant_id.distinct' => __('orders.variant_repeated')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => __('orders.attributes.customer_id'),
            'billing_address_id' => __('orders.attributes.billing_address_id'),
            'shipping_address_id' => __('orders.attributes.shipping_address_id'),
            'warehouse_id' => __('orders.attributes.warehouse_id'),
            'shipping_method_id' => __('orders.attributes.shipping_method_id'),
            ...self::lineAttributes(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function lineAttributes(): array
    {
        return [
            'lines' => __('orders.attributes.lines'),
            'lines.*.variant_id' => __('orders.attributes.variant_id'),
            'lines.*.quantity' => __('orders.attributes.quantity'),
        ];
    }

    /**
     * @return array{customer_id: int, billing_address_id: int, shipping_address_id: int, warehouse_id: int, shipping_method_id: int, notes: string|null, lines: list<array{variant_id: int, quantity: int}>}
     */
    public function values(): array
    {
        return [
            'customer_id' => $this->integer('customer_id'),
            'billing_address_id' => $this->integer('billing_address_id'),
            'shipping_address_id' => $this->integer('shipping_address_id'),
            'warehouse_id' => $this->integer('warehouse_id'),
            'shipping_method_id' => $this->integer('shipping_method_id'),
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
            'lines' => self::linesFrom($this),
        ];
    }

    /**
     * @return list<array{variant_id: int, quantity: int}>
     */
    public static function linesFrom(FormRequest $request): array
    {
        /** @var array<int, array{variant_id: int|string, quantity: int|string}> $lines */
        $lines = $request->validated('lines');

        return array_map(fn (array $line): array => [
            'variant_id' => (int) $line['variant_id'],
            'quantity' => (int) $line['quantity'],
        ], array_values($lines));
    }

    private static function sellableVariant(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $sellable = is_numeric($value) && Variant::query()
                ->whereKey((int) $value)
                ->whereRelation('product', 'status', ProductStatus::Active)
                ->exists();

            if (! $sellable) {
                $fail(__('orders.variant_unavailable'));
            }
        };
    }
}
