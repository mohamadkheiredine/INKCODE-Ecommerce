<?php

namespace Database\Seeders;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ConfirmOrder;
use App\Actions\Orders\CreateOrder;
use App\Actions\Shipping\QuoteShipping;
use App\Enums\ProductStatus;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * A first set of demo Orders over the last four weeks, fulfilled from Beirut Central:
 * some Pending, most Confirmed (holding Reserved stock), a few Cancelled after confirming
 * (their reservations released). The demo Sales User enters them and the Operations User
 * confirms or cancels, through the same actions as the API, so prices, numbers, status
 * history, Stock movements and the audit log all agree. Deterministic, and recorded only
 * once (skipped when Orders exist).
 */
class DemoOrdersSeeder extends Seeder
{
    private const int ORDERS = 16;

    public function run(): void
    {
        if (Order::query()->exists()) {
            return;
        }

        $warehouse = Warehouse::query()->where('code', 'BEY-01')->firstOrFail();
        $variants = Variant::query()
            ->whereRelation('product', 'status', ProductStatus::Active)
            ->whereHas('stockBalances', fn ($query) => $query->where('warehouse_id', $warehouse->id)->where('available', '>=', 8))
            ->orderBy('id')
            ->get()
            ->values();
        $addresses = $this->shippableAddresses();
        $sales = User::query()->where('email', 'sales@inkcode.test')->firstOrFail();
        $operations = User::query()->where('email', 'operations@inkcode.test')->firstOrFail();

        $today = Carbon::today();
        try {
            for ($index = 0; $index < self::ORDERS; $index++) {
                // Each Order is entered (and its history recorded) on its own day, the last one today.
                Carbon::setTestNow($today->copy()->subDays(30 - $index * 2)->setTime(9 + $index % 8, 15 * ($index % 4)));
                $address = $addresses[$index % count($addresses)];

                Auth::setUser($sales);
                $order = app(CreateOrder::class)->handle([
                    'customer_id' => $address->customer_id,
                    'billing_address_id' => $address->id,
                    'shipping_address_id' => $address->id,
                    'warehouse_id' => $warehouse->id,
                    'shipping_method_id' => app(QuoteShipping::class)->handle($address->country_code, $address->city)->methods[0]->id,
                    'notes' => null,
                    // Every third Order uses the demo SAVE10 Coupon (counted once Confirmed).
                    'coupon_code' => $index % 3 === 1 && Coupon::query()->where('code', 'SAVE10')->exists() ? 'SAVE10' : null,
                    'lines' => $this->lines($variants->all(), $index),
                ]);

                Auth::setUser($operations);
                if ($index % 4 !== 0) {
                    Carbon::setTestNow(Carbon::now()->addHours(2));
                    app(ConfirmOrder::class)->handle($order);
                }
                if ($index % 4 === 3) {
                    Carbon::setTestNow(Carbon::now()->addDay());
                    app(CancelOrder::class)->handle($order, 'Customer changed their mind');
                }
            }
        } finally {
            Carbon::setTestNow();
            Auth::forgetUser();
        }
    }

    /**
     * One or two Variants, one or two units each, chosen in a fixed rotation.
     *
     * @param  list<Variant>  $variants
     * @return list<array{variant_id: int, quantity: int}>
     */
    private function lines(array $variants, int $index): array
    {
        $first = $variants[$index % count($variants)];
        $lines = [['variant_id' => $first->id, 'quantity' => 1 + $index % 2]];
        $second = $variants[($index * 3 + 1) % count($variants)];
        if ($index % 3 !== 0 && $second->id !== $first->id) {
            $lines[] = ['variant_id' => $second->id, 'quantity' => 1];
        }

        return $lines;
    }

    /**
     * Default shipping addresses a Shipping method delivers to, in a fixed order.
     *
     * @return list<CustomerAddress>
     */
    private function shippableAddresses(): array
    {
        return CustomerAddress::query()
            ->where('is_default_shipping', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (CustomerAddress $address): bool => app(QuoteShipping::class)->handle($address->country_code, $address->city)->methods !== [])
            ->values()
            ->all();
    }
}
