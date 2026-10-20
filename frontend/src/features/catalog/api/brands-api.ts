import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type { Brand, BrandInput } from '../types'

export const brandKeys = {
  all: ['brands'] as const,
  lists: () => [...brandKeys.all, 'list'] as const,
  list: (query: ListQuery) => [...brandKeys.lists(), query] as const,
  /** Every Brand, for the Product form and filters. */
  options: () => [...brandKeys.all, 'options'] as const,
}

/** The API's page-size limit; enough for every Brand of one store. */
const MAX_PER_PAGE = 100

export const brandsApi = {
  async list(query: ListQuery): Promise<ListPage<Brand>> {
    const { data } = await http.get<ListPage<Brand>>('/brands', { params: toApiParams(query) })
    return data
  },

  async all(): Promise<Brand[]> {
    const { data } = await http.get<ListPage<Brand>>('/brands', {
      params: { sort: 'name', per_page: MAX_PER_PAGE },
    })
    return data.data
  },

  async create(input: BrandInput): Promise<Brand> {
    const { data } = await http.post<{ data: Brand }>('/brands', input)
    return data.data
  },

  async update(id: number, input: BrandInput): Promise<Brand> {
    const { data } = await http.put<{ data: Brand }>(`/brands/${id}`, input)
    return data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/brands/${id}`)
  },

  /** Replaces the Brand's logo (multipart, on its own endpoint). */
  async uploadLogo(id: number, logo: File): Promise<Brand> {
    const body = new FormData()
    body.append('logo', logo)
    const { data } = await http.post<{ data: Brand }>(`/brands/${id}/logo`, body)
    return data.data
  },

  async removeLogo(id: number): Promise<Brand> {
    const { data } = await http.delete<{ data: Brand }>(`/brands/${id}/logo`)
    return data.data
  },
}
