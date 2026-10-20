import { z } from 'zod'
import type { OrderInput, OrderLineInput } from '../types'

const QUANTITY = /^[1-9]\d{0,4}$/

/** As StoreOrderRequest: a Customer and two of their addresses, a Warehouse, a method, lines. */
export const orderSchema = z.object({
  customer_id: z.string().min(1, 'orders:validation.customerRequired'),
  billing_address_id: z.string().min(1, 'orders:validation.addressRequired'),
  shipping_address_id: z.string().min(1, 'orders:validation.addressRequired'),
  warehouse_id: z.string().min(1, 'orders:validation.warehouseRequired'),
  shipping_method_id: z.string().min(1, 'orders:validation.shippingMethodRequired'),
  notes: z.string().trim().max(1000, 'orders:validation.notesTooLong'),
  lines: z
    .array(
      z.object({
        variant_id: z.number(),
        sku: z.string(),
        label: z.string(),
        quantity: z
          .string()
          .trim()
          .regex(QUANTITY, 'orders:validation.quantity')
          .refine((value) => Number(value) <= 10_000, 'orders:validation.quantity'),
      }),
    )
    .min(1, 'orders:validation.noLines'),
})

export type OrderFormValues = z.infer<typeof orderSchema>

export const EMPTY_ORDER: OrderFormValues = {
  customer_id: '',
  billing_address_id: '',
  shipping_address_id: '',
  warehouse_id: '',
  shipping_method_id: '',
  notes: '',
  lines: [],
}

/** The lines worth quoting while the form is filled in: those with a valid quantity. */
export function quotableLines(lines: OrderFormValues['lines']): OrderLineInput[] {
  return lines
    .filter((line) => QUANTITY.test(line.quantity.trim()))
    .map((line) => ({ variant_id: line.variant_id, quantity: Number(line.quantity) }))
}

export function toOrderInput(values: OrderFormValues): OrderInput {
  return {
    customer_id: Number(values.customer_id),
    billing_address_id: Number(values.billing_address_id),
    shipping_address_id: Number(values.shipping_address_id),
    warehouse_id: Number(values.warehouse_id),
    shipping_method_id: Number(values.shipping_method_id),
    notes: values.notes,
    lines: values.lines.map((line) => ({
      variant_id: line.variant_id,
      quantity: Number(line.quantity),
    })),
  }
}
