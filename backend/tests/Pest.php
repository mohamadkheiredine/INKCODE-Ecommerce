<?php

use App\Actions\Inventory\CreateReceipt;
use App\Actions\Orders\CreateOrder;
use App\Enums\Role;
use App\Models\AuditEntry;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\User;
use App\Models\Variant;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the MySQL `inkcode_testing` database (see
| phpunit.xml) so row locks and transactions behave like production.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

// Concurrency tests run separate PHP processes, which only see committed rows, so they
// truncate instead of rolling back.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create an active User holding exactly the given Role.
 *
 * @param  array<string, mixed>  $attributes
 */
function userWithRole(Role $role, array $attributes = []): User
{
    return User::factory()->withRole($role)->create($attributes);
}

/**
 * Every Role that only looks at the catalog: everyone but Admin and Operations.
 *
 * @return list<Role>
 */
function rolesThatMayNotManageTheCatalog(): array
{
    return [Role::Inventory, Role::Sales, Role::Support, Role::Finance, Role::Viewer];
}

/**
 * The audit entries recorded for one record, oldest first. (The audit log's own API
 * arrives with the audit viewer, #26.)
 *
 * @return Collection<int, AuditEntry>
 */
function auditTrailOf(string $recordType, int $recordId): Collection
{
    return AuditEntry::query()
        ->where('record_type', $recordType)
        ->where('record_id', $recordId)
        ->orderBy('id')
        ->get();
}

/**
 * Seed the way a local or review environment does, with demo mode on or off.
 */
function seedStore(bool $demoMode): void
{
    config(['app.demo_mode' => $demoMode]);
    test()->seed(DatabaseSeeder::class);
}

/**
 * Sends the next request from one browser, holding only this session cookie (none for a
 * new browser). Tests share one app, so the guards and the in-memory session are reset
 * the way a real next request finds them.
 */
function asBrowser(string $session = ''): TestCase
{
    app('auth')->forgetGuards();
    app('session')->driver()->flush();

    return test()->withCredentials()->withCookie((string) config('session.cookie'), $session);
}

function signInFromNewBrowser(string $email, string $password): TestResponse
{
    return asBrowser()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
}

/**
 * The session a response started, to send on that browser's later requests.
 */
function sessionOf(TestResponse $response): string
{
    return $response->getCookie((string) config('session.cookie'))?->getValue()
        ?? throw new RuntimeException('The response started no session.');
}

/**
 * Creates a Pending Order, as the API does, for a new Customer in Beirut shipped by a $5
 * Standard method: one line per `[Variant, quantity]`, fulfilled from the Warehouse.
 *
 * @param  list<array{Variant, int}>  $lines
 */
function placeOrder(Warehouse $warehouse, array $lines, ?Customer $customer = null): Order
{
    $customer ??= Customer::factory()->create();
    $address = $customer->addresses()->where('is_default_shipping', true)->first()
        ?? CustomerAddress::factory()->defaultForBoth()->create(['customer_id' => $customer->id, 'country_code' => 'LB', 'city' => 'Beirut']);
    $method = ShippingMethod::query()->where('name', 'Standard')->first() ?? ShippingMethod::factory()->create([
        'shipping_zone_id' => ShippingZone::factory()->covering('LB')->create()->id,
        'name' => 'Standard',
        'price' => '5.00',
    ]);

    return app(CreateOrder::class)->handle([
        'customer_id' => $address->customer_id,
        'billing_address_id' => $address->id,
        'shipping_address_id' => $address->id,
        'warehouse_id' => $warehouse->id,
        'shipping_method_id' => $method->id,
        'notes' => null,
        'lines' => array_map(fn (array $line): array => ['variant_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
    ]);
}

/**
 * Receives stock through a Receipt, as the API does: one line per
 * `[Variant, quantity, unit cost]`.
 *
 * @param  list<array{Variant, int, string}>  $lines
 */
function receiveStock(Warehouse $warehouse, array $lines, string $supplierReference = 'PO-1'): Receipt
{
    return app(CreateReceipt::class)->handle([
        'warehouse_id' => $warehouse->id,
        'supplier_reference' => $supplierReference,
        'notes' => null,
        'lines' => array_map(fn (array $line): array => [
            'variant_id' => $line[0]->id,
            'quantity' => $line[1],
            'unit_cost' => $line[2],
        ], $lines),
    ]);
}
