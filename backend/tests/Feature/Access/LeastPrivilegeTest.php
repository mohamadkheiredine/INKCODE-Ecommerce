<?php

use App\Enums\Role;
use App\Enums\ShipmentStatus;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Carrier;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Variant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/**
 * Changes every signed-in User may make to their own session, profile and notifications.
 */
const SELF_SERVICE_ROUTES = ['auth.login', 'auth.logout', 'auth.me.preferences', 'auth.me.password', 'notifications.read-all', 'notifications.read'];

/**
 * A real record for every route parameter, so each request reaches its authorization
 * check. A child record (an image, a Variant, an address) also fixes its parent.
 *
 * @return array<string, int>
 */
function recordsForRouteParameters(): array
{
    $product = Product::factory()->create();
    $variant = Variant::factory()->for($product)->create();
    $image = $product->images()->create(['path' => 'products/demo.jpg', 'position' => 1]);
    $address = CustomerAddress::factory()->create();
    $method = ShippingMethod::factory()->create();
    $carrier = Carrier::factory()->create();
    $order = placeOrder(Warehouse::factory()->create(), [[$variant, 1]]);

    /** @var array<string, Model> $records */
    $records = [
        'attribute' => Attribute::factory()->create(),
        'brand' => Brand::factory()->create(),
        'carrier' => $carrier,
        'category' => Category::factory()->create(),
        'customer' => Customer::query()->findOrFail($address->customer_id),
        'address' => $address,
        'product' => $product,
        'variant' => $variant,
        'image' => $image,
        'shipping_zone' => ShippingZone::factory()->create(),
        'shipping_method' => $method,
        'user' => User::factory()->withRole(Role::Sales)->create(),
        'warehouse' => Warehouse::factory()->create(),
        'order' => $order,
        'shipment' => $order->shipments()->create([
            'number' => $order->number.'-1',
            'carrier_id' => $carrier->id,
            'tracking_number' => 'TRK-1',
            'status' => ShipmentStatus::Shipped,
            'shipped_at' => now(),
        ]),
        'transfer' => Transfer::query()->create([
            'source_warehouse_id' => Warehouse::factory()->create()->id,
            'destination_warehouse_id' => Warehouse::factory()->create()->id,
        ]),
    ];

    return array_map(fn (Model $record): int => (int) $record->getKey(), $records);
}

it('refuses the Viewer every endpoint that changes data', function () {
    $ids = recordsForRouteParameters();
    $this->actingAs(userWithRole(Role::Viewer));

    $changing = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/')
            && array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []
            && ! in_array($route->getName(), SELF_SERVICE_ROUTES, true));

    expect($changing)->not->toBeEmpty();

    foreach ($changing as $route) {
        $uri = preg_replace_callback('/\{(\w+)(:\w*)?\??}/', function (array $match) use ($ids, $route): string {
            return (string) ($ids[$match[1]] ?? throw new RuntimeException("No record for {{$match[1]}} in {$route->uri()}: add one."));
        }, $route->uri());
        $method = array_values(array_diff($route->methods(), ['HEAD']))[0];

        $status = $this->json($method, "/$uri")->status();

        expect($status)->toBe(403, "$method /$uri answered $status to the Viewer");
    }
});
