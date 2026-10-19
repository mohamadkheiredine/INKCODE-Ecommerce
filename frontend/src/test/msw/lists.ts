import { HttpResponse } from 'msw/http'
import type { ListPage } from '@/lib/list-query'

type Row = Record<string, unknown>

type ListOptions<T extends Row> = {
  /** Fields the `search` term is looked for in. */
  searchIn: (keyof T)[]
  /** How each filter keeps a row. */
  filters?: Record<string, (row: T, value: string) => boolean>
  defaultSort: string
}

/**
 * Answers a list request exactly as the API's list convention does: search, filters,
 * sort (with `-` for descending) and pages, in the `data` + `meta` + `links` shape.
 */
export function listResponse<T extends Row>(rows: T[], request: Request, options: ListOptions<T>) {
  const url = new URL(request.url)
  const params = url.searchParams
  const search = params.get('search')?.toLowerCase() ?? ''
  const sort = params.get('sort') ?? options.defaultSort
  const page = Number(params.get('page') ?? 1)
  const perPage = Number(params.get('per_page') ?? 15)

  let matched = rows.filter(
    (row) =>
      !search ||
      options.searchIn.some((field) =>
        String(row[field] ?? '')
          .toLowerCase()
          .includes(search),
      ),
  )
  for (const [name, keep] of Object.entries(options.filters ?? {})) {
    const value = params.get(`filter[${name}]`)
    if (value) matched = matched.filter((row) => keep(row, value))
  }

  const field = sort.replace(/^-/, '')
  const direction = sort.startsWith('-') ? -1 : 1
  matched = [...matched].sort((a, b) => {
    const left = a[field] as string | number
    const right = b[field] as string | number
    return (left < right ? -1 : left > right ? 1 : 0) * direction
  })

  const total = matched.length
  const lastPage = Math.max(1, Math.ceil(total / perPage))
  const data = matched.slice((page - 1) * perPage, page * perPage)
  const pageUrl = (n: number) => `${url.origin}${url.pathname}?page=${n}`

  const body: ListPage<T> = {
    data,
    meta: {
      current_page: page,
      last_page: lastPage,
      per_page: perPage,
      total,
      from: data.length ? (page - 1) * perPage + 1 : null,
      to: data.length ? (page - 1) * perPage + data.length : null,
    },
    links: {
      first: pageUrl(1),
      last: pageUrl(lastPage),
      prev: page > 1 ? pageUrl(page - 1) : null,
      next: page < lastPage ? pageUrl(page + 1) : null,
    },
  }
  return HttpResponse.json(body)
}
