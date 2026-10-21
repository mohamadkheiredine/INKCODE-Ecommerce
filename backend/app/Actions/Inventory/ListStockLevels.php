<?php

namespace App\Actions\Inventory;

use App\Models\Variant;
use App\Services\Settings;
use App\Support\ListParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Stock levels: one row per Variant per active Warehouse (or per the Warehouse asked for,
 * even a deactivated one), including Variants that never had stock there. Each row has
 * On-hand, Reserved, Available, Stock value (On-hand × Average cost) and whether
 * Available is at or below the Variant's Low-stock threshold (the store default when the
 * Variant has none).
 */
final class ListStockLevels
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return LengthAwarePaginator<int, Variant>
     */
    public function handle(ListParameters $parameters): LengthAwarePaginator
    {
        return $parameters->paginate(
            $this->query($parameters)->with('attributeValues.attribute:id,name'),
            tieBreakers: ['warehouses.id'],
        );
    }

    /**
     * How many rows the filters keep (all pages).
     */
    public function count(ListParameters $parameters): int
    {
        return $this->query($parameters)->count();
    }

    /**
     * Units on hand and their Stock value across every row the filters keep (all pages).
     *
     * @return array{on_hand: int, stock_value: string}
     */
    public function totals(ListParameters $parameters): array
    {
        $totals = DB::query()
            ->fromSub($this->query($parameters)->toBase(), 'levels')
            ->selectRaw('COALESCE(SUM(on_hand), 0) AS on_hand, COALESCE(SUM(stock_value), 0) AS stock_value')
            ->first();

        return [
            'on_hand' => (int) ($totals->on_hand ?? 0),
            'stock_value' => bcadd((string) ($totals->stock_value ?? '0'), '0', 2),
        ];
    }

    /**
     * @return Builder<Variant>
     */
    private function query(ListParameters $parameters): Builder
    {
        $query = Variant::query()
            ->join('products', 'products.id', '=', 'variants.product_id')
            ->crossJoin('warehouses')
            ->leftJoin('stock_balances', fn (JoinClause $join) => $join
                ->on('stock_balances.variant_id', '=', 'variants.id')
                ->on('stock_balances.warehouse_id', '=', 'warehouses.id'))
            ->select('variants.*')
            ->addSelect([
                'products.name AS product_name',
                'warehouses.id AS warehouse_id',
                'warehouses.name AS warehouse_name',
                'warehouses.code AS warehouse_code',
            ])
            ->selectRaw('COALESCE(stock_balances.on_hand, 0) AS on_hand')
            ->selectRaw('COALESCE(stock_balances.reserved, 0) AS reserved')
            ->selectRaw('COALESCE(stock_balances.available, 0) AS available')
            ->selectRaw('CAST(COALESCE(stock_balances.on_hand, 0) * variants.average_cost AS DECIMAL(20, 2)) AS stock_value')
            ->selectRaw('COALESCE(variants.low_stock_threshold, ?) AS effective_threshold', [$this->settings->lowStockThreshold()]);

        $parameters->search($query, ['variants.sku', 'products.name']);

        if ($parameters->hasFilter('warehouse')) {
            $query->where('warehouses.id', $parameters->filter('warehouse'));
        } else {
            $query->where('warehouses.is_active', true);
        }

        if ($parameters->hasFilter('product')) {
            $query->where('variants.product_id', $parameters->filter('product'));
        }

        if ($parameters->hasFilter('category')) {
            $categoryId = (int) $parameters->filter('category');
            $query->whereIn('products.category_id', fn ($categories) => $categories
                ->select('id')
                ->from('categories')
                ->where('id', $categoryId)
                ->orWhere('parent_id', $categoryId));
        }

        if ($parameters->hasFilter('low_stock') && filter_var($parameters->filter('low_stock'), FILTER_VALIDATE_BOOLEAN)) {
            $query->whereRaw('COALESCE(stock_balances.available, 0) <= COALESCE(variants.low_stock_threshold, ?)', [$this->settings->lowStockThreshold()]);
        }

        return $query;
    }
}
