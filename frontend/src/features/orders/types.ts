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
  /** Units removed by Partial cancellation. */
  cancelled_quantity: number
  /** Units already in the Order's Shipments. */
  shipped_quantity: number
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

/** Where a Shipment is, mirroring `App\Enums\ShipmentStatus`. */
export type ShipmentStatus = 'shipped' | 'delivered'

export type ShipmentStatusChange = {
  id: number
  from_status: ShipmentStatus | null
  to_status: ShipmentStatus
  user: { id: number; name: string } | null
  created_at: string
}

/** A parcel carrying some units of the Order's lines, with its Carrier and tracking number. */
export type Shipment = {
  id: number
  number: string
  status: ShipmentStatus
  carrier: { id: number; name: string }
  tracking_number: string
  /** The Carrier's tracking page, or null when the Carrier has no tracking link. */
  tracking_url: string | null
  shipped_at: string
  delivered_at: string | null
  lines: { order_line_id: number; quantity: number }[]
  status_history: ShipmentStatusChange[]
}

/** Shipment progress, separate from the Order status. */
export type Fulfillment = {
  status: 'unfulfilled' | 'partially_shipped' | 'shipped' | 'delivered'
  shipped_units: number
  units: number
}

/** Units of each line a new Shipment carries. */
export type ShipmentInput = {
  carrier_id: number
  tracking_number: string
  lines: { line_id: number; quantity: number }[]
}

/** One entry of an Order's timeline (oldest first): a status change, a Shipment step or a note. */
export type TimelineEvent =
  | ({ type: 'status' } & OrderStatusChange)
  | ({ type: 'shipment'; shipment: { id: number; number: string } } & ShipmentStatusChange)
  | {
      type: 'note'
      id: number
      body: string
      user: { id: number; name: string } | null
      created_at: string
    }

export type UserRef = { id: number; name: string }

/** Amounts are decimal strings. The list has `units`; one Order has `lines` and history. */
export type Order = {
  id: number
  number: string
  status: OrderStatus
  /** From Preparing onward (and once Cancelled): lines, quantities and addresses are fixed. */
  is_locked: boolean
  /** The statuses the Order flow lets it move to next. */
  allowed_moves: OrderStatus[]
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
  user?: UserRef | null
  assignee?: UserRef | null
  units?: number
  lines?: OrderLine[]
  status_history?: OrderStatusChange[]
  shipments?: Shipment[]
  fulfillment?: Fulfillment
  timeline?: TimelineEvent[]
  created_at: string
}

export type OrderLineInput = { variant_id: number; quantity: number }

/** The new lines, addresses and Shipping method of a Pending or Confirmed Order. */
export type OrderUpdateInput = {
  billing_address_id: number
  shipping_address_id: number
  shipping_method_id: number
  lines: OrderLineInput[]
}

/** Partial cancellation: units of each line to cancel. */
export type LineCancellation = { line_id: number; quantity: number }

/** Statuses whose Order page shows Shipments (from Preparing, before they can be created). */
export const SHIPMENT_STATUSES: readonly OrderStatus[] = [
  'preparing',
  'ready_for_shipping',
  'shipped',
  'delivered',
]

/** The statuses an Order moves forward to by hand (Shipped comes with Shipments). */
export const ADVANCE_STATUSES: readonly OrderStatus[] = ['preparing', 'ready_for_shipping']

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
  lines: Omit<OrderLine, 'id' | 'product_id' | 'cancelled_quantity' | 'shipped_quantity'>[]
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
