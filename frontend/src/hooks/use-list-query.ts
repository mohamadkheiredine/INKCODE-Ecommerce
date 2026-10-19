import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router'
import {
  type ListQuery,
  type ListQueryConfig,
  type ListSort,
  readListQuery,
  writeListQuery,
} from '@/lib/list-query'

export type ListQueryControls = {
  query: ListQuery
  /** Whether a search or a filter narrows the list (an empty list then means "no matches"). */
  isFiltered: boolean
  setSearch: (search: string) => void
  setFilter: (name: string, value: string | null) => void
  setSort: (sort: ListSort) => void
  setPage: (page: number) => void
  setPerPage: (perPage: number) => void
  clearFilters: () => void
  /** The address of a page of this list, for real links (open in a new tab works). */
  hrefForPage: (page: number) => string
}

type Change = (current: ListQuery) => Partial<ListQuery>

/**
 * A list's search, filters, sort and page, kept in the URL. Every change is a new history
 * entry, so back and forward step through them, except typing in the search box, which
 * replaces the entry. Narrowing the list always goes back to its first page.
 *
 * `config` must be stable (declare it at module level).
 */
export function useListQuery(config: ListQueryConfig): ListQueryControls {
  const [params, setParams] = useSearchParams()
  const query = useMemo(() => readListQuery(params, config), [params, config])

  const update = useCallback(
    (change: Change, options: { replace?: boolean } = {}) =>
      setParams((current) => {
        const currentQuery = readListQuery(current, config)
        return writeListQuery({ ...currentQuery, ...change(currentQuery) }, config, current)
      }, options),
    [setParams, config],
  )

  const setSearch = useCallback(
    (search: string) => update(() => ({ search: search.trim(), page: 1 }), { replace: true }),
    [update],
  )
  const setFilter = useCallback(
    (name: string, value: string | null) =>
      update(({ filters }) => {
        const { [name]: _removed, ...others } = filters
        return { filters: value ? { ...others, [name]: value } : others, page: 1 }
      }),
    [update],
  )
  const setSort = useCallback((sort: ListSort) => update(() => ({ sort, page: 1 })), [update])
  const setPage = useCallback((page: number) => update(() => ({ page })), [update])
  const setPerPage = useCallback(
    (perPage: number) => update(() => ({ perPage, page: 1 })),
    [update],
  )
  const clearFilters = useCallback(
    () => update(() => ({ search: '', filters: {}, page: 1 })),
    [update],
  )
  const hrefForPage = useCallback(
    (page: number) => `?${writeListQuery({ ...query, page }, config, params).toString()}`,
    [query, config, params],
  )

  return {
    query,
    isFiltered: query.search !== '' || Object.keys(query.filters).length > 0,
    setSearch,
    setFilter,
    setSort,
    setPage,
    setPerPage,
    clearFilters,
    hrefForPage,
  }
}
