<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSettings;
use App\Models\Variant;
use App\Models\Warehouse;
use App\Services\Settings;

describe('Stock levels', function () {
    it('shows every Variant in every active Warehouse with Available, Stock value and totals', function () {
        $beirut = Warehouse::factory()->create(['name' => 'Beirut']);
        Warehouse::factory()->create(['name' => 'Tripoli']);
        Warehouse::factory()->inactive()->create();
        $tee = Variant::factory()->create(['sku' => 'TEE', 'low_stock_threshold' => 3]);
        receiveStock($beirut, [[$tee, 10, '2.50']]);

        $response = $this->actingAs(userWithRole(Role::Viewer))
            ->getJson('/api/v1/stock-levels?sort=-stock_value')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.totals', ['on_hand' => 10, 'stock_value' => '25.00']);

        expect($response->json('data.0'))->toMatchArray([
            'warehouse' => ['id' => $beirut->id, 'name' => 'Beirut', 'code' => $beirut->code],
            'on_hand' => 10,
            'reserved' => 0,
            'available' => 10,
            'average_cost' => '2.5000',
            'stock_value' => '25.00',
            'low_stock_threshold' => 3,
            'is_low_stock' => false,
        ])->and($response->json('data.1'))->toMatchArray(['on_hand' => 0, 'stock_value' => '0.00', 'is_low_stock' => true]);
    });

    it('filters by Warehouse, Product, Category (with its children) and low stock', function () {
        StoreSettings::query()->findOrFail(StoreSettings::ID)->update(['low_stock_threshold' => 5]);
        app(Settings::class)->forget();
        [$beirut, $tripoli] = Warehouse::factory()->count(2)->create();
        $clothing = Category::factory()->create();
        $shirts = Category::factory()->childOf($clothing)->create();
        $tee = Variant::factory()->for(Product::factory()->for($shirts)->state(['name' => 'Linen Tee']))->create(['sku' => 'TEE']);
        $mug = Variant::factory()->create(['sku' => 'MUG']);
        receiveStock($beirut, [[$tee, 20, '2.00'], [$mug, 4, '1.00']]);
        receiveStock($tripoli, [[$tee, 6, '2.00']]);

        $this->actingAs(userWithRole(Role::Inventory));
        $rows = fn (string $query): array => array_map(
            fn (array $row): string => $row['variant']['sku'].'@'.$row['warehouse']['id'],
            $this->getJson("/api/v1/stock-levels?sort=sku&$query")->assertOk()->json('data'),
        );

        expect($rows("filter[warehouse]={$beirut->id}"))->toBe(["MUG@{$beirut->id}", "TEE@{$beirut->id}"])
            ->and($rows("filter[product]={$tee->product_id}"))->toBe(["TEE@{$beirut->id}", "TEE@{$tripoli->id}"])
            ->and($rows("filter[category]={$clothing->id}"))->toBe(["TEE@{$beirut->id}", "TEE@{$tripoli->id}"])
            ->and($rows('filter[low_stock]=1'))->toBe(["MUG@{$beirut->id}", "MUG@{$tripoli->id}"])
            ->and($rows('search=linen'))->toBe(["TEE@{$beirut->id}", "TEE@{$tripoli->id}"]);
    });

    it('is refused to Roles that do not see stock', function () {
        $this->actingAs(userWithRole(Role::Support))->getJson('/api/v1/stock-levels')->assertForbidden();
        $this->getJson('/api/v1/stock-movements')->assertForbidden();
    });
});

describe('Stock movement history', function () {
    it('lists movements newest first, linked to their source, filtered by Variant, Warehouse, type and day', function () {
        [$beirut, $tripoli] = Warehouse::factory()->count(2)->create();
        [$tee, $mug] = Variant::factory()->count(2)->create();
        $this->travelTo('2026-10-01 10:00');
        $first = receiveStock($beirut, [[$tee, 5, '1.00']]);
        $this->travelTo('2026-10-03 10:00');
        receiveStock($tripoli, [[$mug, 2, '1.00']]);
        $user = userWithRole(Role::Sales);

        $this->actingAs($user)
            ->getJson('/api/v1/stock-movements')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.variant.sku', $mug->sku)
            ->assertJsonPath('data.1.source', ['type' => 'receipt', 'id' => $first->id, 'label' => $first->number])
            ->assertJsonPath('data.1.on_hand_delta', 5);

        $skus = fn (string $query): array => $this->getJson("/api/v1/stock-movements?$query")->assertOk()->json('data.*.variant.sku');
        expect($skus("filter[variant]={$tee->id}"))->toBe([$tee->sku])
            ->and($skus("filter[warehouse]={$tripoli->id}"))->toBe([$mug->sku])
            ->and($skus('filter[type]=receipt'))->toHaveCount(2)
            ->and($skus('filter[from]=2026-10-02'))->toBe([$mug->sku])
            ->and($skus('filter[to]=2026-10-01'))->toBe([$tee->sku]);

        $this->getJson('/api/v1/stock-movements?filter[type]=teleport&filter[from]=2026-10-03&filter[to]=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter.type', 'filter.to']);
    });
});

describe('the Product page', function () {
    it('shows each Variant\'s stock per Warehouse to Roles that see stock, and not to others', function () {
        $warehouse = Warehouse::factory()->create(['name' => 'Beirut']);
        $variant = Variant::factory()->create();
        receiveStock($warehouse, [[$variant, 8, '1.00']]);

        $this->actingAs(userWithRole(Role::Sales))
            ->getJson("/api/v1/products/{$variant->product_id}")
            ->assertOk()
            ->assertJsonPath('data.variants.0.stock', [[
                'warehouse' => ['id' => $warehouse->id, 'name' => 'Beirut', 'code' => $warehouse->code],
                'on_hand' => 8,
                'reserved' => 0,
                'available' => 8,
            ]]);

        $this->actingAs(userWithRole(Role::Support))
            ->getJson("/api/v1/products/{$variant->product_id}")
            ->assertOk()
            ->assertJsonMissingPath('data.variants.0.stock');
    });
});
