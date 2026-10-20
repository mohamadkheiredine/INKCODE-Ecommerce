/**
 * The one list convention the API shares (Laravel `ListRequest`): `search`,
 * `filter[name]=value`, `sort=name` or `sort=-name` (descending), `page` and `per_page`.
 * Lists answer with `data`, `meta` (pagination totals) and `links`.
 *
 * The URL of a list screen uses exactly the same parameters, so a list's address can be
 * shared and back/forward walks through its states.
 */

export type ListMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
  /** Position of the first and last row on this page, or null when it is empty. */
  from: number | null
  to: number | null
}

export type ListLinks = {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export type ListPage<T> = { data: T[]; meta: ListMeta; links: ListLinks }

export type SortDirection = 'asc' | 'desc'
export type ListSort = { field: string; direction: SortDirection }

export type ListQuery = {
  search: string
  /** Only the filters that are set. */
  filters: Record<string, string>
  sort: ListSort
  page: number
  perPage: number
}

/** What a list screen accepts; anything else in the URL is ignored. */
export type ListQueryConfig = {
  defaultSort: ListSort
  /** The sortable fields; an unknown sort in the URL falls back to the default. */
  sorts: readonly string[]
  /**
   * Each filter with its allowed values, or a check for filters whose values come from
   * data (a Tag's id); a value in the URL that isn't allowed is ignored.
   */
  filters?: Record<string, readonly string[] | ((value: string) => boolean)>
  defaultPerPage?: number
}

export const PAGE_SIZES = [15, 25, 50, 100] as const
export const DEFAULT_PER_PAGE = PAGE_SIZES[0]

/** A filter check for record ids ("12"). */
export const isId = (value: string) => /^[1-9]\d{0,18}$/.test(value)

/** A filter check for amounts with at most two decimals ("25", "19.90"). */
export const isAmount = (value: string) => /^\d{1,8}(?:\.\d{1,2})?$/.test(value)

export function parseSort(value: string | null | undefined): ListSort | null {
  if (!value) return null
  return value.startsWith('-')
    ? { field: value.slice(1), direction: 'desc' }
    : { field: value, direction: 'asc' }
}

export function formatSort({ field, direction }: ListSort): string {
  return direction === 'desc' ? `-${field}` : field
}

/** A filter check for record ids ("12"). */
export const isRecordId = (value: string): boolean => /^[1-9]\d*$/.test(value)

function positiveInteger(value: string | null, fallback: number): number {
  const number = Number(value)
  return Number.isInteger(number) && number > 0 ? number : fallback
}

/** The list state a URL describes, with everything unknown or invalid left at its default. */
export function readListQuery(params: URLSearchParams, config: ListQueryConfig): ListQuery {
  const sort = parseSort(params.get('sort'))
  const filters: Record<string, string> = {}
  for (const [name, allowed] of Object.entries(config.filters ?? {})) {
    const value = params.get(`filter[${name}]`)
    const isAllowed = typeof allowed === 'function' ? allowed : (v: string) => allowed.includes(v)
    if (value && isAllowed(value)) filters[name] = value
  }
  const perPage = positiveInteger(params.get('per_page'), config.defaultPerPage ?? DEFAULT_PER_PAGE)

  return {
    search: params.get('search')?.trim() ?? '',
    filters,
    sort: sort && config.sorts.includes(sort.field) ? sort : config.defaultSort,
    page: positiveInteger(params.get('page'), 1),
    perPage: (PAGE_SIZES as readonly number[]).includes(perPage)
      ? perPage
      : (config.defaultPerPage ?? DEFAULT_PER_PAGE),
  }
}

/**
 * The URL parameters for a list state. Defaults are left out, so a fresh list has a
 * clean address; other parameters already in the URL are kept.
 */
export function writeListQuery(
  query: ListQuery,
  config: ListQueryConfig,
  base: URLSearchParams = new URLSearchParams(),
): URLSearchParams {
  const params = new URLSearchParams(base)
  for (const key of [...params.keys()]) {
    if (key === 'search' || key === 'sort' || key === 'page' || key === 'per_page')
      params.delete(key)
    if (key.startsWith('filter[')) params.delete(key)
  }

  if (query.search) params.set('search', query.search)
  for (const [name, value] of Object.entries(query.filters)) params.set(`filter[${name}]`, value)
  const sort = formatSort(query.sort)
  if (sort !== formatSort(config.defaultSort)) params.set('sort', sort)
  if (query.page > 1) params.set('page', String(query.page))
  if (query.perPage !== (config.defaultPerPage ?? DEFAULT_PER_PAGE)) {
    params.set('per_page', String(query.perPage))
  }
  return params
}

/** The same state as API query parameters (every value spelled out). */
export function toApiParams(query: ListQuery): Record<string, string | number> {
  const params: Record<string, string | number> = {
    sort: formatSort(query.sort),
    page: query.page,
    per_page: query.perPage,
  }
  if (query.search) params.search = query.search
  for (const [name, value] of Object.entries(query.filters)) params[`filter[${name}]`] = value
  return params
}
