<?php

namespace App\Actions\Orders;

use App\Actions\Shipping\QuoteShipping;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Variant;
use App\Services\AuditTrail;
use App\Services\OrderPricing;
use App\Services\Settings;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Pending Order: prices its lines (VAT per line, untaxed shipping), snapshots the
 * addresses, Shipping method and Variants, gives it the next Order number of its year,
 * and records its first status and an audit entry, all in one transaction. Stock is not
 * touched until the Order is Confirmed ({@see ConfirmOrder}).
 */
final class CreateOrder
{
    public function __construct(
        private readonly QuoteShipping $quoteShipping,
        private readonly OrderPricing $pricing,
        private readonly Settings $settings,
        private readonly AuditTrail $auditTrail,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  array{customer_id: int, billing_address_id: int, shipping_address_id: int, warehouse_id: int, shipping_method_id: int, notes: string|null, lines: list<array{variant_id: int, quantity: int}>}  $values
     *
     * @throws BusinessRuleViolation 422 `shipping_method_unavailable` when the method does not deliver to the shipping address
     */
    public function handle(array $values): Order
    {
        $billing = CustomerAddress::query()->findOrFail($values['billing_address_id']);
        $shipping = CustomerAddress::query()->findOrFail($values['shipping_address_id']);
        $method = $this->quoteShipping->handle($shipping->country_code, $shipping->city)->method($values['shipping_method_id']);

        $variants = Variant::query()
            ->with(['product:id,name,taxable', 'attributeValues.attribute:id,name'])
            ->findMany(array_column($values['lines'], 'variant_id'))
            ->keyBy('id');
        $priced = $this->pricing->price(
            array_map(fn (array $line): array => ['variant' => $variants[$line['variant_id']], 'quantity' => $line['quantity']], $values['lines']),
            $method->price,
        );

        return DB::transaction(function () use ($values, $billing, $shipping, $method, $priced): Order {
            $userId = $this->auth->guard()->id();
            $userId = $userId === null ? null : (int) $userId;
            $placedAt = Carbon::now();

            $order = Order::query()->create([
                'number' => $this->nextNumber($placedAt),
                'status' => OrderStatus::Pending,
                'customer_id' => $values['customer_id'],
                'warehouse_id' => $values['warehouse_id'],
                'shipping_method_id' => $method->id,
                'billing_address' => self::snapshot($billing),
                'shipping_address' => self::snapshot($shipping),
                'shipping_method_name' => $method->name,
                'currency_code' => $this->settings->currencyCode(),
                'vat_rate' => $priced['vat_rate'],
                'items_total' => $priced['items_total'],
                'discount_total' => $priced['discount_total'],
                'vat_total' => $priced['vat_total'],
                'shipping_total' => $priced['shipping_total'],
                'total' => $priced['total'],
                'notes' => $values['notes'],
                'user_id' => $userId,
                'placed_at' => $placedAt,
            ]);
            $order->lines()->createMany($priced['lines']);
            $order->statusChanges()->create(['from_status' => null, 'to_status' => OrderStatus::Pending, 'user_id' => $userId]);

            $this->auditTrail->created($order);

            return $order;
        });
    }

    /**
     * ORD-<year>-<six digits>, from a per-year counter. The counter's row is locked until
     * the Order commits, so two Orders never get the same number and none is skipped.
     */
    private function nextNumber(Carbon $placedAt): string
    {
        $year = (int) $placedAt->format('Y');
        DB::table('order_number_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
        $last = (int) DB::table('order_number_sequences')->where('year', $year)->lockForUpdate()->value('last_number');
        DB::table('order_number_sequences')->where('year', $year)->update(['last_number' => $last + 1]);

        return sprintf('ORD-%d-%06d', $year, $last + 1);
    }

    /**
     * @return array{recipient_name: string, phone: string|null, country_code: string, city: string, line1: string, line2: string|null, postal_code: string|null}
     */
    public static function snapshot(CustomerAddress $address): array
    {
        return [
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'country_code' => $address->country_code,
            'city' => $address->city,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'postal_code' => $address->postal_code,
        ];
    }
}
