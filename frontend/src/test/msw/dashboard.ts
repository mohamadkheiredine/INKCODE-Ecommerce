import type { Dashboard } from '@/features/dashboard/types'

const kpi = <T>(value: T, previous: T | null = value, change: number | null = 0) => ({
  value,
  previous,
  change,
})

/** A dashboard with no Counted orders in the range (what an empty store returns). */
export function anEmptyDashboard(from = '2026-09-16', to = '2026-10-15'): Dashboard {
  return {
    range: { from, to, previous_from: '2026-08-17', previous_to: '2026-09-15' },
    kpis: {
      revenue: kpi<`${number}`>('0.00'),
      net_sales: kpi<`${number}`>('0.00'),
      orders: kpi(0),
      pending_fulfillment: kpi(0),
      average_order_value: kpi<`${number}`>('0.00'),
      customers: kpi(0),
      cancellations: kpi(0),
      returns: kpi(0),
      low_stock: kpi(0, null, null),
    },
    revenue_by_day: [{ date: to, revenue: '0.00', previous_revenue: '0.00' }],
    status_breakdown: [
      { status: 'confirmed', count: 0 },
      { status: 'preparing', count: 0 },
      { status: 'ready_for_shipping', count: 0 },
      { status: 'shipped', count: 0 },
      { status: 'delivered', count: 0 },
    ],
    top_products: [],
    recent_orders: [],
    alerts: [],
  }
}

/** The worked example: one Delivered Order of $99.50 with $20 refunded, and a busier store. */
export function aDashboard(overrides: Partial<Dashboard> = {}): Dashboard {
  const empty = anEmptyDashboard()
  return {
    ...empty,
    kpis: {
      revenue: kpi<`${number}`>('99.50', '50.00', 99),
      net_sales: kpi<`${number}`>('70.00', '0.00', null),
      orders: kpi(1, 2, -50),
      pending_fulfillment: kpi(0, 1, -100),
      average_order_value: kpi<`${number}`>('99.50', '25.00', 298),
      customers: kpi(1, 2, -50),
      cancellations: kpi(2, 1, 100),
      returns: kpi(0),
      low_stock: kpi(3, null, null),
    },
    status_breakdown: empty.status_breakdown.map((slice) =>
      slice.status === 'delivered' ? { ...slice, count: 1 } : slice,
    ),
    top_products: [{ product_id: 4, name: 'Classic Tee', units: 3, sales: '64.50' }],
    recent_orders: [
      {
        id: 12,
        number: 'ORD-2026-00012',
        status: 'delivered',
        payment_status: 'paid',
        total: '99.50',
        placed_at: '2026-10-15T09:00:00+00:00',
        customer: { id: 3, name: 'Maya Haddad' },
      },
    ],
    alerts: [
      { type: 'low_stock', count: 3 },
      { type: 'orders_awaiting_confirmation', count: 1 },
    ],
    ...overrides,
  }
}
