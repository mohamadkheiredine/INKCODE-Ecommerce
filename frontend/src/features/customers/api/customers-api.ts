import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type {
  AddressInput,
  Customer,
  CustomerAddress,
  CustomerInput,
  CustomerNote,
  CustomerProfile,
  Tag,
} from '../types'

export const customerKeys = {
  all: ['customers'] as const,
  lists: () => [...customerKeys.all, 'list'] as const,
  list: (query: ListQuery) => [...customerKeys.lists(), query] as const,
  detail: (id: number) => [...customerKeys.all, 'detail', id] as const,
  notes: (id: number) => [...customerKeys.detail(id), 'notes'] as const,
  tags: () => [...customerKeys.all, 'tags'] as const,
}

/** Tags are few, so the filter and the picker load them all at once (the API's page limit). */
const TAG_PAGE_SIZE = 100

/** How many notes the profile shows before "Show older notes". */
export const NOTES_PAGE_SIZE = 15

export const customersApi = {
  async list(query: ListQuery): Promise<ListPage<Customer>> {
    const { data } = await http.get<ListPage<Customer>>('/customers', {
      params: toApiParams(query),
    })
    return data
  },

  async get(id: number): Promise<CustomerProfile> {
    const { data } = await http.get<{ data: CustomerProfile }>(`/customers/${id}`)
    return data.data
  },

  async create(input: CustomerInput): Promise<CustomerProfile> {
    const { data } = await http.post<{ data: CustomerProfile }>('/customers', input)
    return data.data
  },

  async update(id: number, input: CustomerInput): Promise<CustomerProfile> {
    const { data } = await http.put<{ data: CustomerProfile }>(`/customers/${id}`, input)
    return data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/customers/${id}`)
  },

  async tags(): Promise<Tag[]> {
    const { data } = await http.get<ListPage<Tag>>('/customer-tags', {
      params: { per_page: TAG_PAGE_SIZE },
    })
    return data.data
  },

  async addAddress(customerId: number, input: AddressInput): Promise<CustomerAddress> {
    const { data } = await http.post<{ data: CustomerAddress }>(
      `/customers/${customerId}/addresses`,
      input,
    )
    return data.data
  },

  async updateAddress(
    customerId: number,
    addressId: number,
    input: AddressInput,
  ): Promise<CustomerAddress> {
    const { data } = await http.put<{ data: CustomerAddress }>(
      `/customers/${customerId}/addresses/${addressId}`,
      input,
    )
    return data.data
  },

  async removeAddress(customerId: number, addressId: number): Promise<void> {
    await http.delete(`/customers/${customerId}/addresses/${addressId}`)
  },

  async notes(customerId: number, page: number): Promise<ListPage<CustomerNote>> {
    const { data } = await http.get<ListPage<CustomerNote>>(`/customers/${customerId}/notes`, {
      params: { page, per_page: NOTES_PAGE_SIZE },
    })
    return data
  },

  async addNote(customerId: number, body: string): Promise<CustomerNote> {
    const { data } = await http.post<{ data: CustomerNote }>(`/customers/${customerId}/notes`, {
      body,
    })
    return data.data
  },
}
