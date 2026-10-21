<?php

namespace App\Actions\Reports;

use App\Enums\ReportType;
use App\Enums\ReturnReason;
use App\Enums\SalesInterval;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderReturn;
use App\Models\Variant;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * One of the six reports for a date range, computed from the same Counted orders as the
 * dashboard ({@see Order::counted()}, dated by the day they were placed), with a summary
 * compared against the previous period of the same length.
 *
 * Every report has the same shape, which the screen and both exports read:
 * - `columns`: each column's key and type (text, date, integer, money, percent, or enum: a
 *   stored value the client translates).
 * - `rows`: one array per row, keyed by column; money as strings with two decimals.
 * - `summary`: figures with their `previous` value and `change` in percent (null on a zero
 *   baseline, or for a snapshot of now such as Stock value).
 */
final class BuildReport
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  'units'|'sales'  $rankBy  how the Products report orders its rows
     * @return array{report: string, range: array<string, string>, columns: list<array{key: string, type: string}>, summary: list<array{key: string, type: string, value: string|int, previous: string|int|null, change: float|null}>, rows: list<array<string, string|int|null>>}
     */
    public function handle(ReportType $type, CarbonImmutable $from, CarbonImmutable $to, SalesInterval $interval = SalesInterval::Day, string $rankBy = 'sales'): array
    {
        $from = $from->startOfDay();
        $to = $to->startOfDay();
        $days = (int) $from->diffInDays($to) + 1;
        $previousTo = $from->subDay();
        $previousFrom = $previousTo->subDays($days - 1);

        [$columns, $rows, $current, $previous] = match ($type) {
            ReportType::Sales => $this->sales($from, $to, $previousFrom, $previousTo, $interval),
            ReportType::Products => $this->products($from, $to, $previousFrom, $previousTo, $rankBy),
            ReportType::Categories => $this->categories($from, $to, $previousFrom, $previousTo),
            ReportType::Customers => $this->customers($from, $to, $previousFrom, $previousTo),
            ReportType::Returns => $this->returns($from, $to, $previousFrom, $previousTo),
            ReportType::Inventory => $this->inventory($from, $to, $previousFrom, $previousTo),
        };

        $summary = [];
        foreach ($current as $key => [$kind, $value]) {
            $before = $previous[$key] ?? null;
            $summary[] = [
                'key' => $key,
                'type' => $kind,
                'value' => $value,
                'previous' => $before,
                'change' => $before === null ? null : $this->change((string) $value, (string) $before),
            ];
        }

        return [
            'report' => $type->value,
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'previous_from' => $previousFrom->toDateString(),
                'previous_to' => $previousTo->toDateString(),
            ],
            'columns' => array_map(fn (string $key, string $kind): array => ['key' => $key, 'type' => $kind], array_keys($columns), $columns),
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    /**
     * Revenue and its parts per day, week or month, every period of the range included.
     *
     * @return array{0: array<string, string>, 1: list<array<string, string|int|null>>, 2: array<string, array{0: string, 1: string|int}>, 3: array<string, string|int>}
     */
    private function sales(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, CarbonImmutable $previousTo, SalesInterval $interval): array
    {
        $period = match ($interval) {
            SalesInterval::Day => 'DATE(placed_at)',
            SalesInterval::Week => 'DATE_SUB(DATE(placed_at), INTERVAL WEEKDAY(placed_at) DAY)',
            SalesInterval::Month => "DATE_FORMAT(placed_at, '%Y-%m-01')",
        };
        $found = $this->countedOrders($from, $to)->toBase()
            ->selectRaw("$period AS period")
            ->selectRaw('COUNT(*) AS orders, SUM(items_total) AS items, SUM(discount_total) AS discounts')
            ->selectRaw('SUM(vat_total) AS vat, SUM(shipping_total) AS shipping, SUM(total) AS revenue')
            ->selectRaw('SUM(refunded_amount) AS refunds, SUM(items_total - discount_total - refunded_amount) AS net_sales')
            ->groupByRaw($period)
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->period);

        $start = match ($interval) {
            SalesInterval::Day => $from,
            SalesInterval::Week => $from->startOfWeek(CarbonImmutable::MONDAY),
            SalesInterval::Month => $from->startOfMonth(),
        };
        $rows = [];
        for ($day = $start; $day->lessThanOrEqualTo($to); $day = match ($interval) {
            SalesInterval::Day => $day->addDay(),
            SalesInterval::Week => $day->addWeek(),
            SalesInterval::Month => $day->addMonth(),
        }) {
            $row = $found->get($day->toDateString());
            $orders = (int) ($row->orders ?? 0);
            $revenue = $this->money($row->revenue ?? null);
            $rows[] = [
                'period' => $day->toDateString(),
                'orders' => $orders,
                'items' => $this->money($row->items ?? null),
                'discounts' => $this->money($row->discounts ?? null),
                'vat' => $this->money($row->vat ?? null),
                'shipping' => $this->money($row->shipping ?? null),
                'revenue' => $revenue,
                'refunds' => $this->money($row->refunds ?? null),
                'net_sales' => $this->money($row->net_sales ?? null),
                'average_order_value' => $this->average($revenue, $orders),
            ];
        }

        $columns = [
            'period' => 'date', 'orders' => 'integer', 'items' => 'money', 'discounts' => 'money', 'vat' => 'money',
            'shipping' => 'money', 'revenue' => 'money', 'refunds' => 'money', 'net_sales' => 'money', 'average_order_value' => 'money',
        ];
        $totals = function (CarbonImmutable $start, CarbonImmutable $end): array {
            $row = $this->countedOrders($start, $end)->toBase()
                ->selectRaw('COUNT(*) AS orders, SUM(total) AS revenue, SUM(items_total - discount_total - refunded_amount) AS net_sales')
                ->first();
            $revenue = $this->money($row->revenue ?? null);
            $orders = (int) ($row->orders ?? 0);

            return [
                'revenue' => ['money', $revenue],
                'net_sales' => ['money', $this->money($row->net_sales ?? null)],
                'orders' => ['integer', $orders],
                'average_order_value' => ['money', $this->average($revenue, $orders)],
            ];
        };

        return [$columns, $rows, $totals($from, $to), $this->values($totals($previousFrom, $previousTo))];
    }

    /**
     * Every Product sold in the range: units, Orders and sales (line totals, VAT included).
     *
     * @return array{0: array<string, string>, 1: list<array<string, string|int|null>>, 2: array<string, array{0: string, 1: string|int}>, 3: array<string, string|int>}
     */
    private function products(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, CarbonImmutable $previousTo, string $rankBy): array
    {
        $rows = $this->soldLines($from, $to)
            ->join('products', 'products.id', '=', 'variants.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('variants.product_id')
            ->selectRaw('variants.product_id, MAX(order_lines.product_name) AS product, MAX(categories.name) AS category')
            ->selectRaw('SUM(order_lines.quantity) AS units, COUNT(DISTINCT order_lines.order_id) AS orders, SUM(order_lines.line_total) AS sales')
            ->orderByDesc($rankBy === 'units' ? 'units' : 'sales')
            ->orderByDesc($rankBy === 'units' ? 'sales' : 'units')
            ->orderBy('variants.product_id')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'product' => (string) $row->product,
                'category' => (string) $row->category,
                'units' => (int) $row->units,
                'orders' => (int) $row->orders,
                'sales' => $this->money($row->sales),
            ])
            ->all();

        $totals = function (CarbonImmutable $start, CarbonImmutable $end): array {
            $row = $this->soldLines($start, $end)->toBase()
                ->selectRaw('COUNT(DISTINCT variants.product_id) AS products, SUM(order_lines.quantity) AS units, SUM(order_lines.line_total) AS sales')
                ->first();

            return [
                'products' => ['integer', (int) ($row->products ?? 0)],
                'units' => ['integer', (int) ($row->units ?? 0)],
                'sales' => ['money', $this->money($row->sales ?? null)],
            ];
        };

        return [
            ['product' => 'text', 'category' => 'text', 'units' => 'integer', 'orders' => 'integer', 'sales' => 'money'],
            $rows,
            $totals($from, $to),
            $this->values($totals($previousFrom, $previousTo)),
        ];
    }

    /**
     * Sales per Category (the Product's own Category, with its parent), and each one's share.
     *
     * @return array{0: array<string, string>, 1: list<array<string, string|int|null>>, 2: array<string, array{0: string, 1: string|int}>, 3: array<string, string|int>}
     */
    private function categories(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, CarbonImmutable $previousTo): array
    {
        $found = $this->soldLines($from, $to)
            ->join('products', 'products.id', '=', 'variants.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('categories AS parents', 'parents.id', '=', 'categories.parent_id')
            ->groupBy('categories.id')
            ->selectRaw('categories.id, MAX(categories.name) AS category, MAX(parents.name) AS parent')
            ->selectRaw('COUNT(DISTINCT variants.product_id) AS products, SUM(order_lines.quantity) AS units, SUM(order_lines.line_total) AS sales')
            ->orderByDesc('sales')
            ->orderBy('categories.id')
            ->toBase()
            ->get();
        $total = $this->money($found->reduce(fn (string $sum, object $row): string => bcadd($sum, (string) $row->sales, 2), '0'));
        $rows = $found->map(fn (object $row): array => [
            'category' => (string) $row->category,
            'parent' => $row->parent === null ? null : (string) $row->parent,
            'products' => (int) $row->products,
            'units' => (int) $row->units,
            'sales' => $this->money($row->sales),
            'share' => $this->percent((string) $row->sales, $total),
        ])->all();

        $totals = function (CarbonImmutable $start, CarbonImmutable $end): array {
            $row = $this->soldLines($start, $end)
                ->join('products', 'products.id', '=', 'variants.product_id')
                ->toBase()
                ->selectRaw('COUNT(DISTINCT products.category_id) AS categories, SUM(order_lines.quantity) AS units, SUM(order_lines.line_total) AS sales')
                ->first();

            return [
                'categories' => ['integer', (int) ($row->categories ?? 0)],
                'units' => ['integer', (int) ($row->units ?? 0)],
                'sales' => ['money', $this->money($row->sales ?? null)],
            ];
        };

        return [
            ['category' => 'text', 'parent' => 'text', 'products' => 'integer', 'units' => 'integer', 'sales' => 'money', 'share' => 'percent'],
            $rows,
            $totals($from, $to),
            $this->values($totals($previousFrom, $previousTo)),
        ];
    }

    /**
     * The Customers who placed Counted orders in the range, top spenders first; New when
     * their first Counted order ever falls in the range, Returning otherwise.
     *
     * @return array{0: array<string, string>, 1: list<array<string, string|int|null>>, 2: array<string, array{0: string, 1: string|int}>, 3: array<string, string|int>}
     */
    private function customers(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, CarbonImmutable $previousTo): array
    {
        $spenders = fn (CarbonImmutable $start, CarbonImmutable $end) => $this->countedOrders($start, $end)
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->joinSub(
                Order::query()->counted()->toBase()->selectRaw('customer_id, MIN(placed_at) AS first_placed_at')->groupBy('customer_id'),
                'firsts',
                'firsts.customer_id',
                '=',
                'orders.customer_id',
            )
            ->groupBy('orders.customer_id')
            ->selectRaw('orders.customer_id, MAX(customers.name) AS customer, MAX(customers.email) AS email')
            ->selectRaw('COUNT(*) AS orders, SUM(orders.total) AS revenue, MAX(firsts.first_placed_at) AS first_placed_at')
            ->toBase();

        $rows = $spenders($from, $to)
            ->orderByDesc('revenue')
            ->orderBy('orders.customer_id')
            ->get()
            ->map(function (object $row) use ($from): array {
                $revenue = $this->money($row->revenue);

                return [
                    'customer' => (string) $row->customer,
                    'email' => $row->email === null ? null : (string) $row->email,
                    'type' => CarbonImmutable::parse((string) $row->first_placed_at)->lessThan($from) ? 'returning' : 'new',
                    'orders' => (int) $row->orders,
                    'revenue' => $revenue,
                    'average_order_value' => $this->average($revenue, (int) $row->orders),
                ];
            })
            ->all();

        $totals = function (CarbonImmutable $start, CarbonImmutable $end) use ($spenders): array {
            $row = DB::query()->fromSub($spenders($start, $end), 'spenders')
                ->selectRaw('COUNT(*) AS customers, SUM(revenue) AS revenue')
                ->selectRaw('COALESCE(SUM(CASE WHEN first_placed_at >= ? THEN 1 ELSE 0 END), 0) AS new_customers', [$start])
                ->first();
            $customers = (int) ($row->customers ?? 0);
            $new = (int) ($row->new_customers ?? 0);

            return [
                'customers' => ['integer', $customers],
                'new_customers' => ['integer', $new],
                'returning_customers' => ['integer', $customers - $new],
                'revenue' => ['money', $this->money($row->revenue ?? null)],
            ];
        };

        return [
            ['customer' => 'text', 'email' => 'text', 'type' => 'enum', 'orders' => 'integer', 'revenue' => 'money', 'average_order_value' => 'money'],
            $rows,
            $totals($from, $to),
            $this->values($totals($previousFrom, $previousTo)),
        ];
    }

    /**
     * Returns Requested in the range by reason (every reason listed), with the return rate:
     * Returns per 100 Counted orders placed in the same range.
     *
     * @return array{0: array<string, string>, 1: list<array<string, string|int|null>>, 2: array<string, array{0: string, 1: string|int}>, 3: array<string, string|int>}
     */
    private function returns(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, CarbonImmutable $previousTo): array
    {
        $byReason = fn (CarbonImmutable $start, CarbonImmutable $end) => OrderReturn::query()
            ->whereBetween('returns.requested_at', [$start, $end->endOfDay()])
            ->leftJoinSub(
                DB::table('return_lines')->selectRaw('return_id, SUM(quantity) AS units')->groupBy('return_id'),
                'lines',
                'lines.return_id',
                '=',
                'returns.id',
            )
            ->groupBy('returns.reason')
            ->selectRaw('returns.reason, COUNT(*) AS returns, COALESCE(SUM(lines.units), 0) AS units, COALESCE(SUM(returns.refund_amount), 0) AS refunded')
            ->toBase()
            ->get()
            ->keyBy('reason');

        $found = $byReason($from, $to);
        $returns = (int) $found->sum('returns');
        $rows = array_map(function (ReturnReason $reason) use ($found, $returns): array {
            $row = $found->get($reason->value);

            return [
                'reason' => $reason->value,
                'returns' => (int) ($row->returns ?? 0),
                'units' => (int) ($row->units ?? 0),
                'refunded' => $this->money($row->refunded ?? null),
                'share' => $this->percent((string) ($row->returns ?? 0), (string) $returns),
            ];
        }, ReturnReason::cases());

        $totals = function (CarbonImmutable $start, CarbonImmutable $end) use ($byReason): array {
            $found = $byReason($start, $end);
            $returns = (int) $found->sum('returns');

            return [
                'returns' => ['integer', $returns],
                'units' => ['integer', (int) $found->sum('units')],
                'refunded' => ['money', $this->money($found->reduce(fn (string $sum, object $row): string => bcadd($sum, (string) $row->refunded, 2), '0'))],
                'return_rate' => ['percent', $this->percent((string) $returns, (string) $this->countedOrders($start, $end)->count())],
            ];
        };

        return [
            ['reason' => 'enum', 'returns' => 'integer', 'units' => 'integer', 'refunded' => 'money', 'share' => 'percent'],
            $rows,
            $totals($from, $to),
            $this->values($totals($previousFrom, $previousTo)),
        ];
    }

    /**
     * Stock now, per Variant across the active Warehouses: On-hand, Reserved, Available,
     * how many Warehouses are at or below its Low-stock threshold, and Stock value. The
     * summary adds the units received and deducted for Orders in the range.
     *
     * @return array{0: array<string, string>, 1: list<array<string, string|int|null>>, 2: array<string, array{0: string, 1: string|int}>, 3: array<string, string|int>}
     */
    private function inventory(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, CarbonImmutable $previousTo): array
    {
        $threshold = $this->settings->lowStockThreshold();
        $levels = Variant::query()
            ->join('products', 'products.id', '=', 'variants.product_id')
            ->crossJoin('warehouses')
            ->leftJoin('stock_balances', fn (JoinClause $join) => $join
                ->on('stock_balances.variant_id', '=', 'variants.id')
                ->on('stock_balances.warehouse_id', '=', 'warehouses.id'))
            ->where('warehouses.is_active', true)
            ->groupBy('variants.id')
            ->selectRaw('variants.id, MAX(variants.sku) AS sku, MAX(products.name) AS product, MAX(variants.average_cost) AS average_cost')
            ->selectRaw('COALESCE(MAX(variants.low_stock_threshold), ?) AS threshold', [$threshold])
            ->selectRaw('COALESCE(SUM(stock_balances.on_hand), 0) AS on_hand, COALESCE(SUM(stock_balances.reserved), 0) AS reserved')
            ->selectRaw('COALESCE(SUM(stock_balances.available), 0) AS available')
            ->selectRaw('SUM(CASE WHEN COALESCE(stock_balances.available, 0) <= COALESCE(variants.low_stock_threshold, ?) THEN 1 ELSE 0 END) AS low_stock', [$threshold])
            ->selectRaw('SUM(CAST(COALESCE(stock_balances.on_hand, 0) * variants.average_cost AS DECIMAL(20, 2))) AS stock_value')
            ->orderByDesc('low_stock')
            ->orderBy('sku')
            ->toBase()
            ->get();

        $rows = $levels->map(fn (object $row): array => [
            'sku' => (string) $row->sku,
            'product' => (string) $row->product,
            'on_hand' => (int) $row->on_hand,
            'reserved' => (int) $row->reserved,
            'available' => (int) $row->available,
            'threshold' => (int) $row->threshold,
            'low_stock' => (int) $row->low_stock,
            'average_cost' => $this->money($row->average_cost),
            'stock_value' => $this->money($row->stock_value),
        ])->all();

        $movements = fn (CarbonImmutable $start, CarbonImmutable $end): array => [
            'units_received' => ['integer', (int) DB::table('stock_movements')
                ->where('type', StockMovementType::Receipt->value)
                ->whereBetween('created_at', [$start, $end->endOfDay()])
                ->sum('on_hand_delta')],
            'units_deducted' => ['integer', (int) -DB::table('stock_movements')
                ->where('type', StockMovementType::Deduction->value)
                ->whereBetween('created_at', [$start, $end->endOfDay()])
                ->sum('on_hand_delta')],
        ];
        $current = [
            'stock_value' => ['money', $this->money($levels->reduce(fn (string $sum, object $row): string => bcadd($sum, (string) $row->stock_value, 2), '0'))],
            'on_hand' => ['integer', (int) $levels->sum('on_hand')],
            'low_stock' => ['integer', (int) $levels->sum('low_stock')],
            ...$movements($from, $to),
        ];

        return [
            [
                'sku' => 'text', 'product' => 'text', 'on_hand' => 'integer', 'reserved' => 'integer', 'available' => 'integer',
                'threshold' => 'integer', 'low_stock' => 'integer', 'average_cost' => 'money', 'stock_value' => 'money',
            ],
            $rows,
            $current,
            $this->values($movements($previousFrom, $previousTo)),
        ];
    }

    /**
     * @return Builder<Order>
     */
    private function countedOrders(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Order::query()->counted()->whereBetween('orders.placed_at', [$from, $to->endOfDay()]);
    }

    /**
     * The lines of the range's Counted orders, joined to their Variants (as the dashboard's
     * top Products reads them).
     *
     * @return Builder<OrderLine>
     */
    private function soldLines(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return OrderLine::query()
            ->join('variants', 'variants.id', '=', 'order_lines.variant_id')
            ->whereIn('order_lines.order_id', $this->countedOrders($from, $to)->select('orders.id'))
            ->where('order_lines.quantity', '>', 0);
    }

    /**
     * @param  array<string, array{0: string, 1: string|int}>  $figures
     * @return array<string, string|int>
     */
    private function values(array $figures): array
    {
        return array_map(fn (array $figure): string|int => $figure[1], $figures);
    }

    private function money(mixed $amount): string
    {
        return bcadd(is_numeric($amount) ? (string) $amount : '0', '0', 2);
    }

    /** Revenue ÷ Orders, half up to cents (as the dashboard's Average order value). */
    private function average(string $revenue, int $orders): string
    {
        return $orders === 0 ? '0.00' : bcadd(bcdiv($revenue, (string) $orders, 3), '0.005', 2);
    }

    /** The part as a percentage of the whole, half up to one decimal; 0.0 for an empty whole. */
    private function percent(string $part, string $whole): string
    {
        if (bccomp($whole, '0', 2) === 0) {
            return '0.0';
        }

        return bcadd(bcdiv(bcmul($part, '100', 4), $whole, 4), '0.05', 1);
    }

    /**
     * The percentage change, to one decimal; null on a zero baseline (unless both are zero).
     */
    private function change(string $current, string $previous): ?float
    {
        if (bccomp($previous, '0', 2) === 0) {
            return bccomp($current, '0', 2) === 0 ? 0.0 : null;
        }

        return round((float) bcdiv(bcmul(bcsub($current, $previous, 2), '100', 4), $previous, 4), 1);
    }
}
