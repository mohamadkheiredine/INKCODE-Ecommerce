<?php

use App\Actions\Orders\ConfirmOrder;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->warehouse = Warehouse::factory()->create();
    $this->shirt = Variant::factory()->create(['price' => '19.99', 'sku' => 'TEE-RED-M']);
    $this->mug = Variant::factory()->for(Product::factory()->state(['taxable' => false]))->create(['price' => '10.00', 'sku' => 'MUG-01']);
    receiveStock($this->warehouse, [[$this->shirt, 50, '5.00'], [$this->mug, 50, '3.00']]);
});

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function quoteFor(array $extra = []): array
{
    $test = test();

    return test()->postJson('/api/v1/orders/quote', [
        'lines' => [['variant_id' => $test->shirt->id, 'quantity' => 3], ['variant_id' => $test->mug->id, 'quantity' => 1]],
        ...$extra,
    ])->assertOk()->json('data');
}

describe('pricing an Order with a Coupon', function () {
    it('spreads a percentage across the lines by value, puts the cent left over on the largest line and taxes the discounted amounts', function () {
        Coupon::factory()->create(['code' => 'SAVE10']);
        $this->actingAs(userWithRole(Role::Sales));

        // Items 59.97 + 10.00 = 69.97; 10% = 6.997 → 7.00. Shares 5.99 + 1.00 = 6.99, so the
        // remaining cent goes to the shirts: 6.00. VAT 5% on 53.97 = 2.70; the mug is untaxed.
        $quote = quoteFor(['coupon_code' => 'save10']);

        expect($quote['coupon'])->toBe(['code' => 'SAVE10', 'is_valid' => true, 'type' => 'percentage', 'value' => '10.00'])
            ->and(array_column($quote['lines'], 'discount_amount'))->toBe(['6.00', '1.00'])
            ->and(array_column($quote['lines'], 'vat_amount'))->toBe(['2.70', '0.00'])
            ->and(array_column($quote['lines'], 'line_total'))->toBe(['56.67', '9.00'])
            ->and([$quote['items_total'], $quote['discount_total'], $quote['vat_total'], $quote['total']])
            ->toBe(['69.97', '7.00', '2.70', '65.67']);
    });

    it('discounts only eligible Products and Categories (a parent Category includes its children), at most their value', function () {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $book = Variant::factory()->for(Product::factory()->state(['category_id' => $child->id]))->create(['price' => '8.00']);
        Coupon::factory()->fixed('50.00')->create(['code' => 'BIG'])->categories()->attach($parent);
        Coupon::factory()->fixed('3.00')->create(['code' => 'MUGS'])->products()->attach($this->mug->product_id);
        $this->actingAs(userWithRole(Role::Sales));

        $lines = [['variant_id' => $this->shirt->id, 'quantity' => 1], ['variant_id' => $book->id, 'quantity' => 2]];
        $big = $this->postJson('/api/v1/orders/quote', ['lines' => $lines, 'coupon_code' => 'BIG'])->json('data');
        $mugs = quoteFor(['coupon_code' => 'MUGS']);

        expect(array_column($big['lines'], 'discount_amount'))->toBe(['0.00', '16.00'])
            ->and(array_column($big['lines'], 'coupon_eligible'))->toBe([false, true])
            ->and($big['discount_total'])->toBe('16.00')
            ->and(array_column($mugs['lines'], 'discount_amount'))->toBe(['0.00', '3.00']);
    });

    it('reports why a Coupon does not apply and prices without it', function (Closure $makeCoupon, string $reason, string $message) {
        $makeCoupon();
        $this->actingAs(userWithRole(Role::Sales));

        $quote = quoteFor(['coupon_code' => 'TRYME', 'customer_id' => Customer::query()->value('id')]);

        expect($quote['coupon'])->toBe(['code' => 'TRYME', 'is_valid' => false, 'reason' => $reason, 'message' => $message])
            ->and($quote['discount_total'])->toBe('0.00');
    })->with([
        'unknown' => [fn () => null, 'not_found', 'There is no Coupon with the code TRYME.'],
        'not started' => [fn () => Coupon::factory()->create(['code' => 'TRYME', 'starts_on' => '2099-01-01']), 'not_started', 'Coupon TRYME can be used from 2099-01-01.'],
        'expired' => [fn () => Coupon::factory()->create(['code' => 'TRYME', 'starts_on' => '2026-01-01', 'ends_on' => '2026-02-01']), 'expired', 'Coupon TRYME expired on 2026-02-01.'],
        'below the minimum' => [fn () => Coupon::factory()->create(['code' => 'TRYME', 'minimum_order_amount' => '70.00']), 'below_minimum', 'Coupon TRYME needs items worth at least 70.00 USD.'],
        'no eligible items' => [fn () => Coupon::factory()->create(['code' => 'TRYME'])->products()->attach(Product::factory()->create()), 'no_eligible_items', 'None of the items in this Order are eligible for Coupon TRYME.'],
        'total limit reached' => [function () {
            $coupon = Coupon::factory()->create(['code' => 'TRYME', 'usage_limit' => 1]);
            app(ConfirmOrder::class)->handle(placeOrder(test()->warehouse, [[test()->shirt, 1]], couponCode: $coupon->code));
        }, 'limit_reached', 'Coupon TRYME has reached its usage limit.'],
        'Customer limit reached' => [function () {
            $coupon = Coupon::factory()->create(['code' => 'TRYME', 'per_customer_limit' => 1]);
            app(ConfirmOrder::class)->handle(placeOrder(test()->warehouse, [[test()->shirt, 1]], couponCode: $coupon->code));
        }, 'customer_limit_reached', 'This Customer has already used Coupon TRYME as many times as allowed.'],
    ]);

    it('refuses to create an Order with a Coupon that does not apply', function () {
        Coupon::factory()->create(['code' => 'LATER', 'starts_on' => Carbon::tomorrow()->toDateString()]);
        $order = placeOrder($this->warehouse, [[$this->shirt, 1]]);

        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/orders', [
                'customer_id' => $order->customer_id,
                'billing_address_id' => $order->customer->addresses()->value('id'),
                'shipping_address_id' => $order->customer->addresses()->value('id'),
                'warehouse_id' => $this->warehouse->id,
                'shipping_method_id' => $order->shipping_method_id,
                'coupon_code' => 'later',
                'lines' => [['variant_id' => $this->shirt->id, 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'coupon_invalid')
            ->assertJsonPath('details.reason', 'not_started')
            ->assertJsonPath('details.field', 'coupon_code');

        expect(Order::query()->count())->toBe(1);
    });

    it('takes one Coupon code per Order', function () {
        $this->actingAs(userWithRole(Role::Sales))
            ->postJson('/api/v1/orders/quote', ['lines' => [['variant_id' => $this->shirt->id, 'quantity' => 1]], 'coupon_code' => ['A', 'B']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('coupon_code');
    });
});

describe('counting Coupon uses', function () {
    it('keeps the Coupon on the Order and counts the use only at Confirm', function () {
        $coupon = Coupon::factory()->create(['code' => 'SAVE10']);
        $order = placeOrder($this->warehouse, [[$this->shirt, 3], [$this->mug, 1]], couponCode: 'SAVE10');

        $this->actingAs(userWithRole(Role::Sales))
            ->getJson("/api/v1/orders/$order->id")
            ->assertJsonPath('data.coupon', ['id' => $coupon->id, 'code' => 'SAVE10', 'type' => 'percentage', 'value' => '10.00'])
            ->assertJsonPath('data.discount_total', '7.00')
            ->assertJsonPath('data.total', '70.67');
        expect(CouponRedemption::query()->count())->toBe(0);

        $this->postJson("/api/v1/orders/$order->id/confirm")->assertOk();

        expect(CouponRedemption::query()->sole()->only('coupon_id', 'order_id', 'customer_id', 'discount_amount', 'released_at'))
            ->toBe(['coupon_id' => $coupon->id, 'order_id' => $order->id, 'customer_id' => $order->customer_id, 'discount_amount' => '7.00', 'released_at' => null]);
        $this->getJson('/api/v1/coupons')->assertJsonPath('data.0.times_used', 1);
    });

    it('refuses to confirm past the usage limit and frees the use again when an Order is cancelled', function () {
        Coupon::factory()->create(['code' => 'ONCE', 'usage_limit' => 1]);
        $first = placeOrder($this->warehouse, [[$this->shirt, 1]], couponCode: 'ONCE');
        $second = placeOrder($this->warehouse, [[$this->shirt, 1]], couponCode: 'ONCE');
        $this->actingAs(userWithRole(Role::Sales));

        $this->postJson("/api/v1/orders/$first->id/confirm")->assertOk();
        $this->postJson("/api/v1/orders/$second->id/confirm")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'coupon_invalid')
            ->assertJsonPath('details.reason', 'limit_reached');
        expect($second->fresh()?->status)->toBe(OrderStatus::Pending)
            ->and(StockBalance::query()->where('variant_id', $this->shirt->id)->value('reserved'))->toBe(1);

        $this->postJson("/api/v1/orders/$first->id/cancel")->assertOk();
        $this->postJson("/api/v1/orders/$second->id/confirm")->assertOk();

        $coupon = Coupon::query()->sole();
        $this->getJson("/api/v1/coupons/$coupon->id/redemptions")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.order.number', $second->number)
            ->assertJsonPath('data.0.customer.id', $second->customer_id)
            ->assertJsonPath('data.0.released_at', null)
            ->assertJsonPath('data.1.order.number', $first->number);
        expect($this->getJson("/api/v1/coupons/$coupon->id/redemptions?filter[state]=released")->json('data.*.order.number'))->toBe([$first->number]);
    });

    it('refuses to confirm once the Coupon has expired or the Customer has used it up', function () {
        Coupon::factory()->create(['code' => 'TODAY', 'ends_on' => Carbon::today()->toDateString()]);
        Coupon::factory()->create(['code' => 'MINE', 'per_customer_limit' => 1]);
        $customer = Customer::factory()->create();
        $expiring = placeOrder($this->warehouse, [[$this->shirt, 1]], couponCode: 'TODAY');
        $first = placeOrder($this->warehouse, [[$this->shirt, 1]], $customer, 'MINE');
        $second = placeOrder($this->warehouse, [[$this->mug, 1]], $customer, 'MINE');
        $this->actingAs(userWithRole(Role::Sales));

        $this->postJson("/api/v1/orders/$first->id/confirm")->assertOk();
        $this->postJson("/api/v1/orders/$second->id/confirm")->assertJsonPath('details.reason', 'customer_limit_reached');

        $this->travel(1)->days();
        $this->postJson("/api/v1/orders/$expiring->id/confirm")->assertUnprocessable()->assertJsonPath('details.reason', 'expired');
    });

    it('re-prices from the Order’s own Coupon terms when lines are cancelled, and updates the use', function () {
        $coupon = Coupon::factory()->create(['code' => 'SAVE10']);
        $order = placeOrder($this->warehouse, [[$this->shirt, 3], [$this->mug, 1]], couponCode: 'SAVE10');
        $this->actingAs(userWithRole(Role::Sales))->postJson("/api/v1/orders/$order->id/confirm")->assertOk();
        $coupon->update(['value' => '50.00']);

        $mugLine = $order->lines()->where('variant_id', $this->mug->id)->value('id');
        $this->postJson("/api/v1/orders/$order->id/cancel-lines", ['lines' => [['line_id' => $mugLine, 'quantity' => 1]]])
            ->assertOk()
            // 10% of the shirts alone: 5.997 → 6.00; VAT on 53.97 = 2.70.
            ->assertJsonPath('data.discount_total', '6.00')
            ->assertJsonPath('data.lines.0.discount_amount', '6.00')
            ->assertJsonPath('data.lines.1.discount_amount', '0.00')
            ->assertJsonPath('data.total', '61.67');

        expect(CouponRedemption::query()->sole()->discount_amount)->toBe('6.00');
    });

    it('moves the use when a Confirmed Order’s Coupon is changed or removed', function () {
        Coupon::factory()->create(['code' => 'SAVE10']);
        $other = Coupon::factory()->fixed('5.00')->create(['code' => 'FIVE']);
        $order = placeOrder($this->warehouse, [[$this->shirt, 2]], couponCode: 'SAVE10');
        $address = $order->customer->addresses()->value('id');
        $this->actingAs(userWithRole(Role::Sales))->postJson("/api/v1/orders/$order->id/confirm")->assertOk();
        $edit = fn (?string $code) => $this->putJson("/api/v1/orders/$order->id", [
            'billing_address_id' => $address,
            'shipping_address_id' => $address,
            'shipping_method_id' => $order->shipping_method_id,
            'coupon_code' => $code,
            'lines' => [['variant_id' => $this->shirt->id, 'quantity' => 2]],
        ]);

        $edit('FIVE')->assertOk()->assertJsonPath('data.coupon.code', 'FIVE')->assertJsonPath('data.discount_total', '5.00');
        expect(CouponRedemption::query()->sole()->only('coupon_id', 'discount_amount', 'released_at'))
            ->toBe(['coupon_id' => $other->id, 'discount_amount' => '5.00', 'released_at' => null]);

        $edit(null)->assertOk()->assertJsonPath('data.coupon', null)->assertJsonPath('data.discount_total', '0.00');
        expect(CouponRedemption::query()->sole()->released_at)->not->toBeNull();
    });
});
