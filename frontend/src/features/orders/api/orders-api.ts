import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type { Order, OrderInput, OrderLineInput, OrderQuote, SellableVariant } from '../types'

export const orderKeys = {
  all: ['orders'] as const,
  list: (query: ListQuery) => [...orderKeys.all, 'list', query] as const,
  detail: (id: number) => [...orderKeys.all, 'detail', id] as const,
  quote: (lines: OrderLineInput[], shippingMethodId: number | null) =>
    [...orderKeys.all, 'quote', lines, shippingMethodId] as const,
  sellableVariants: (search: string) => [...orderKeys.all, 'sellable-variants', search] as const,
}

/** Order changes take an Idempotency-Key, so a retried click is applied once. */
const idempotent = (key: string) => ({ headers: { 'Idempotency-Key': key } })

export const ordersApi = {
  async list(query: ListQuery): Promise<ListPage<Order>> {
    const { data } = await http.get<ListPage<Order>>('/orders', { params: toApiParams(query) })
    return data
  },

  async get(id: number): Promise<Order> {
    const { data } = await http.get<{ data: Order }>(`/orders/${id}`)
    return data.data
  },

  async create(input: OrderInput, idempotencyKey: string): Promise<Order> {
    const { data } = await http.post<{ data: Order }>('/orders', input, idempotent(idempotencyKey))
    return data.data
  },

  async confirm(id: number, idempotencyKey: string): Promise<Order> {
    const { data } = await http.post<{ data: Order }>(
      `/orders/${id}/confirm`,
      {},
      idempotent(idempotencyKey),
    )
    return data.data
  },

  async cancel(id: number, note: string, idempotencyKey: string): Promise<Order> {
    const { data } = await http.post<{ data: Order }>(
      `/orders/${id}/cancel`,
      { note: note || null },
      idempotent(idempotencyKey),
    )
    return data.data
  },

  async quote(lines: OrderLineInput[], shippingMethodId: number | null): Promise<OrderQuote> {
    const { data } = await http.post<{ data: OrderQuote }>('/orders/quote', {
      lines,
      shipping_method_id: shippingMethodId,
    })
    return data.data
  },

  async sellableVariants(search: string): Promise<SellableVariant[]> {
    const { data } = await http.get<{ data: SellableVariant[] }>('/orders/sellable-variants', {
      params: { search },
    })
    return data.data
  },
}
