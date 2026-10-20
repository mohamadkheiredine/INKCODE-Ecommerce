import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type {
  QuoteAddress,
  ShippingMethod,
  ShippingMethodInput,
  ShippingQuote,
  ShippingZone,
  ShippingZoneInput,
} from '../types'

export const shippingZoneKeys = {
  all: ['shipping-zones'] as const,
  lists: () => [...shippingZoneKeys.all, 'list'] as const,
  list: (query: ListQuery) => [...shippingZoneKeys.lists(), query] as const,
  detail: (id: number) => [...shippingZoneKeys.all, 'detail', id] as const,
  quote: (address: QuoteAddress) => [...shippingZoneKeys.all, 'quote', address] as const,
}

export const shippingZonesApi = {
  async list(query: ListQuery): Promise<ListPage<ShippingZone>> {
    const { data } = await http.get<ListPage<ShippingZone>>('/shipping-zones', {
      params: toApiParams(query),
    })
    return data
  },

  async get(id: number): Promise<ShippingZone> {
    const { data } = await http.get<{ data: ShippingZone }>(`/shipping-zones/${id}`)
    return data.data
  },

  async create(input: ShippingZoneInput): Promise<ShippingZone> {
    const { data } = await http.post<{ data: ShippingZone }>('/shipping-zones', input)
    return data.data
  },

  async update(id: number, input: ShippingZoneInput): Promise<ShippingZone> {
    const { data } = await http.put<{ data: ShippingZone }>(`/shipping-zones/${id}`, input)
    return data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/shipping-zones/${id}`)
  },

  async createMethod(zoneId: number, input: ShippingMethodInput): Promise<ShippingMethod> {
    const { data } = await http.post<{ data: ShippingMethod }>(
      `/shipping-zones/${zoneId}/methods`,
      input,
    )
    return data.data
  },

  async updateMethod(id: number, input: ShippingMethodInput): Promise<ShippingMethod> {
    const { data } = await http.put<{ data: ShippingMethod }>(`/shipping-methods/${id}`, input)
    return data.data
  },

  async removeMethod(id: number): Promise<void> {
    await http.delete(`/shipping-methods/${id}`)
  },

  async quote(address: QuoteAddress): Promise<ShippingQuote> {
    const { data } = await http.get<{ data: ShippingQuote }>('/shipping/quote', {
      params: address,
    })
    return data.data
  },
}
