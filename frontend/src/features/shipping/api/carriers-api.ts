import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type { Carrier, CarrierInput } from '../types'

export const carrierKeys = {
  all: ['carriers'] as const,
  lists: () => [...carrierKeys.all, 'list'] as const,
  list: (query: ListQuery) => [...carrierKeys.lists(), query] as const,
  shipmentOptions: () => [...carrierKeys.all, 'shipment-options'] as const,
}

export const carriersApi = {
  async list(query: ListQuery): Promise<ListPage<Carrier>> {
    const { data } = await http.get<ListPage<Carrier>>('/carriers', { params: toApiParams(query) })
    return data
  },

  async create(input: CarrierInput): Promise<Carrier> {
    const { data } = await http.post<{ data: Carrier }>('/carriers', input)
    return data.data
  },

  async update(id: number, input: CarrierInput): Promise<Carrier> {
    const { data } = await http.put<{ data: Carrier }>(`/carriers/${id}`, input)
    return data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/carriers/${id}`)
  },
}
