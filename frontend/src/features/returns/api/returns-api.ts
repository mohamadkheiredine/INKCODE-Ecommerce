import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type { OrderReturn, RestockDecision, ReturnRequestInput } from '../types'

export const returnKeys = {
  all: ['returns'] as const,
  list: (query: ListQuery) => [...returnKeys.all, 'list', query] as const,
  detail: (id: number) => [...returnKeys.all, 'detail', id] as const,
}

/** Stock and money steps take an Idempotency-Key, so a retried click is applied once. */
const idempotent = (key: string) => ({ headers: { 'Idempotency-Key': key } })

export const returnsApi = {
  async list(query: ListQuery): Promise<ListPage<OrderReturn>> {
    const { data } = await http.get<ListPage<OrderReturn>>('/returns', {
      params: toApiParams(query),
    })
    return data
  },

  async get(id: number): Promise<OrderReturn> {
    const { data } = await http.get<{ data: OrderReturn }>(`/returns/${id}`)
    return data.data
  },

  async request(orderId: number, input: ReturnRequestInput, idempotencyKey: string) {
    const { data } = await http.post<{ data: OrderReturn }>(
      `/orders/${orderId}/returns`,
      { ...input, note: input.note || null },
      idempotent(idempotencyKey),
    )
    return data.data
  },

  async decide(id: number, decision: 'approve' | 'reject', note: string) {
    const { data } = await http.post<{ data: OrderReturn }>(`/returns/${id}/${decision}`, {
      note: note || null,
    })
    return data.data
  },

  async receive(id: number) {
    const { data } = await http.post<{ data: OrderReturn }>(`/returns/${id}/receive`)
    return data.data
  },

  async restock(
    id: number,
    lines: { line_id: number; decision: RestockDecision }[],
    idempotencyKey: string,
  ) {
    const { data } = await http.post<{ data: OrderReturn }>(
      `/returns/${id}/restock`,
      { lines },
      idempotent(idempotencyKey),
    )
    return data.data
  },

  async refund(
    id: number,
    input: { amount: string; refund_shipping: boolean },
    idempotencyKey: string,
  ) {
    const { data } = await http.post<{ data: OrderReturn }>(
      `/returns/${id}/refund`,
      input,
      idempotent(idempotencyKey),
    )
    return data.data
  },
}
