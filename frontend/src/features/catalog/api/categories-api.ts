import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type { Category, CategoryInput } from '../types'

export const categoryKeys = {
  all: ['categories'] as const,
  lists: () => [...categoryKeys.all, 'list'] as const,
  list: (query: ListQuery) => [...categoryKeys.lists(), query] as const,
  /** Every top-level Category, for the parent picker. */
  parents: () => [...categoryKeys.all, 'parents'] as const,
}

/** The API's page-size limit; enough for every top-level Category of one store. */
const MAX_PER_PAGE = 100

export const categoriesApi = {
  async list(query: ListQuery): Promise<ListPage<Category>> {
    const { data } = await http.get<ListPage<Category>>('/categories', {
      params: toApiParams(query),
    })
    return data
  },

  async topLevel(): Promise<Category[]> {
    const { data } = await http.get<ListPage<Category>>('/categories', {
      params: { 'filter[level]': 'top', sort: 'name', per_page: MAX_PER_PAGE },
    })
    return data.data
  },

  async create(input: CategoryInput): Promise<Category> {
    const { data } = await http.post<{ data: Category }>('/categories', input)
    return data.data
  },

  async update(id: number, input: CategoryInput): Promise<Category> {
    const { data } = await http.put<{ data: Category }>(`/categories/${id}`, input)
    return data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/categories/${id}`)
  },
}
