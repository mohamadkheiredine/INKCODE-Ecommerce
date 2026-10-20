import type { Amount } from '@/lib/format'

export type Warehouse = {
  id: number
  name: string
  code: string
  address: string
  is_active: boolean
  /** Units held, every Variant together (lists only). */
  on_hand?: number
  created_at: string
  updated_at: string
}

export type WarehouseInput = { name: string; code: string; address: string; is_active: boolean }

export type WarehouseRef = Pick<Warehouse, 'id' | 'name' | 'code'>

export type VariantOption = { attribute: string; value: string }

/** One Variant in one Warehouse. Amounts are decimal strings. */
export type StockLevel = {
  variant: { id: number; sku: string; options: VariantOption[] }
  product: { id: number; name: string }
  warehouse: WarehouseRef
  on_hand: number
  reserved: number
  available: number
  average_cost: Amount
  stock_value: Amount
  low_stock_threshold: number
  is_low_stock: boolean
}

export type StockTotals = { on_hand: number; stock_value: Amount }

export const MOVEMENT_TYPES = ['receipt', 'reservation', 'release'] as const
export type StockMovementType = (typeof MOVEMENT_TYPES)[number]

export type StockMovement = {
  id: number
  type: StockMovementType
  variant: {
    id: number
    sku: string
    options: VariantOption[]
    product: { id: number; name: string }
  }
  warehouse: WarehouseRef
  on_hand_delta: number
  reserved_delta: number
  on_hand_after: number
  reserved_after: number
  reason: string | null
  user: { id: number; name: string } | null
  source: { type: string; id: number; label: string | null }
  created_at: string
}

export type ReceiptLine = {
  id: number
  variant: StockMovement['variant']
  quantity: number
  unit_cost: Amount
  line_cost: Amount
}

export type Receipt = {
  id: number
  number: string
  warehouse: WarehouseRef
  supplier_reference: string
  notes: string | null
  user: { id: number; name: string } | null
  units?: number
  total_cost?: Amount
  lines?: ReceiptLine[]
  created_at: string
}

export type ReceiptInput = {
  warehouse_id: number
  supplier_reference: string
  notes: string
  lines: { variant_id: number; quantity: number; unit_cost: string }[]
}

/** "Red / M", or "" for a Variant without Attributes. */
export function optionsLabel(options: VariantOption[]): string {
  return options.map((option) => option.value).join(' / ')
}
