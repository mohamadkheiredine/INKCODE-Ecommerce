<?php

namespace App\Actions\Orders;

use App\Models\ShippingMethod;
use App\Models\StockBalance;
use App\Models\Variant;
use App\Models\Warehouse;
use App\Services\OrderPricing;

/**
 * The live quote the Order form shows while it is filled in: the lines priced exactly as
 * {@see CreateOrder} will price them, and every active Warehouse with the lines it lacks
 * Available stock for. Warehouses that can fulfil every line come first, so the form
 * pre-selects one of them as the Fulfilling warehouse.
 */
final class QuoteOrder
{
    public function __construct(private readonly OrderPricing $pricing) {}

    /**
     * @param  list<array{variant_id: int, quantity: int}>  $lines
     * @return array<string, mixed> the {@see OrderPricing::price()} result plus `warehouses`
     */
    public function handle(array $lines, ?int $shippingMethodId): array
    {
        $variants = Variant::query()
            ->with(['product:id,name,taxable', 'attributeValues.attribute:id,name'])
            ->findMany(array_column($lines, 'variant_id'))
            ->keyBy('id');
        $shippingPrice = $shippingMethodId === null ? '0.00' : ShippingMethod::query()->findOrFail($shippingMethodId)->price;

        return [
            ...$this->pricing->price(
                array_map(fn (array $line): array => ['variant' => $variants[$line['variant_id']], 'quantity' => $line['quantity']], $lines),
                $shippingPrice,
            ),
            'warehouses' => $this->warehouses($lines, $variants->map(fn (Variant $variant): string => $variant->sku)->all()),
        ];
    }

    /**
     * @param  list<array{variant_id: int, quantity: int}>  $lines
     * @param  array<int, string>  $skus  by Variant id
     * @return list<array{id: int, name: string, code: string, is_sufficient: bool, shortages: list<array{variant_id: int, sku: string, requested: int, available: int}>}>
     */
    private function warehouses(array $lines, array $skus): array
    {
        $available = StockBalance::query()
            ->whereIn('variant_id', array_column($lines, 'variant_id'))
            ->get(['variant_id', 'warehouse_id', 'available'])
            ->mapWithKeys(fn (StockBalance $balance): array => ["$balance->warehouse_id:$balance->variant_id" => $balance->available]);

        $warehouses = Warehouse::query()->where('is_active', true)->orderBy('id')->get(['id', 'name', 'code'])
            ->map(function (Warehouse $warehouse) use ($lines, $skus, $available): array {
                $shortages = [];
                foreach ($lines as $line) {
                    $inStock = (int) ($available["$warehouse->id:{$line['variant_id']}"] ?? 0);
                    if ($inStock < $line['quantity']) {
                        $shortages[] = ['variant_id' => $line['variant_id'], 'sku' => $skus[$line['variant_id']], 'requested' => $line['quantity'], 'available' => $inStock];
                    }
                }

                return [...$warehouse->only('id', 'name', 'code'), 'is_sufficient' => $shortages === [], 'shortages' => $shortages];
            });

        return $warehouses->sortBy(fn (array $warehouse): int => $warehouse['is_sufficient'] ? 0 : 1)->values()->all();
    }
}
