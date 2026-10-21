<?php

namespace Database\Seeders;

use App\Actions\Inventory\CreateReceipt;
use App\Actions\Orders\AdvanceOrder;
use App\Actions\Orders\ConfirmOrder;
use App\Actions\Orders\CreateOrder;
use App\Actions\Payments\RecordPayment;
use App\Actions\Shipments\CreateShipment;
use App\Actions\Shipments\DeliverShipment;
use App\Actions\Shipping\QuoteShipping;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Models\Carrier;
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
 * Older, Delivered and paid Orders for a few Customers who had none, so the demo shows
 * every Segment: two VIPs (one still buying, one lapsed), three At risk Customers (last
 * Order 110–170 days ago) and two Regulars. A Receipt first restocks the two dearest
 * Variants it sells. Everything goes through the same actions as the API, each on its own
 * past day, so totals, Stock movements, Payments and the audit log agree. Deterministic,
 * and recorded only once (skipped when an Order older than 30 days exists).
 */
class DemoCustomerHistorySeeder extends Seeder
{
    /**
     * Per Customer, each Order as [days ago, [[Variant position, quantity], ...]]: position 0
     * is the dearest Variant, 1 the next.
     *
     * @var list<list<array{int, list<array{int, int}>}>>
     */
    private const array HISTORY = [
        [[200, [[0, 4]]], [150, [[0, 4]]], [60, [[1, 2]]]],
        [[220, [[0, 4]]], [160, [[0, 4]]]],
        [[130, [[1, 1]]]],
        [[170, [[1, 2]]]],
        [[110, [[0, 1]]]],
        [[70, [[1, 1]]], [45, [[0, 1]]]],
        [[50, [[0, 2]]]],
    ];

    public function run(): void
    {
        if (Order::query()->where('placed_at', '<', now()->subDays(31))->exists()) {
            return;
        }

        $warehouse = Warehouse::query()->where('code', 'BEY-01')->firstOrFail();
        $variants = Variant::query()
            ->whereRelation('product', 'status', ProductStatus::Active)
            ->whereHas('stockBalances', fn ($query) => $query->where('warehouse_id', $warehouse->id))
            ->orderByDesc('price')
            ->orderBy('id')
            ->take(2)
            ->get()
            ->values();
        $addresses = $this->addressesWithoutOrders(count(self::HISTORY));
        $operations = User::query()->where('email', 'operations@inkcode.test')->firstOrFail();
        $sales = User::query()->where('email', 'sales@inkcode.test')->firstOrFail();
        $finance = User::query()->where('email', 'finance@inkcode.test')->firstOrFail();
        $carrier = Carrier::query()->orderBy('id')->firstOrFail();
        $today = Carbon::today();

        try {
            Carbon::setTestNow($today->copy()->subDays(230)->setTime(10, 0));
            Auth::setUser($operations);
            app(CreateReceipt::class)->handle([
                'warehouse_id' => $warehouse->id,
                'supplier_reference' => 'PO-HISTORY',
                'notes' => 'Stock for the earlier Orders',
                'lines' => [
                    ['variant_id' => $variants[0]->id, 'quantity' => 20, 'unit_cost' => bcmul($variants[0]->price, '0.45', 2)],
                    ['variant_id' => $variants[1]->id, 'quantity' => 10, 'unit_cost' => bcmul($variants[1]->price, '0.45', 2)],
                ],
            ]);

            foreach (self::HISTORY as $position => $orders) {
                $address = $addresses[$position];
                foreach ($orders as [$daysAgo, $lines]) {
                    Carbon::setTestNow($today->copy()->subDays($daysAgo)->setTime(11 + $position % 6, 30));
                    Auth::setUser($sales);
                    $order = app(CreateOrder::class)->handle([
                        'customer_id' => $address->customer_id,
                        'billing_address_id' => $address->id,
                        'shipping_address_id' => $address->id,
                        'warehouse_id' => $warehouse->id,
                        'shipping_method_id' => app(QuoteShipping::class)->handle($address->country_code, $address->city)->methods[0]->id,
                        'notes' => null,
                        'coupon_code' => null,
                        'lines' => array_map(fn (array $line): array => ['variant_id' => $variants[$line[0]]->id, 'quantity' => $line[1]], $lines),
                    ]);
                    $this->deliver($order, $operations, $carrier);

                    Auth::setUser($finance);
                    app(RecordPayment::class)->handle($order->refresh(), PaymentMethod::CashOnDelivery, $order->total, null, 'Collected by the courier', null);
                }
            }
        } finally {
            Carbon::setTestNow();
            Auth::forgetUser();
        }
    }

    /** Confirms, prepares and ships the Order in one Shipment, then delivers it, a day apart. */
    private function deliver(Order $order, User $operations, Carrier $carrier): void
    {
        Auth::setUser($operations);
        Carbon::setTestNow(Carbon::now()->addHours(2));
        $order = app(ConfirmOrder::class)->handle($order);
        foreach ([OrderStatus::Preparing, OrderStatus::ReadyForShipping] as $step) {
            Carbon::setTestNow(Carbon::now()->addDay());
            $order = app(AdvanceOrder::class)->handle($order, $step);
        }

        Carbon::setTestNow(Carbon::now()->addHours(3));
        $order = app(CreateShipment::class)->handle($order, [
            'carrier_id' => $carrier->id,
            'tracking_number' => sprintf('TRK%06d01', $order->id),
            'lines' => $order->lines->map(fn (OrderLine $line): array => ['line_id' => $line->id, 'quantity' => $line->quantity])->all(),
        ], $operations);

        Carbon::setTestNow(Carbon::now()->addDays(2));
        app(DeliverShipment::class)->handle($order, $order->shipments()->sole(), $operations);
    }

    /**
     * The last default shipping addresses, of Customers without Orders, that a Shipping
     * method delivers to.
     *
     * @return list<CustomerAddress>
     */
    private function addressesWithoutOrders(int $count): array
    {
        return CustomerAddress::query()
            ->where('is_default_shipping', true)
            ->whereNotIn('customer_id', Order::query()->select('customer_id'))
            ->orderByDesc('id')
            ->get()
            ->filter(fn (CustomerAddress $address): bool => app(QuoteShipping::class)->handle($address->country_code, $address->city)->methods !== [])
            ->take($count)
            ->values()
            ->all();
    }
}
