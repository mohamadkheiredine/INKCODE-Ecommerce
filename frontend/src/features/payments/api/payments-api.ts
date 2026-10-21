import type { Order } from '@/features/orders/types'
import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type { Payment, PaymentInput, ReconciliationRow, RefundInput } from '../types'

export const paymentKeys = {
  all: ['payments'] as const,
  list: (query: ListQuery) => [...paymentKeys.all, 'list', query] as const,
  reconciliation: (query: ListQuery) => [...paymentKeys.all, 'reconciliation', query] as const,
}

/** The Order with its new ledger, and the row just recorded. */
export type LedgerWrite = { order: Order; payment: Payment }

/** Payments and Refunds take an Idempotency-Key, so a retried click is recorded once. */
const idempotent = (key: string) => ({ headers: { 'Idempotency-Key': key } })

export const paymentsApi = {
  async list(query: ListQuery): Promise<ListPage<Payment>> {
    const { data } = await http.get<ListPage<Payment>>('/payments', { params: toApiParams(query) })
    return data
  },

  async reconciliation(query: ListQuery): Promise<ListPage<ReconciliationRow>> {
    const { data } = await http.get<ListPage<ReconciliationRow>>('/payments/reconciliation', {
      params: toApiParams(query),
    })
    return data
  },

  async record(orderId: number, input: PaymentInput, idempotencyKey: string): Promise<LedgerWrite> {
    const { data } = await http.post<{ data: Order; payment: Payment }>(
      `/orders/${orderId}/payments`,
      { ...input, note: input.note || null },
      idempotent(idempotencyKey),
    )
    return { order: data.data, payment: data.payment }
  },

  async refund(orderId: number, input: RefundInput, idempotencyKey: string): Promise<LedgerWrite> {
    const { data } = await http.post<{ data: Order; payment: Payment }>(
      `/orders/${orderId}/refunds`,
      { ...input, note: input.note || null },
      idempotent(idempotencyKey),
    )
    return { order: data.data, payment: data.payment }
  },
}
