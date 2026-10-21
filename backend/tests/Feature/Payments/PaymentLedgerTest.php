<?php

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Testing\TestResponse;

/** Records a payment through the API as the given (or a new Finance) User. */
function pay(Order $order, array $body, ?string $key = null): TestResponse
{
    return test()->postJson("/api/v1/orders/$order->id/payments", $body, $key === null ? [] : ['Idempotency-Key' => $key]);
}

beforeEach(function () {
    $this->warehouse = Warehouse::factory()->create();
    $this->shirt = Variant::factory()->create(['sku' => 'TEE-RED-M', 'price' => '20.00']);
    receiveStock($this->warehouse, [[$this->shirt, 20, '5.00']]);
    $this->order = placeOrder($this->warehouse, [[$this->shirt, 2]]);
    $this->finance = userWithRole(Role::Finance);
});

describe('recording payments', function () {
    it('records a Manual payment, derives Partially paid then Paid, and audits each row', function () {
        $this->actingAs($this->finance);
        $total = $this->order->total;
        $half = bcdiv($total, '2', 2);

        pay($this->order, ['method' => 'manual', 'amount' => $half, 'note' => 'Bank transfer'])
            ->assertCreated()
            ->assertJsonPath('data.payment_status', 'partially_paid')
            ->assertJsonPath('data.captured_amount', $half)
            ->assertJsonPath('data.amount_due', bcsub($total, $half, 2))
            ->assertJsonPath('payment.kind', 'payment')
            ->assertJsonPath('payment.method', 'manual')
            ->assertJsonPath('payment.status', 'succeeded')
            ->assertJsonPath('payment.user.id', $this->finance->id)
            ->assertJsonPath('data.payments.0.amount', $half)
            ->assertJsonPath('data.timeline.1.type', 'payment');

        pay($this->order, ['method' => 'cash_on_delivery', 'amount' => bcsub($total, $half, 2)])
            ->assertCreated()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.amount_due', '0.00');

        expect(auditTrailOf('payment', Payment::query()->firstOrFail()->id)->sole()->after)
            ->toMatchArray(['kind' => 'payment', 'method' => 'manual', 'amount' => $half, 'status' => 'succeeded']);
    });

    it('refuses a payment above the amount still due with 409 payment_exceeds_due', function () {
        $this->actingAs($this->finance);

        pay($this->order, ['method' => 'manual', 'amount' => bcadd($this->order->total, '0.01', 2)])
            ->assertConflict()
            ->assertJsonPath('code', 'payment_exceeds_due');
        pay($this->order, ['method' => 'manual', 'amount' => $this->order->total])->assertCreated();
        pay($this->order, ['method' => 'manual', 'amount' => '0.01'])->assertConflict()->assertJsonPath('code', 'payment_exceeds_due');

        expect(Payment::query()->count())->toBe(1);
    });

    it('records a payment replayed with the same Idempotency-Key only once', function () {
        $this->actingAs($this->finance);
        $body = ['method' => 'manual', 'amount' => '10.00'];

        pay($this->order, $body, 'pay-1')->assertCreated();
        pay($this->order, $body, 'pay-1')->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        expect(Payment::query()->sole()->idempotency_key)->toBe('pay-1')
            ->and($this->order->refresh()->captured_amount)->toBe('10.00');
    });

    it('validates the method, the amount and the card test option, and never takes card numbers', function () {
        $this->actingAs($this->finance);

        pay($this->order, ['method' => 'cheque', 'amount' => '0'])->assertUnprocessable()->assertJsonValidationErrors(['method', 'amount']);
        pay($this->order, ['method' => 'manual', 'amount' => '1.234'])->assertUnprocessable()->assertJsonValidationErrors(['amount']);
        pay($this->order, ['method' => 'card', 'amount' => '5.00'])->assertUnprocessable()->assertJsonValidationErrors(['card_outcome']);
        pay($this->order, ['method' => 'manual', 'amount' => '5.00', 'card_outcome' => 'approve'])->assertUnprocessable()->assertJsonValidationErrors(['card_outcome']);
        pay($this->order, ['method' => 'card', 'amount' => '5.00', 'card_outcome' => 'approve', 'card_number' => '4242424242424242'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['card_number']);

        expect(Payment::query()->count())->toBe(0);
    });

    it('refuses payments on a Cancelled Order, which owes nothing', function () {
        $this->actingAs(userWithRole(Role::Operations))->postJson("/api/v1/orders/{$this->order->id}/cancel")->assertOk();

        $this->actingAs($this->finance);
        pay($this->order, ['method' => 'manual', 'amount' => '1.00'])->assertConflict()->assertJsonPath('code', 'payment_exceeds_due');
    });
});

describe('the mock Card gateway', function () {
    it('approves a charge with a gateway reference', function () {
        $this->actingAs($this->finance);

        pay($this->order, ['method' => 'card', 'amount' => $this->order->total, 'card_outcome' => 'approve'])
            ->assertCreated()
            ->assertJsonPath('payment.status', 'succeeded')
            ->assertJsonPath('data.payment_status', 'paid');

        expect(Payment::query()->sole()->gateway_reference)->toStartWith('mock_ch_');
    });

    it('records a declined charge as failed, counts it toward nothing, and tells every active Finance User', function () {
        $otherFinance = userWithRole(Role::Finance);
        $inactiveFinance = userWithRole(Role::Finance, ['is_active' => false]);
        $sales = userWithRole(Role::Sales);
        $this->actingAs($this->finance);

        pay($this->order, ['method' => 'card', 'amount' => '10.00', 'card_outcome' => 'decline'], 'card-1')
            ->assertCreated()
            ->assertJsonPath('payment.status', 'failed')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.captured_amount', '0.00')
            ->assertJsonPath('data.amount_due', $this->order->total);

        foreach ([$this->finance, $otherFinance] as $user) {
            expect($user->notifications()->sole()->type)->toBe(NotificationType::PaymentDeclined->value);
        }
        expect($inactiveFinance->notifications()->count())->toBe(0)
            ->and($sales->notifications()->count())->toBe(0);

        $this->getJson('/api/v1/notifications')
            ->assertJsonPath('data.0.message', "A Card payment of 10.00 on Order {$this->order->number} was declined.")
            ->assertJsonPath('data.0.link', "/orders/{$this->order->id}");
    });
});

describe('Refunds', function () {
    beforeEach(function () {
        $this->actingAs($this->finance);
        pay($this->order, ['method' => 'manual', 'amount' => $this->order->total])->assertCreated();
    });

    it('derives Partially refunded then Refunded, and refuses going past the Captured amount', function () {
        $total = $this->order->total;

        $this->postJson("/api/v1/orders/{$this->order->id}/refunds", ['method' => 'manual', 'amount' => '5.00'])
            ->assertCreated()
            ->assertJsonPath('payment.kind', 'refund')
            ->assertJsonPath('data.payment_status', 'partially_refunded')
            ->assertJsonPath('data.refunded_amount', '5.00');

        $this->postJson("/api/v1/orders/{$this->order->id}/refunds", ['method' => 'manual', 'amount' => bcsub($total, '4.99', 2)])
            ->assertConflict()
            ->assertJsonPath('code', 'refund_exceeds_captured');

        $this->postJson("/api/v1/orders/{$this->order->id}/refunds", ['method' => 'manual', 'amount' => bcsub($total, '5.00', 2)])
            ->assertCreated()
            ->assertJsonPath('data.payment_status', 'refunded');

        $this->postJson("/api/v1/orders/{$this->order->id}/refunds", ['method' => 'manual', 'amount' => '0.01'])
            ->assertConflict()
            ->assertJsonPath('code', 'refund_exceeds_captured');
    });

    it('records a Refund replayed with the same Idempotency-Key only once', function () {
        $body = ['method' => 'manual', 'amount' => '3.00'];

        $this->postJson("/api/v1/orders/{$this->order->id}/refunds", $body, ['Idempotency-Key' => 'refund-1'])->assertCreated();
        $this->postJson("/api/v1/orders/{$this->order->id}/refunds", $body, ['Idempotency-Key' => 'refund-1'])
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');

        expect(Payment::query()->where('kind', 'refund')->count())->toBe(1)
            ->and($this->order->refresh()->refunded_amount)->toBe('3.00');
    });

    it('refunds to a Card only through an approved Card charge', function () {
        $this->postJson("/api/v1/orders/{$this->order->id}/refunds", ['method' => 'card', 'amount' => '1.00'])
            ->assertConflict()
            ->assertJsonPath('code', 'refund_method_unavailable');
    });

    it('gives back what was received when a paid Order is cancelled, in the same step', function () {
        $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/orders/{$this->order->id}/cancel", ['note' => 'Out of stock'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.payment_status', 'refunded')
            ->assertJsonPath('data.refunded_amount', $this->order->total);

        expect(Payment::query()->where('kind', 'refund')->sole()->only('method', 'note'))
            ->toMatchArray(['note' => 'Out of stock']);
    });

    it('gives back the difference when a paid Order is partly cancelled', function () {
        $order = placeOrder($this->warehouse, [[$this->shirt, 3]]);
        pay($order, ['method' => 'manual', 'amount' => $order->total])->assertCreated();
        $before = $order->refresh()->total;

        $response = $this->actingAs(userWithRole(Role::Operations))
            ->postJson("/api/v1/orders/$order->id/cancel-lines", ['lines' => [['line_id' => $order->lines->first()->id, 'quantity' => 1]]])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'partially_refunded');

        expect($response->json('data.refunded_amount'))->toBe(bcsub($before, $response->json('data.total'), 2))
            ->and($response->json('data.amount_due'))->toBe('0.00');
    });
});

describe('who may do what', function () {
    it('lets only Roles with payments.record record payments', function (Role $role) {
        $this->actingAs(userWithRole($role));

        pay($this->order, ['method' => 'manual', 'amount' => '1.00'])->assertForbidden();
    })->with([Role::Operations, Role::Inventory, Role::Sales, Role::Support, Role::Viewer]);

    it('lets only Roles with refunds.issue issue Refunds', function (Role $role) {
        $this->actingAs($this->finance);
        pay($this->order, ['method' => 'manual', 'amount' => '5.00'])->assertCreated();

        $this->actingAs(userWithRole($role))
            ->postJson("/api/v1/orders/{$this->order->id}/refunds", ['method' => 'manual', 'amount' => '1.00'])
            ->assertForbidden();
    })->with([Role::Operations, Role::Inventory, Role::Sales, Role::Support, Role::Viewer]);

    it('lets the Admin record payments and Refunds', function () {
        $this->actingAs(userWithRole(Role::Admin));

        pay($this->order, ['method' => 'manual', 'amount' => '5.00'])->assertCreated();
        $this->postJson("/api/v1/orders/{$this->order->id}/refunds", ['method' => 'manual', 'amount' => '5.00'])->assertCreated();
    });

    it('hides the Payment ledger, payments list and reconciliation from Roles without payments.view', function () {
        $this->actingAs($this->finance);
        pay($this->order, ['method' => 'manual', 'amount' => '5.00'])->assertCreated();

        $this->actingAs(userWithRole(Role::Inventory));
        $order = $this->getJson("/api/v1/orders/{$this->order->id}")->assertOk()->json('data');
        expect($order)->not->toHaveKey('payments')
            ->and(collect($order['timeline'])->pluck('type')->all())->not->toContain('payment');
        $this->getJson('/api/v1/payments')->assertForbidden();
        $this->getJson('/api/v1/payments/reconciliation')->assertForbidden();
    });
});

describe('the payments list and reconciliation', function () {
    it('lists ledger rows across Orders with filters and search', function () {
        $other = placeOrder($this->warehouse, [[$this->shirt, 1]]);
        $this->actingAs($this->finance);
        pay($this->order, ['method' => 'manual', 'amount' => '5.00'])->assertCreated();
        pay($other, ['method' => 'card', 'amount' => '3.00', 'card_outcome' => 'decline'])->assertCreated();

        $this->actingAs(userWithRole(Role::Viewer));
        $this->getJson('/api/v1/payments')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.order.number', $other->number);
        $this->getJson('/api/v1/payments?filter[status]=failed')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.method', 'card');
        $this->getJson('/api/v1/payments?filter[method]=manual&search='.$this->order->number)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/payments?filter[kind]=refund')->assertJsonPath('meta.total', 0);
    });

    it('lists the Orders whose ledger total differs from what they owe', function () {
        $paid = placeOrder($this->warehouse, [[$this->shirt, 1]]);
        $this->actingAs($this->finance);
        pay($paid, ['method' => 'manual', 'amount' => $paid->total])->assertCreated();
        pay($this->order, ['method' => 'manual', 'amount' => '5.00'])->assertCreated();
        // A ledger row written behind the API's back: the Order's kept totals no longer agree.
        Payment::query()->create(['order_id' => $paid->id, 'kind' => 'payment', 'method' => 'manual', 'amount' => '2.00', 'status' => 'succeeded']);

        $rows = collect($this->getJson('/api/v1/payments/reconciliation?per_page=100')->assertOk()->json('data'))->keyBy('number');

        expect($rows)->toHaveCount(2)
            ->and($rows[$this->order->number])->toMatchArray([
                'amount_owed' => $this->order->total,
                'ledger_total' => '5.00',
                'difference' => bcsub('5.00', $this->order->total, 2),
            ])
            ->and($rows[$paid->number]['difference'])->toBe('2.00');
        $this->getJson('/api/v1/payments/reconciliation?filter[balance]=over')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.number', $paid->number);
        $this->getJson('/api/v1/payments/reconciliation?filter[balance]=due&sort=difference')->assertJsonPath('meta.total', 1);
    });
});
