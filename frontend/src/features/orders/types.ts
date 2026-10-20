import type { WarehouseRef, VariantOption } from '@/features/inventory/types'
import type { Amount } from '@/lib/format'

/** Where an Order is in its life, mirroring `App\Enums\OrderStatus`. */
export const ORDER_STATUSES = [
  'pending',
  'confirmed',
  'preparing',
  'ready_for_shipping',
  'shipped',
  'delivered',
  'cancelled',
] as const
export type OrderStatus = (typeof ORDER_STATUSES)[number]

/** Statuses an Order may still be cancelled from (before Shipped). */
export const CANCELLABLE_STATUSES: readonly OrderStatus[] = [
  'pending',
  'confirmed',
  'preparing',
  'ready_for_shipping',
]

/** An address as the Order keeps it: a snapshot, unchanged when the Customer edits theirs. */
export type OrderAddress = {
  recipient_name: string
  phone: string | null
  country_code: string
  city: string
  line1: string
  line2: string | null
  postal_code: string | null
}

/** One Variant on an Order, as it was sold (name, SKU, options, prices and VAT). */
export type OrderLine = {
  id: number
  variant_id: number
  product_id: number | null
  product_name: string
  sku: string
  options: VariantOption[]
  quantity: number
  unit_price: Amount
  discount_amount: Amount
  taxable: boolean
  vat_rate: Amount
  vat_amount: Amount
  line_total: Amount
}

export type OrderStatusChange = {
  id: number
  from_status: OrderStatus | null
  to_status: OrderStatus
  note: string | null
  user: { id: number; name: string } | null
  created_at: string
}

/** Amounts are decimal strings. The list has `units`; one Order has `lines` and history. */
export type Order = {
  id: number
  number: string
  status: OrderStatus
  placed_at: string
  customer: { id: number; name: string; email: string | null; phone: string | null }
  warehouse: WarehouseRef
  shipping_method: { id: number; name: string }
  billing_address: OrderAddress
  shipping_address: OrderAddress
  currency_code: string
  vat_rate: Amount
  items_total: Amount
  discount_total: Amount
  vat_total: Amount
  shipping_total: Amount
  total: Amount
  notes: string | null
  user?: { id: number; name: string } | null
  units?: number
  lines?: OrderLine[]
  status_history?: OrderStatusChange[]
  created_at: string
}

export type OrderLineInput = { variant_id: number; quantity: number }

export type OrderInput = {
  customer_id: number
  billing_address_id: number
  shipping_address_id: number
  warehouse_id: number
  shipping_method_id: number
  notes: string
  lines: OrderLineInput[]
}

/** A line the Fulfilling warehouse lacks stock for. */
export type StockShortage = {
  variant_id: number
  sku: string
  requested: number
  available: number
}

/** The live quote: lines priced as the Order will be, and each Warehouse's shortages. */
export type OrderQuote = {
  vat_rate: Amount
  items_total: Amount
  discount_total: Amount
  vat_total: Amount
  shipping_total: Amount
  total: Amount
  lines: Omit<OrderLine, 'id' | 'product_id'>[]
  warehouses: (WarehouseRef & { is_sufficient: boolean; shortages: StockShortage[] })[]
}

/** A Variant the Order form can add (its Product is Active). */
export type SellableVariant = {
  id: number
  sku: string
  options: VariantOption[]
  price: Amount
  product: { id: number; name: string }
  available: number
}
