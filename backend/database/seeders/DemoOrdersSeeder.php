<?php

namespace Database\Seeders;

use App\Actions\Orders\AdvanceOrder;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ConfirmOrder;
use App\Actions\Orders\CreateOrder;
use App\Actions\Shipments\CreateShipment;
use App\Actions\Shipments\DeliverShipment;
use App\Actions\Shipping\QuoteShipping;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\Carrier;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * A first set of demo Orders over the last four weeks, fulfilled from Beirut Central:
 * some Pending, Confirmed ones at every step of the Order flow (Preparing, Ready for shipping
 * with and without a first Shipment, Shipped, Delivered in one or two Shipments), a few
 * Cancelled after confirming (their reservations released). The demo Sales User enters them
 * and the Operations User confirms, moves, ships or cancels, through the same actions as the API, so prices, numbers, status
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
                $this->fulfil($order, $index, $operations);
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
     * Moves some Confirmed Orders on, each a day apart: 9 is Preparing; 6 is Ready for
     * shipping; 10 has shipped its first line; 2 is Shipped; 5 is Delivered in one
     * Shipment and 1 in two (one per line).
     */
    private function fulfil(Order $order, int $index, User $operations): void
    {
        $target = match ($index) {
            9 => OrderStatus::Preparing,
            1, 2, 5, 6, 10 => OrderStatus::ReadyForShipping,
            default => null,
        };
        if ($target === null) {
            return;
        }

        foreach ([OrderStatus::Preparing, OrderStatus::ReadyForShipping] as $step) {
            Carbon::setTestNow(Carbon::now()->addDay());
            $order = app(AdvanceOrder::class)->handle($order, $step);
            if ($step === $target) {
                break;
            }
        }

        $lines = $order->lines->map(fn (OrderLine $line): array => ['line_id' => $line->id, 'quantity' => $line->quantity])->all();
        $parcels = match ($index) {
            1 => array_map(fn (array $line): array => [$line], $lines),
            2, 5 => [$lines],
            10 => [[$lines[0]]],
            default => [],
        };
        $carriers = Carrier::query()->orderBy('id')->get();
        foreach ($parcels as $position => $parcel) {
            Carbon::setTestNow(Carbon::now()->addHours(3));
            $order = app(CreateShipment::class)->handle($order, [
                'carrier_id' => $carriers[($index + $position) % $carriers->count()]->id,
                'tracking_number' => sprintf('TRK%06d%02d', $order->id, $position + 1),
                'lines' => $parcel,
            ], $operations);
        }

        if (in_array($index, [1, 5], true)) {
            foreach ($order->shipments()->get() as $shipment) {
                Carbon::setTestNow(Carbon::now()->addDays(2));
                $order = app(DeliverShipment::class)->handle($order, $shipment, $operations);
            }
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
