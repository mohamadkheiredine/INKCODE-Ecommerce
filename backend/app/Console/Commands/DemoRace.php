<?php

namespace App\Console\Commands;

use App\Actions\Inventory\CreateReceipt;
use App\Actions\Orders\CreateOrder;
use App\Actions\Shipping\QuoteShipping;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Symfony\Component\Process\Process;

/**
 * Demo scenario 2: proves that simultaneous confirmations never oversell. It stocks a new
 * Variant with exactly `--available` units, creates `--workers` Pending Orders of one unit
 * each, then confirms them all at the same moment from separate PHP processes (each with
 * its own database connection, like separate web requests). Exactly `--available`
 * confirmations succeed, the rest are refused with `insufficient_stock`, and the balance
 * still equals the sum of its Stock movements.
 *
 * It writes real rows: an Archived "demo:race" Product (kept out of the catalog), a
 * Receipt and the Orders, all visible in the app afterwards.
 */
#[Signature('demo:race {--workers=20 : Simultaneous confirmations} {--available=5 : Units in stock}')]
#[Description('Confirm many Orders for the last units at once and show that none oversells')]
class DemoRace extends Command
{
    /** Seconds the workers get to boot before they all confirm at the same moment. */
    private const int START_DELAY = 5;

    public function handle(CreateReceipt $createReceipt, CreateOrder $createOrder, QuoteShipping $quoteShipping, AuthFactory $auth): int
    {
        $workers = max(1, (int) $this->option('workers'));
        $available = max(0, (int) $this->option('available'));

        $warehouse = Warehouse::query()->where('is_active', true)->orderBy('id')->first();
        $category = Category::query()->orderBy('id')->first();
        $address = $this->shippableAddress($quoteShipping);
        if ($warehouse === null || $category === null || $address === null) {
            $this->error('Seed the store first (php artisan migrate --seed): demo:race needs an active Warehouse, a Category and a Customer address that a Shipping method delivers to.');

            return self::FAILURE;
        }

        $admin = User::query()->role(Role::Admin->value)->orderBy('id')->first();
        if ($admin !== null) {
            $auth->guard()->setUser($admin);
        }

        $variant = $this->raceVariant($category);
        if ($available > 0) {
            $createReceipt->handle([
                'warehouse_id' => $warehouse->id,
                'supplier_reference' => 'DEMO-RACE',
                'notes' => 'Stock for php artisan demo:race',
                'lines' => [['variant_id' => $variant->id, 'quantity' => $available, 'unit_cost' => '1.00']],
            ]);
        }

        $method = $quoteShipping->handle($address->country_code, $address->city)->methods[0];
        $orders = collect(range(1, $workers))->map(fn (): Order => $createOrder->handle([
            'customer_id' => $address->customer_id,
            'billing_address_id' => $address->id,
            'shipping_address_id' => $address->id,
            'warehouse_id' => $warehouse->id,
            'shipping_method_id' => $method->id,
            'notes' => 'demo:race',
            'lines' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]));

        $this->info("$workers Pending Orders for 1 × $variant->sku each; $available Available in $warehouse->name. Confirming them all at once…");

        $outcomes = $this->confirmAtOnce($orders->all(), $admin);

        return $this->report($outcomes, $variant, $warehouse, $available) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Starts one worker process per Order; they all wait for the same start time.
     *
     * @param  list<Order>  $orders
     * @return array<string, string> the outcome per Order number: `confirmed` or the refusal's code
     */
    private function confirmAtOnce(array $orders, ?User $admin): array
    {
        $startAt = microtime(true) + self::START_DELAY;
        $processes = [];
        foreach ($orders as $order) {
            $process = new Process(
                [PHP_BINARY, 'artisan', 'demo:race-worker', (string) $order->id, (string) $startAt, (string) ($admin === null ? 0 : $admin->id)],
                base_path(),
                ['APP_ENV' => app()->environment(), 'DB_DATABASE' => config('database.connections.mysql.database')],
                timeout: 120,
            );
            $process->start();
            $processes[$order->number] = $process;
        }

        $outcomes = [];
        foreach ($processes as $number => $process) {
            $process->wait();
            $outcomes[$number] = trim($process->getOutput()) ?: 'error: '.trim($process->getErrorOutput());
        }

        return $outcomes;
    }

    /**
     * @param  array<string, string>  $outcomes
     */
    private function report(array $outcomes, Variant $variant, Warehouse $warehouse, int $available): bool
    {
        $confirmed = count(array_filter($outcomes, fn (string $outcome): bool => $outcome === 'confirmed'));
        $refused = count(array_filter($outcomes, fn (string $outcome): bool => $outcome === 'insufficient_stock'));

        $balance = StockBalance::query()->where(['variant_id' => $variant->id, 'warehouse_id' => $warehouse->id])->firstOrFail();
        $movements = StockMovement::query()->where(['variant_id' => $variant->id, 'warehouse_id' => $warehouse->id]);
        $sumsMatch = $balance->on_hand === (int) $movements->clone()->sum('on_hand_delta')
            && $balance->reserved === (int) $movements->clone()->sum('reserved_delta');
        $confirmedOrders = Order::query()->whereIn('number', array_keys($outcomes))->where('status', OrderStatus::Confirmed)->count();

        $this->table(['Order', 'Outcome'], collect($outcomes)->map(fn (string $outcome, string $number): array => [$number, $outcome])->values()->all());
        $this->table(['Confirmed', 'Refused (insufficient_stock)', 'On-hand', 'Reserved', 'Available', 'Balance = Σ movements'], [[
            $confirmed, $refused, $balance->on_hand, $balance->reserved, $balance->on_hand - $balance->reserved, $sumsMatch ? 'yes' : 'NO',
        ]]);

        $expected = min($available, count($outcomes));
        $passed = $confirmed === $expected && $confirmedOrders === $expected && $refused === count($outcomes) - $expected
            && $balance->reserved === $expected && $sumsMatch;

        $passed
            ? $this->info("No overselling: exactly $expected of ".count($outcomes).' confirmations succeeded.')
            : $this->error('Unexpected outcome: see the tables above.');

        return $passed;
    }

    private function raceVariant(Category $category): Variant
    {
        $stamp = now()->format('YmdHis');
        $product = Product::query()->create([
            'name' => "demo:race $stamp",
            'slug' => "demo-race-$stamp",
            'description' => 'Created by php artisan demo:race.',
            'category_id' => $category->id,
            'base_price' => '10.00',
            'taxable' => true,
            'tags' => [],
            // Archived, so it never shows up for sale; Orders keep their own snapshot.
            'status' => ProductStatus::Archived,
        ]);

        return $product->variants()->create([
            'sku' => "RACE-$stamp",
            'combination_key' => '',
            'price' => '10.00',
            'average_cost' => '1.0000',
            'position' => 0,
        ]);
    }

    /**
     * A default shipping address that some active Shipping method delivers to.
     */
    private function shippableAddress(QuoteShipping $quoteShipping): ?CustomerAddress
    {
        foreach (CustomerAddress::query()->where('is_default_shipping', true)->orderBy('id')->limit(50)->get() as $address) {
            if ($quoteShipping->handle($address->country_code, $address->city)->methods !== []) {
                return $address;
            }
        }

        return null;
    }
}
