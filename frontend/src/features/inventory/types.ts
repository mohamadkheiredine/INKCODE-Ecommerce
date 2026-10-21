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

export const MOVEMENT_TYPES = [
  'receipt',
  'adjustment',
  'transfer_out',
  'transfer_in',
  'reservation',
  'release',
  'deduction',
  'cancellation_return',
  'return_restock',
] as const
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

export const ADJUSTMENT_REASONS = ['count_correction', 'damaged', 'lost', 'found'] as const
export type AdjustmentReason = (typeof ADJUSTMENT_REASONS)[number]

export type AdjustmentInput = {
  warehouse_id: number
  variant_id: number
  quantity_delta: number
  reason: AdjustmentReason
  notes: string | null
}

export type Adjustment = AdjustmentInput & { id: number; number: string; created_at: string }

export const TRANSFER_STATUSES = ['draft', 'in_transit', 'received', 'cancelled'] as const
export type TransferStatus = (typeof TRANSFER_STATUSES)[number]

/** Draft → in transit (send) → received; only a Draft can be cancelled. */
export type Transfer = {
  id: number
  number: string
  status: TransferStatus
  source_warehouse: WarehouseRef
  destination_warehouse: WarehouseRef
  notes: string | null
  user: { id: number; name: string } | null
  units?: number
  created_at: string
}

export type TransferInput = {
  source_warehouse_id: number
  destination_warehouse_id: number
  notes: string | null
  lines: { variant_id: number; quantity: number }[]
}

export type TransferStep = 'send' | 'receive' | 'cancel'
