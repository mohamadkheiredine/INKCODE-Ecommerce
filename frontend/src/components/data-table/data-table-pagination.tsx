import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import type { ListQueryControls } from '@/hooks/use-list-query'
import { type ListMeta, PAGE_SIZES } from '@/lib/list-query'
import { useFormatters } from '@/lib/format'

/** The pages to offer: the first, the last, and the current one with its neighbours. */
function pageNumbers(current: number, last: number): (number | 'gap')[] {
  const wanted = new Set([1, last, current - 1, current, current + 1])
  const pages = [...wanted].filter((page) => page >= 1 && page <= last).sort((a, b) => a - b)
  return pages.flatMap((page, index) =>
    index > 0 && page - pages[index - 1]! > 1 ? (['gap', page] as const) : [page],
  )
}

/**
 * "Showing 16–30 of 42", the page size, and links to the pages: real links, so a page
 * can be opened in a new tab and back/forward work.
 */
export function DataTablePagination({ meta, list }: { meta: ListMeta; list: ListQueryControls }) {
  const { t } = useTranslation()
  const { number } = useFormatters()
  const { current_page: current, last_page: last } = meta

  return (
    <div className="flex flex-col-reverse items-center justify-between gap-3 text-sm text-muted-foreground sm:flex-row">
      <p aria-live="polite">
        {t('dataTable.showing', {
          from: number(meta.from ?? 0),
          to: number(meta.to ?? 0),
          total: number(meta.total),
        })}
      </p>
      <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
        <label className="flex items-center gap-2">
          <span>{t('dataTable.rowsPerPage')}</span>
          <NativeSelect
            size="sm"
            value={String(list.query.perPage)}
            onChange={(event) => list.setPerPage(Number(event.target.value))}
          >
            {PAGE_SIZES.map((size) => (
              <NativeSelectOption key={size} value={size}>
                {number(size)}
              </NativeSelectOption>
            ))}
          </NativeSelect>
        </label>
        {last > 1 && (
          <nav aria-label={t('dataTable.pagination')}>
            <ul className="flex items-center gap-1">
              <li>
                <PageLink
                  page={current - 1}
                  disabled={current <= 1}
                  list={list}
                  label={t('dataTable.previousPage')}
                >
                  <ChevronLeftIcon className="size-4 rtl:rotate-180" aria-hidden="true" />
                </PageLink>
              </li>
              {pageNumbers(current, last).map((page, index) =>
                page === 'gap' ? (
                  <li key={`gap-${index}`} aria-hidden="true" className="px-1">
                    …
                  </li>
                ) : (
                  <li key={page}>
                    <PageLink
                      page={page}
                      list={list}
                      current={page === current}
                      label={t('dataTable.page', { page: number(page) })}
                    >
                      {number(page)}
                    </PageLink>
                  </li>
                ),
              )}
              <li>
                <PageLink
                  page={current + 1}
                  disabled={current >= last}
                  list={list}
                  label={t('dataTable.nextPage')}
                >
                  <ChevronRightIcon className="size-4 rtl:rotate-180" aria-hidden="true" />
                </PageLink>
              </li>
            </ul>
          </nav>
        )}
      </div>
    </div>
  )
}

function PageLink({
  page,
  list,
  label,
  current = false,
  disabled = false,
  children,
}: {
  page: number
  list: ListQueryControls
  label: string
  current?: boolean
  disabled?: boolean
  children: React.ReactNode
}) {
  if (disabled) {
    return (
      <Button type="button" variant="ghost" size="icon-sm" disabled aria-label={label}>
        {children}
      </Button>
    )
  }

  return (
    <Button asChild variant={current ? 'outline' : 'ghost'} size="icon-sm">
      <Link
        to={list.hrefForPage(page)}
        aria-label={label}
        aria-current={current ? 'page' : undefined}
      >
        {children}
      </Link>
    </Button>
  )
}
