import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type { Attribute, AttributeInput } from '../types'

export const attributeKeys = {
  all: ['attributes'] as const,
  lists: () => [...attributeKeys.all, 'list'] as const,
  list: (query: ListQuery) => [...attributeKeys.lists(), query] as const,
  /** The whole library, for the Variant builder. */
  library: () => [...attributeKeys.all, 'library'] as const,
}

/** The API's page-size limit; enough for every Attribute of one store. */
const MAX_PER_PAGE = 100

export const attributesApi = {
  async list(query: ListQuery): Promise<ListPage<Attribute>> {
    const { data } = await http.get<ListPage<Attribute>>('/attributes', {
      params: toApiParams(query),
    })
    return data
  },

  async all(): Promise<Attribute[]> {
    const { data } = await http.get<ListPage<Attribute>>('/attributes', {
      params: { sort: 'name', per_page: MAX_PER_PAGE },
    })
    return data.data
  },

  async create(input: AttributeInput): Promise<Attribute> {
    const { data } = await http.post<{ data: Attribute }>('/attributes', input)
    return data.data
  },

  async update(id: number, input: AttributeInput): Promise<Attribute> {
    const { data } = await http.put<{ data: Attribute }>(`/attributes/${id}`, input)
    return data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/attributes/${id}`)
  },
}
