import { z } from 'zod'
import {
  ADJUSTMENT_REASONS,
  type ReceiptInput,
  type Warehouse,
  type WarehouseInput,
} from '../types'

/* Warehouse ---------------------------------------------------------------------- */

/** As StoreWarehouseRequest: the code is kept in capitals. */
export const warehouseSchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, 'inventory:validation.nameRequired')
    .max(120, 'inventory:validation.nameTooLong'),
  code: z
    .string()
    .trim()
    .transform((code) => code.toUpperCase())
    .pipe(z.string().regex(/^[A-Z0-9-]{2,16}$/, 'inventory:validation.codeFormat')),
  address: z
    .string()
    .trim()
    .min(1, 'inventory:validation.addressRequired')
    .max(255, 'inventory:validation.addressTooLong'),
  is_active: z.boolean(),
})

export type WarehouseFormValues = z.input<typeof warehouseSchema>

export function warehouseFormValues(warehouse: Warehouse | null): WarehouseFormValues {
  return {
    name: warehouse?.name ?? '',
    code: warehouse?.code ?? '',
    address: warehouse?.address ?? '',
    is_active: warehouse?.is_active ?? true,
  }
}

export type WarehouseFormOutput = z.output<typeof warehouseSchema> & WarehouseInput

/* Receipt ------------------------------------------------------------------------ */

/** As StoreReceiptRequest: whole quantities, costs with up to four decimals. */
const COST_PATTERN = /^\d{1,8}(\.\d{1,4})?$/

export const receiptSchema = z.object({
  warehouse_id: z.string().min(1, 'inventory:validation.warehouseRequired'),
  supplier_reference: z
    .string()
    .trim()
    .min(1, 'inventory:validation.referenceRequired')
    .max(100, 'inventory:validation.referenceTooLong'),
  notes: z.string().trim().max(1000, 'inventory:validation.notesTooLong'),
  lines: z
    .array(
      z.object({
        variant_id: z.number(),
        sku: z.string(),
        label: z.string(),
        quantity: z
          .string()
          .trim()
          .regex(/^[1-9]\d{0,6}$/, 'inventory:validation.quantity')
          .refine((value) => Number(value) <= 1_000_000, 'inventory:validation.quantity'),
        unit_cost: z.string().trim().regex(COST_PATTERN, 'inventory:validation.unitCost'),
      }),
    )
    .min(1, 'inventory:receipts.noLines'),
})

export type ReceiptFormValues = z.infer<typeof receiptSchema>

export const EMPTY_RECEIPT: ReceiptFormValues = {
  warehouse_id: '',
  supplier_reference: '',
  notes: '',
  lines: [],
}

export function toReceiptInput(values: ReceiptFormValues): ReceiptInput {
  return {
    warehouse_id: Number(values.warehouse_id),
    supplier_reference: values.supplier_reference,
    notes: values.notes,
    lines: values.lines.map((line) => ({
      variant_id: line.variant_id,
      quantity: Number(line.quantity),
      unit_cost: line.unit_cost,
    })),
  }
}

/* Adjustment and Transfer (from a Stock level row) ------------------------------- */

/** As StoreAdjustmentRequest: a signed, non-zero quantity and a reason. */
export const adjustmentSchema = z.object({
  quantity: z
    .string()
    .trim()
    .regex(/^-?[1-9]\d{0,6}$/, 'inventory:validation.quantityNotZero'),
  reason: z.enum(ADJUSTMENT_REASONS, { error: 'inventory:validation.reasonRequired' }),
  notes: z.string().trim().max(1000),
})

export type AdjustmentFormValues = z.input<typeof adjustmentSchema>

/** As StoreTransferRequest with one line: another Warehouse and a positive quantity. */
export const transferSchema = z.object({
  destination_warehouse_id: z.string().min(1, 'inventory:validation.destinationRequired'),
  quantity: z
    .string()
    .trim()
    .regex(/^[1-9]\d{0,6}$/, 'inventory:validation.quantityPositive'),
  notes: z.string().trim().max(1000),
})

export type TransferFormValues = z.input<typeof transferSchema>
