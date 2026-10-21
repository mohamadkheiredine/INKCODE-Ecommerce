<?php

namespace App\Actions\Dashboard;

use App\Actions\Inventory\ListStockLevels;
use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderReturn;
use App\Support\ListParameters;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The executive dashboard for a date range, every figure computed from Counted orders
 * ({@see Order::counted()}) dated by the day they were placed, and compared with the
 * previous period of the same length:
 *
 * - Revenue: what Customers were charged (the Orders' totals: items − discount + VAT + shipping).
 * - Net sales: items − discount − Refunds, without VAT or shipping.
 * - Average order value: Revenue ÷ Counted orders.
 * - Pending fulfillment: Counted orders not Shipped yet; Customers: who placed them.
 * - Cancellations: Orders placed in the range and Cancelled; Returns: Requested in the range.
 * - Low-stock items: Variant rows in an active Warehouse at or below their threshold (now).
 *
 * A change is the percentage against the previous period, or null when that period had
 * nothing to compare with (a zero baseline).
 */
final class ShowDashboard
{
    private const int LIST_SIZE = 5;

    public function __construct(private readonly ListStockLevels $stockLevels) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->startOfDay();
        $days = (int) $from->diffInDays($to) + 1;
        $previousTo = $from->subDay();
        $previousFrom = $previousTo->subDays($days - 1);

        $current = $this->totals($from, $to);
        $previous = $this->totals($previousFrom, $previousTo);
        $lowStock = $this->lowStockCount();

        $kpis = [];
        foreach ($current as $name => $value) {
            $kpis[$name] = ['value' => $value, 'previous' => $previous[$name], 'change' => $this->change((string) $value, (string) $previous[$name])];
        }
        $kpis['low_stock'] = ['value' => $lowStock, 'previous' => null, 'change' => null];

        return [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'previous_from' => $previousFrom->toDateString(),
                'previous_to' => $previousTo->toDateString(),
            ],
            'kpis' => $kpis,
            'revenue_by_day' => $this->revenueByDay($from, $days, $previousFrom),
            'status_breakdown' => $this->statusBreakdown($from, $to),
            'top_products' => $this->topProducts($from, $to),
            'recent_orders' => $this->recentOrders($from, $to),
            'alerts' => $this->alerts($lowStock),
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
     * @return array{revenue: string, net_sales: string, orders: int, pending_fulfillment: int, average_order_value: string, customers: int, cancellations: int, returns: int}
     */
    private function totals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $pending = [OrderStatus::Confirmed->value, OrderStatus::Preparing->value, OrderStatus::ReadyForShipping->value];
        $row = $this->countedOrders($from, $to)->toBase()
            ->selectRaw('COUNT(*) AS orders')
            ->selectRaw('COALESCE(SUM(total), 0) AS revenue')
            ->selectRaw('COALESCE(SUM(items_total - discount_total - refunded_amount), 0) AS net_sales')
            ->selectRaw('COUNT(DISTINCT customer_id) AS customers')
            ->selectRaw('COALESCE(SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END), 0) AS pending_fulfillment', $pending)
            ->first();

        $orders = (int) ($row->orders ?? 0);
        $revenue = bcadd((string) ($row->revenue ?? '0'), '0', 2);

        return [
            'revenue' => $revenue,
            'net_sales' => bcadd((string) ($row->net_sales ?? '0'), '0', 2),
            'orders' => $orders,
            'pending_fulfillment' => (int) ($row->pending_fulfillment ?? 0),
            'average_order_value' => $orders === 0 ? '0.00' : $this->roundToCents(bcdiv($revenue, (string) $orders, 3)),
            'customers' => (int) ($row->customers ?? 0),
            'cancellations' => Order::query()
                ->where('status', OrderStatus::Cancelled)
                ->whereBetween('placed_at', [$from, $to->endOfDay()])
                ->count(),
            'returns' => OrderReturn::query()->whereBetween('requested_at', [$from, $to->endOfDay()])->count(),
        ];
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

    /**
     * One point per day of the range, with the same day of the previous period beside it.
     *
     * @return list<array{date: string, revenue: string, previous_revenue: string}>
     */
    private function revenueByDay(CarbonImmutable $from, int $days, CarbonImmutable $previousFrom): array
    {
        $daily = fn (CarbonImmutable $start): array => $this->countedOrders($start, $start->addDays($days - 1))->toBase()
            ->selectRaw('DATE(placed_at) AS day, SUM(total) AS revenue')
            ->groupByRaw('DATE(placed_at)')
            ->pluck('revenue', 'day')
            ->all();
        $current = $daily($from);
        $previous = $daily($previousFrom);

        $points = [];
        for ($day = 0; $day < $days; $day++) {
            $date = $from->addDays($day)->toDateString();
            $previousDate = $previousFrom->addDays($day)->toDateString();
            $points[] = [
                'date' => $date,
                'revenue' => bcadd((string) ($current[$date] ?? '0'), '0', 2),
                'previous_revenue' => bcadd((string) ($previous[$previousDate] ?? '0'), '0', 2),
            ];
        }

        return $points;
    }

    /**
     * How many Counted orders are in each Counted status now (every status, zeros included).
     *
     * @return list<array{status: string, count: int}>
     */
    private function statusBreakdown(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = $this->countedOrders($from, $to)->toBase()
            ->selectRaw('status, COUNT(*) AS orders')
            ->groupBy('status')
            ->pluck('orders', 'status')
            ->all();

        return array_map(
            fn (OrderStatus $status): array => ['status' => $status->value, 'count' => (int) ($counts[$status->value] ?? 0)],
            Order::countedStatuses(),
        );
    }

    /**
     * The Products that sold the most (by their lines' totals), with the units still ordered.
     *
     * @return list<array{product_id: int, name: string, units: int, sales: string}>
     */
    private function topProducts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return OrderLine::query()
            ->join('variants', 'variants.id', '=', 'order_lines.variant_id')
            ->whereIn('order_lines.order_id', $this->countedOrders($from, $to)->select('orders.id'))
            ->where('order_lines.quantity', '>', 0)
            ->groupBy('variants.product_id')
            ->selectRaw('variants.product_id, MAX(order_lines.product_name) AS name, SUM(order_lines.quantity) AS units, SUM(order_lines.line_total) AS sales')
            ->orderByDesc('sales')
            ->orderBy('variants.product_id')
            ->limit(self::LIST_SIZE)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'units' => (int) $row->units,
                'sales' => bcadd((string) $row->sales, '0', 2),
            ])
            ->all();
    }

    /**
     * The latest Counted orders placed in the range.
     *
     * @return list<array<string, mixed>>
     */
    private function recentOrders(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->countedOrders($from, $to)
            ->with('customer:id,name')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->limit(self::LIST_SIZE)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'total' => $order->total,
                'placed_at' => $order->placed_at->toIso8601String(),
                'customer' => ['id' => $order->customer->id, 'name' => $order->customer->name],
            ])
            ->all();
    }

    /**
     * What needs someone's attention now; only the alerts with something in them.
     *
     * @return list<array{type: string, count: int}>
     */
    private function alerts(int $lowStock): array
    {
        $alerts = [
            'low_stock' => $lowStock,
            'orders_awaiting_confirmation' => Order::query()->where('status', OrderStatus::Pending)->count(),
            'returns_awaiting_decision' => OrderReturn::query()->where('status', ReturnStatus::Requested)->count(),
        ];

        $raised = array_filter($alerts);

        return array_map(
            fn (string $type, int $count): array => ['type' => $type, 'count' => $count],
            array_keys($raised),
            $raised,
        );
    }

    private function lowStockCount(): int
    {
        return $this->stockLevels->count(new ListParameters(null, ['low_stock' => '1'], 'sku', false, 1));
    }

    /** Half up to two decimals (amounts here are never negative). */
    private function roundToCents(string $amount): string
    {
        return bcadd($amount, '0.005', 2);
    }
}
