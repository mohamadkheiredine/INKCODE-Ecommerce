import type { OrderStatus } from '@/features/orders/types'
import type { PaymentStatus } from '@/features/payments/types'

/** A KPI for the range, with the previous period's value and the change in percent. */
export type Kpi<T> = {
  value: T
  /** Null for a figure that is a snapshot of now (low-stock items). */
  previous: T | null
  /** Null on a zero baseline: the previous period had nothing to compare with. */
  change: number | null
}

export type MoneyKpiName = 'revenue' | 'net_sales' | 'average_order_value'
export type CountKpiName =
  'orders' | 'pending_fulfillment' | 'customers' | 'cancellations' | 'returns' | 'low_stock'

export type DashboardAlertType =
  'low_stock' | 'orders_awaiting_confirmation' | 'returns_awaiting_decision'

/** The executive dashboard, every figure computed by the API from Counted orders. */
export type Dashboard = {
  range: { from: string; to: string; previous_from: string; previous_to: string }
  kpis: Record<MoneyKpiName, Kpi<`${number}`>> & Record<CountKpiName, Kpi<number>>
  revenue_by_day: { date: string; revenue: `${number}`; previous_revenue: `${number}` }[]
  status_breakdown: { status: OrderStatus; count: number }[]
  top_products: { product_id: number; name: string; units: number; sales: `${number}` }[]
  recent_orders: {
    id: number
    number: string
    status: OrderStatus
    payment_status: PaymentStatus
    total: `${number}`
    placed_at: string
    customer: { id: number; name: string }
  }[]
  alerts: { type: DashboardAlertType; count: number }[]
}

/** The date presets of the range picker; `custom` reads `from` and `to` from the URL. */
export const DATE_PRESETS = ['today', '7d', '30d', 'month', 'custom'] as const
export type DatePreset = (typeof DATE_PRESETS)[number]

export type DateRange = { from: string; to: string }
