import { http } from '@/lib/api/client'
import type { SearchResults } from '../types'

/** The API refuses shorter searches (SearchRequest). */
export const MIN_SEARCH_LENGTH = 2

export const searchKeys = {
  all: ['search'] as const,
  results: (q: string) => [...searchKeys.all, q] as const,
}

export const searchApi = {
  /** The top Orders, Products and Customers matching `q`, grouped by what the Role may see. */
  async search(q: string, signal?: AbortSignal): Promise<SearchResults> {
    const { data } = await http.get<{ data: SearchResults }>('/search', { params: { q }, signal })
    return data.data
  },
}
