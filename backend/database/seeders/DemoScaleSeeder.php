<?php

namespace Database\Seeders;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\GenerateVariants;
use App\Actions\Inventory\CreateReceipt;
use App\Actions\Orders\AdvanceOrder;
use App\Actions\Orders\AssignOrder;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ConfirmOrder;
use App\Actions\Orders\CreateOrder;
use App\Actions\Payments\IssueRefund;
use App\Actions\Payments\RecordPayment;
use App\Actions\Returns\DecideRestock;
use App\Actions\Returns\MoveReturn;
use App\Actions\Returns\RefundReturn;
use App\Actions\Returns\RequestReturn;
use App\Actions\Shipments\CreateShipment;
use App\Actions\Shipments\DeliverShipment;
use App\Actions\Shipping\QuoteShipping;
use App\Enums\CardTestOutcome;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Enums\RestockDecision;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Variant;
use App\Models\Warehouse;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The demo store at the scale the brief asks for: 200 Products and 1,000 Orders over the
 * last year, on top of the hand-written demo records. Products get Variants from the shared
 * Attribute library and are stocked in the three active Warehouses by Receipts at different
 * unit costs, so Average cost has been re-weighted. Orders are placed by the demo Sales User
 * for the seeded Customers, a few more each month, then confirmed, shipped (sometimes in two
 * Shipments) and delivered by Operations, paid by Finance, and some are cancelled, returned
 * or refunded. Everything goes through the same actions as the API, so totals, stock,
 * Payments, reports and the audit log agree. Deterministic (a fixed random seed) and run
 * only once (skipped when the catalog is already at full scale). Feature tests turn it off
 * with DEMO_FULL_SCALE=false, so they keep the small hand-written demo store.
 */
class DemoScaleSeeder extends Seeder
{
    public const int PRODUCTS = 200;

    public const int ORDERS = 1000;

    private const int RANDOM_SEED = 28;

    private const int DAYS = 365;

    /**
     * Per Category slug: the product nouns, the price range, the Brands that make them and
     * the Attributes their Variants combine.
     *
     * @var array<string, array{list<string>, array{int, int}, list<string>, list<string>}>
     */
    private const array CATEGORIES = [
        'shirts' => [['Oxford Shirt', 'Linen Shirt', 'Polo', 'Henley', 'Flannel Shirt', 'Crew Tee'], [18, 65], ['nordic-wear', 'urban-thread', 'zest'], ['Color', 'Size']],
        'trousers' => [['Chinos', 'Cargo Pants', 'Slim Jeans', 'Joggers', 'Pleated Trousers'], [35, 95], ['nordic-wear', 'urban-thread'], ['Color', 'Size']],
        'jackets' => [['Bomber Jacket', 'Rain Shell', 'Denim Jacket', 'Puffer Vest', 'Field Jacket'], [70, 220], ['atlas-outdoor', 'nordic-wear'], ['Color', 'Size']],
        'dresses' => [['Wrap Dress', 'Midi Dress', 'Shirt Dress', 'Slip Dress'], [45, 140], ['zest', 'urban-thread'], ['Color', 'Size']],
        'sneakers' => [['Court Sneaker', 'Road Runner', 'High-top', 'Slip-on'], [55, 160], ['atlas-outdoor', 'urban-thread'], ['Color', 'Size']],
        'boots' => [['Chelsea Boot', 'Hiking Boot', 'Desert Boot'], [90, 240], ['atlas-outdoor', 'cedar-co'], ['Color', 'Size']],
        'sandals' => [['Slide', 'Strap Sandal', 'Espadrille'], [25, 70], ['zest', 'atlas-outdoor'], ['Color']],
        'bags' => [['Tote', 'Backpack', 'Crossbody Bag', 'Duffel'], [40, 190], ['cedar-co', 'zest'], ['Color', 'Material']],
        'hats' => [['Baseball Cap', 'Bucket Hat', 'Beanie', 'Fedora'], [15, 55], ['urban-thread', 'nordic-wear'], ['Color']],
        'watches' => [['Field Watch', 'Dress Watch', 'Chronograph'], [120, 420], ['cedar-co'], ['Material']],
        'kitchen' => [['Chef Knife', 'Cast-iron Pan', 'Espresso Cups', 'Cutting Board', 'Teapot'], [18, 140], ['lumen-home'], []],
        'decor' => [['Table Lamp', 'Linen Cushion', 'Ceramic Vase', 'Wall Clock', 'Throw Blanket'], [22, 120], ['lumen-home', 'cedar-co'], ['Color']],
    ];

    /** @var list<string> */
    private const array ADJECTIVES = [
        'Essential', 'Heritage', 'Coastal', 'Metro', 'Alpine', 'Everyday', 'Studio', 'Vintage', 'Nomad',
        'Harbor', 'Summit', 'Crafted', 'Weekend', 'Classic', 'Modern', 'Highland', 'Riviera', 'Cedar',
    ];

    /** @var list<string> */
    private const array TAGS = ['new arrival', 'bestseller', 'sustainable', 'limited edition', 'gift idea'];

    /**
     * Where Orders ship from, with their share of Orders and the stock each receives.
     *
     * @var array<string, array{int, array{int, int}}>
     */
    private const array WAREHOUSES = [
        'BEY-01' => [60, [20, 40]],
        'TRI-01' => [25, [10, 22]],
        'DXB-01' => [15, [8, 16]],
    ];

    /** @var array<string, User> */
    private array $staff = [];

    private ?Carbon $realNow = null;

    private Generator $faker;

    public function run(): void
    {
        if (Product::query()->count() >= self::PRODUCTS) {
            return;
        }

        $this->faker = FakerFactory::create();
        $this->faker->seed(self::RANDOM_SEED);
        $this->realNow = Carbon::now();
        foreach (['admin', 'sales', 'operations', 'finance', 'support', 'inventory'] as $role) {
            $this->staff[$role] = User::query()->where('email', "$role@inkcode.test")->firstOrFail();
        }

        try {
            $products = $this->createProducts(self::PRODUCTS - Product::query()->count());
            $this->stock($products);
            $this->placeOrders(
                max(0, self::ORDERS - Order::query()->count()),
                array_values(array_filter($products, fn (Product $product): bool => $product->status === ProductStatus::Active)),
            );
            // Staff have read their older alerts; only the last week's are still unread.
            DatabaseNotification::query()
                ->whereNull('read_at')
                ->where('created_at', '<', $this->realNow->copy()->subWeek())
                ->update(['read_at' => DB::raw('created_at')]);
        } finally {
            Carbon::setTestNow();
            Auth::forgetUser();
        }
    }

    /**
     * Creates the Products with their Variants, a year before today. Every 25th is a Draft
     * and another every 25th Archived: they are stocked like the rest but never sold.
     *
     * @return list<Product> in order
     */
    private function createProducts(int $count): array
    {
        $this->at($this->realNow->copy()->subDays(self::DAYS + 20)->setTime(9, 0));
        Auth::setUser($this->staff['admin']);

        $categories = Category::query()->whereIn('slug', array_keys(self::CATEGORIES))->pluck('id', 'slug');
        $brands = Brand::query()->pluck('id', 'slug');
        $values = AttributeValue::query()->with('attribute:id,name')->orderBy('position')->get()
            ->groupBy(fn (AttributeValue $value): string => $value->attribute->name);
        $taken = Product::query()->pluck('name')->flip()->all();
        $slugs = array_keys(self::CATEGORIES);

        $created = [];
        for ($index = 0; $index < $count; $index++) {
            $slug = $slugs[$index % count($slugs)];
            [$nouns, [$low, $high], $brandSlugs, $attributes] = self::CATEGORIES[$slug];
            $noun = $nouns[intdiv($index, count($slugs)) % count($nouns)];
            $name = $this->uniqueName($noun, $taken);
            $brand = $brandSlugs[$this->faker->numberBetween(0, count($brandSlugs) - 1)];
            $status = match ($index % 25) {
                7 => ProductStatus::Draft,
                19 => ProductStatus::Archived,
                default => ProductStatus::Active,
            };

            $product = app(CreateProduct::class)->handle([
                'name' => $name,
                'slug' => null,
                'description' => sprintf('%s by %s, made to last and easy to care for.', $name, str_replace('-', ' ', ucwords($brand, '-'))),
                'category_id' => $categories[$slug],
                'brand_id' => $brands[$brand] ?? null,
                'base_price' => $this->faker->numberBetween($low, $high).($this->faker->numberBetween(0, 1) === 0 ? '.00' : '.90'),
                'taxable' => $slug !== 'kitchen' || $index % 2 === 0,
                'tags' => $this->pick(self::TAGS, $this->faker->numberBetween(0, 2)),
                'seo_title' => "$name | INKCODE Store",
                'seo_description' => "Shop the $name: free returns within 30 days.",
                'status' => $status,
            ]);

            $valueIds = [];
            foreach ($attributes as $attribute) {
                $all = $values[$attribute]->all();
                $chosen = match ($attribute) {
                    // A run of 3 or 4 neighbouring sizes.
                    'Size' => array_slice($all, $this->faker->numberBetween(0, count($all) - 4), $this->faker->numberBetween(3, 4)),
                    'Color' => $this->pick($all, $this->faker->numberBetween(2, 3)),
                    default => $this->pick($all, 2),
                };
                $valueIds = [...$valueIds, ...array_map(fn (AttributeValue $value): int => $value->id, $chosen)];
            }
            app(GenerateVariants::class)->handle($product, $valueIds);
            $created[] = $product;
        }

        return $created;
    }

    /**
     * Receives the new Products in the three Warehouses, then restocks Beirut Central half a
     * year later at a higher cost. Every 23rd Variant gets only a few units in Beirut
     * Central, so the low-stock list has entries from day one.
     *
     * @param  list<Product>  $products
     */
    private function stock(array $products): void
    {
        Auth::setUser($this->staff['inventory']);
        $variants = Variant::query()->whereIn('product_id', array_map(fn (Product $product): int => $product->id, $products))->orderBy('id')->get();

        foreach (self::WAREHOUSES as $code => [, [$low, $high]]) {
            $this->at($this->realNow->copy()->subDays(self::DAYS + 10)->setTime(10, 0));
            $lines = [];
            foreach ($variants as $position => $variant) {
                $quantity = $position % 23 === 5 && $code === 'BEY-01' ? 3 : $this->faker->numberBetween($low, $high);
                if ($quantity > 0) {
                    $lines[] = ['variant_id' => $variant->id, 'quantity' => $quantity, 'unit_cost' => bcmul($variant->price, '0.'.$this->faker->numberBetween(38, 46), 2)];
                }
            }
            $this->receive($code, "PO-2025-$code", $lines);
        }

        $this->at($this->realNow->copy()->subDays(intdiv(self::DAYS, 2))->setTime(10, 0));
        $lines = [];
        foreach ($variants as $position => $variant) {
            if ($position % 23 !== 5) {
                $lines[] = ['variant_id' => $variant->id, 'quantity' => $this->faker->numberBetween(10, 20), 'unit_cost' => bcmul($variant->price, '0.'.$this->faker->numberBetween(47, 52), 2)];
            }
        }
        $this->receive('BEY-01', 'PO-2026-RESTOCK', $lines);
    }

    /**
     * @param  list<array{variant_id: int, quantity: int, unit_cost: string}>  $lines
     */
    private function receive(string $code, string $reference, array $lines): void
    {
        app(CreateReceipt::class)->handle([
            'warehouse_id' => Warehouse::query()->where('code', $code)->value('id'),
            'supplier_reference' => $reference,
            'notes' => null,
            'lines' => $lines,
        ]);
    }

    /**
     * Places the Orders, oldest first, a few more each month. The newer an Order, the more
     * likely it is still on its way; older ones are Delivered or Cancelled.
     *
     * @param  list<Product>  $products
     */
    private function placeOrders(int $count, array $products): void
    {
        $variants = Variant::query()->whereIn('product_id', array_map(fn (Product $product): int => $product->id, $products))
            ->orderBy('product_id')->orderBy('position')->get()->groupBy('product_id')->values()->all();
        $warehouses = Warehouse::query()->whereIn('code', array_keys(self::WAREHOUSES))->get()->keyBy('code');
        $addresses = $this->shippableAddresses();
        $carriers = Carrier::query()->orderBy('id')->get()->all();

        // Day offsets weighted towards today: the store grows over the year.
        $days = [];
        for ($index = 0; $index < $count; $index++) {
            $days[] = (int) floor(self::DAYS * (1 - $this->fraction() ** 0.75));
        }
        rsort($days);

        $joined = 0;
        foreach ($days as $index => $daysAgo) {
            $this->at($this->realNow->copy()->subDays($daysAgo)->setTime($this->faker->numberBetween(8, 21), $this->faker->numberBetween(0, 59)));
            // Only Customers who had joined by then can buy; before the first one joined, the
            // Order waits for them.
            while ($joined < count($addresses) && $this->joinedAt($addresses[$joined])->lte(Carbon::now())) {
                $joined++;
            }
            if ($joined === 0) {
                $this->at($this->joinedAt($addresses[0])->copy()->addHour());
                $joined = 1;
            }
            // Customers who joined early have had longest to come back, so they buy most often.
            $address = $addresses[(int) floor($joined * $this->fraction() ** 1.3)];
            // Orders of one day come at random hours: never before this Customer joined that day.
            if ($this->joinedAt($address)->gt(Carbon::now())) {
                $this->at($this->joinedAt($address)->copy()->addHour());
            }
            $code = $this->weighted(array_map(fn (array $warehouse): int => $warehouse[0], self::WAREHOUSES));
            $lines = $this->lines($variants, $warehouses[$code]->id);
            if ($lines === []) {
                $code = 'BEY-01';
                $lines = $this->lines($variants, $warehouses[$code]->id);
            }
            if ($lines === []) {
                continue;
            }

            $order = $this->create($address, $warehouses[$code], $lines, $daysAgo);
            $this->progress($order, $this->targetFor($daysAgo), $carriers, $index);
        }
    }

    /**
     * @param  list<array{variant_id: int, quantity: int}>  $lines
     */
    private function create(CustomerAddress $address, Warehouse $warehouse, array $lines, int $daysAgo): Order
    {
        Auth::setUser($this->staff['sales']);
        $methods = app(QuoteShipping::class)->handle($address->country_code, $address->city)->methods;
        $values = [
            'customer_id' => $address->customer_id,
            'billing_address_id' => $address->id,
            'shipping_address_id' => $address->id,
            'warehouse_id' => $warehouse->id,
            'shipping_method_id' => $methods[$this->faker->numberBetween(0, count($methods) - 1)]->id,
            'notes' => $this->faker->numberBetween(0, 9) === 0 ? 'Please call before delivery.' : null,
            'coupon_code' => $this->couponFor($daysAgo),
            'lines' => $lines,
        ];

        try {
            return app(CreateOrder::class)->handle($values);
        } catch (BusinessRuleViolation|ValidationException) {
            // The Coupon did not apply to this cart (minimum amount, usage limit): sell without it.
            return app(CreateOrder::class)->handle([...$values, 'coupon_code' => null]);
        }
    }

    /** Recent Orders sometimes use the running Coupons; older ones the summer sale. */
    private function couponFor(int $daysAgo): ?string
    {
        if ($this->faker->numberBetween(1, 100) > 12) {
            return null;
        }

        return match (true) {
            $daysAgo < 85 => 'SAVE10',
            $daysAgo < 145 => 'SUMMER20',
            default => null,
        };
    }

    /** Where an Order this old stands now; null means Cancelled. */
    private function targetFor(int $daysAgo): ?OrderStatus
    {
        $roll = $this->faker->numberBetween(1, 100);

        return match (true) {
            $daysAgo >= 14 => $roll <= 6 ? null : OrderStatus::Delivered,
            $daysAgo >= 5 => match (true) {
                $roll <= 5 => null,
                $roll <= 55 => OrderStatus::Delivered,
                $roll <= 80 => OrderStatus::Shipped,
                $roll <= 90 => OrderStatus::ReadyForShipping,
                default => OrderStatus::Preparing,
            },
            default => match (true) {
                $roll <= 25 => OrderStatus::Pending,
                $roll <= 50 => OrderStatus::Confirmed,
                $roll <= 70 => OrderStatus::Preparing,
                $roll <= 85 => OrderStatus::ReadyForShipping,
                default => OrderStatus::Shipped,
            },
        };
    }

    /**
     * Moves the Order through the Order flow to its target, paying it along the way the way
     * its Payment method does: Cards and bank transfers once Confirmed, Cash on delivery when
     * it is delivered.
     *
     * @param  list<Carrier>  $carriers
     */
    private function progress(Order $order, ?OrderStatus $target, array $carriers, int $index): void
    {
        $method = [PaymentMethod::CashOnDelivery, PaymentMethod::CashOnDelivery, PaymentMethod::Card, PaymentMethod::Card, PaymentMethod::Manual][$index % 5];

        if ($target === OrderStatus::Pending) {
            if ($index % 7 === 0) {
                $this->as('finance', fn () => app(RecordPayment::class)->handle($order, PaymentMethod::Manual, bcdiv($order->total, '2', 2), null, 'Deposit by bank transfer', null));
            }

            return;
        }
        if ($target === null && $index % 2 === 0) {
            $this->later($this->faker->numberBetween(2, 30) * 60);
            $this->as('operations', fn () => app(CancelOrder::class)->handle($order, 'Customer cancelled before confirmation'));

            return;
        }

        $this->later($this->faker->numberBetween(1, 6) * 3600);
        $order = $this->as('operations', fn () => app(ConfirmOrder::class)->handle($order));
        if ($index % 10 < 7) {
            $order = $this->as('operations', fn () => app(AssignOrder::class)->handle($order, $this->staff[$index % 3 === 0 ? 'sales' : 'operations']));
        }
        if ($target === null) {
            $this->later($this->faker->numberBetween(4, 30) * 3600);
            $this->as('operations', fn () => app(CancelOrder::class)->handle($order, 'Customer changed their mind'));

            return;
        }
        if ($method !== PaymentMethod::CashOnDelivery) {
            $this->pay($order, $method, $index);
        }

        foreach ([OrderStatus::Preparing, OrderStatus::ReadyForShipping] as $step) {
            if ($target === OrderStatus::Confirmed) {
                return;
            }
            $this->later($this->faker->numberBetween(6, 20) * 3600);
            $order = $this->as('operations', fn () => app(AdvanceOrder::class)->handle($order, $step));
            if ($target === $step) {
                return;
            }
        }

        // One Shipment, or one per line for some multi-line Orders (partial fulfillment).
        $lines = $order->lines->map(fn (OrderLine $line): array => ['line_id' => $line->id, 'quantity' => $line->quantity])->all();
        $parcels = count($lines) > 1 && $index % 8 === 0 ? array_map(fn (array $line): array => [$line], $lines) : [$lines];
        foreach ($parcels as $position => $parcel) {
            $this->later($this->faker->numberBetween(2, 8) * 3600);
            $order = app(CreateShipment::class)->handle($order, [
                'carrier_id' => $carriers[($index + $position) % count($carriers)]->id,
                'tracking_number' => sprintf('TRK%06d%02d', $order->id, $position + 1),
                'lines' => $parcel,
            ], $this->staff['operations']);
        }
        if ($target === OrderStatus::Shipped) {
            return;
        }

        foreach ($order->shipments()->get() as $shipment) {
            $this->later($this->faker->numberBetween(24, 72) * 3600);
            $order = app(DeliverShipment::class)->handle($order, $shipment, $this->staff['operations']);
        }
        if ($method === PaymentMethod::CashOnDelivery) {
            $this->as('finance', fn () => app(RecordPayment::class)->handle($order->refresh(), PaymentMethod::CashOnDelivery, $order->total, null, 'Collected by the courier', null));
        }

        $this->afterDelivery($order->refresh(), $index);
    }

    /** Card charges (one in eight after a declined attempt) or a bank transfer. */
    private function pay(Order $order, PaymentMethod $method, int $index): void
    {
        $this->later(30 * 60);
        $this->as('finance', function () use ($order, $method, $index): void {
            $record = app(RecordPayment::class);
            if ($method === PaymentMethod::Card) {
                if ($index % 8 === 2) {
                    $record->handle($order, PaymentMethod::Card, $order->total, CardTestOutcome::Decline, null, null);
                    $this->later(15 * 60);
                }
                $record->handle($order, PaymentMethod::Card, $order->total, CardTestOutcome::Approve, null, null);
            } else {
                $record->handle($order, PaymentMethod::Manual, $order->total, null, 'Bank transfer', null);
            }
        });
    }

    /**
     * A few Delivered Orders get a goodwill Refund, and a few others a Return of one unit:
     * most are received, decided and refunded, the rest stop at an earlier step.
     */
    private function afterDelivery(Order $order, int $index): void
    {
        if ($index % 37 === 3) {
            $this->later(24 * 3600);
            $this->as('finance', fn () => app(IssueRefund::class)->handle($order, PaymentMethod::Manual, bcdiv($order->total, '10', 2), 'Goodwill gesture for a late delivery', null));

            return;
        }
        if ($index % 29 !== 11) {
            return;
        }

        $line = $order->lines->first();
        $this->later(48 * 3600);
        $return = $this->as('support', fn (User $user) => app(RequestReturn::class)->handle($order, [
            'reason' => ReturnReason::cases()[$index % count(ReturnReason::cases())],
            'note' => null,
            'lines' => [['line_id' => $line->id, 'quantity' => 1]],
        ], $user));

        $outcome = intdiv($index, 29) % 6;
        if ($outcome === 5) {
            return;
        }
        $this->later(24 * 3600);
        if ($outcome === 4) {
            $this->as('support', fn () => app(MoveReturn::class)->handle($return, ReturnStatus::Rejected, 'Worn item, outside the return policy'));

            return;
        }
        $this->as('support', fn () => app(MoveReturn::class)->handle($return, ReturnStatus::Approved));

        $this->later(48 * 3600);
        $this->as('inventory', fn () => app(MoveReturn::class)->handle($return, ReturnStatus::Received));
        $decision = $outcome === 3 ? RestockDecision::WriteOff : RestockDecision::Restock;
        $this->as('inventory', fn () => app(DecideRestock::class)->handle($return, [$return->lines()->firstOrFail()->id => $decision]));

        $this->later(24 * 3600);
        $return->load(['lines.orderLine', 'order']);
        $suggested = $return->suggestedRefund(false);
        $refundable = $order->refresh()->netCaptured();
        $amount = bccomp($suggested, $refundable, 2) <= 0 ? $suggested : $refundable;
        if (bccomp($amount, '0', 2) > 0) {
            $this->as('finance', fn () => app(RefundReturn::class)->handle($return, $amount, false, null));
        }
    }

    /**
     * One to three lines from the Warehouse's stock; popular Products are chosen more often.
     *
     * @param  list<Collection<int, Variant>>  $variants  per Product
     * @return list<array{variant_id: int, quantity: int}>
     */
    private function lines(array $variants, int $warehouseId): array
    {
        $wanted = $this->weighted([1 => 55, 2 => 32, 3 => 13]);
        $lines = [];
        for ($attempt = 0; $attempt < 8 && count($lines) < $wanted; $attempt++) {
            $product = $variants[(int) floor(count($variants) * $this->fraction() ** 2)];
            $variant = $product[$this->faker->numberBetween(0, $product->count() - 1)];
            $quantity = $this->weighted([1 => 70, 2 => 22, 3 => 8]);
            $available = (int) StockBalance::query()->where('warehouse_id', $warehouseId)->where('variant_id', $variant->id)->value('available');
            if ($available >= $quantity && ! isset($lines[$variant->id])) {
                $lines[$variant->id] = ['variant_id' => $variant->id, 'quantity' => $quantity];
            }
        }

        return array_values($lines);
    }

    /**
     * Default shipping addresses a Shipping method delivers to, in the order their Customers
     * joined.
     *
     * @return list<CustomerAddress>
     */
    private function shippableAddresses(): array
    {
        return CustomerAddress::query()
            ->with('customer:id,created_at')
            ->where('is_default_shipping', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (CustomerAddress $address): bool => app(QuoteShipping::class)->handle($address->country_code, $address->city)->methods !== [])
            ->sortBy(fn (CustomerAddress $address): int => $this->joinedAt($address)->getTimestamp())
            ->values()
            ->all();
    }

    /** When the address could first be used: its Customer joined and it was added. */
    private function joinedAt(CustomerAddress $address): Carbon
    {
        $dates = array_filter([$address->customer->created_at, $address->created_at]);

        return $dates === [] ? $this->realNow->copy()->subDays(self::DAYS + 30) : max($dates);
    }

    /**
     * @param  array<string, true>  $taken
     */
    private function uniqueName(string $noun, array &$taken): string
    {
        $start = $this->faker->numberBetween(0, count(self::ADJECTIVES) - 1);
        foreach (array_keys(self::ADJECTIVES) as $offset) {
            $name = self::ADJECTIVES[($start + $offset) % count(self::ADJECTIVES)]." $noun";
            if (! isset($taken[$name])) {
                $taken[$name] = true;

                return $name;
            }
        }

        $name = "$noun ".(count($taken) + 1);
        $taken[$name] = true;

        return $name;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T> that many distinct items, in their original order
     */
    private function pick(array $items, int $count): array
    {
        $keys = array_keys($items);
        for ($index = count($keys) - 1; $index > 0; $index--) {
            $other = $this->faker->numberBetween(0, $index);
            [$keys[$index], $keys[$other]] = [$keys[$other], $keys[$index]];
        }
        $chosen = array_slice($keys, 0, $count);
        sort($chosen);

        return array_map(fn (int $key): mixed => $items[$key], $chosen);
    }

    /**
     * @template K of array-key
     *
     * @param  array<K, int>  $weights
     * @return K
     */
    private function weighted(array $weights): int|string
    {
        $roll = $this->faker->numberBetween(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }

    /**
     * @template T
     *
     * @param  callable(User): T  $step
     * @return T
     */
    private function as(string $role, callable $step): mixed
    {
        Auth::setUser($this->staff[$role]);

        return $step($this->staff[$role]);
    }

    /** A random number from 0 up to (not including) 1, from the seeded generator. */
    private function fraction(): float
    {
        return $this->faker->numberBetween(0, 999_999) / 1_000_000;
    }

    /** Moves the clock there, never past the real now. */
    private function at(Carbon $moment): void
    {
        Carbon::setTestNow($moment->copy()->min($this->realNow));
    }

    private function later(int $seconds): void
    {
        $this->at(Carbon::now()->addSeconds($seconds));
    }
}
