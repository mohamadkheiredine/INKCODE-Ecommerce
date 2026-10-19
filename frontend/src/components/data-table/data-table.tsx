import { type RowData, useTable } from '@tanstack/react-table'
import { ArrowDownIcon, ArrowUpDownIcon, ArrowUpIcon, type LucideIcon } from 'lucide-react'
import { type ReactNode, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { EmptyState } from '@/components/feedback/empty-state'
import { ErrorState } from '@/components/feedback/error-state'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { ListQueryControls } from '@/hooks/use-list-query'
import type { ListPage } from '@/lib/list-query'
import { cn } from '@/lib/utils'
import { type DataTableColumn, listTableFeatures } from './columns'
import { DataTablePagination } from './data-table-pagination'

type DataTableProps<TData extends RowData> = {
  columns: DataTableColumn<TData>[]
  /** The current page from the API; undefined while the first load is pending. */
  page: ListPage<TData> | undefined
  isPending: boolean
  isError: boolean
  /** True while a page is fetched again (new sort, filter or page): rows dim. */
  isFetching?: boolean
  onRetry: () => void
  list: ListQueryControls
  getRowId: (row: TData) => string
  /** For screen readers: "Categories". */
  label: string
  /** Announced while loading: "Loading Categories…". */
  loadingLabel: string
  /** When the store has none at all: what's missing and the next step. */
  empty: { icon: LucideIcon; title: string; description: string; action?: ReactNode }
}

/**
 * The one list table: TanStack Table in manual mode, with the server doing the search,
 * filtering, sorting and paging, and all of that state in the URL (useListQuery). It
 * shows a skeleton while loading, an error with retry, "nothing yet" with the next step,
 * "nothing matches" with a way to clear the filters, and the rows with pagination.
 */
export function DataTable<TData extends RowData>({
  columns,
  page,
  isPending,
  isError,
  isFetching = false,
  onRetry,
  list,
  getRowId,
  label,
  loadingLabel,
  empty,
}: DataTableProps<TData>) {
  const { t } = useTranslation()
  const table = useTable({
    features: listTableFeatures,
    columns,
    data: page?.data ?? [],
    getRowId: (row) => getRowId(row),
  })

  // A page past the end (the last row was deleted, or an old link): show the last page.
  const lastPage = page?.meta.last_page
  const { page: currentPage } = list.query
  const { setPage } = list
  useEffect(() => {
    if (lastPage && currentPage > lastPage) setPage(lastPage)
  }, [lastPage, currentPage, setPage])

  if (isPending) return <TableSkeleton columns={columns.length} label={loadingLabel} />
  if (isError || !page) return <ErrorState onRetry={onRetry} retrying={isFetching} />

  if (page.meta.total === 0) {
    return list.isFiltered ? (
      <EmptyState
        icon={empty.icon}
        title={t('dataTable.noMatchesTitle', { items: label })}
        description={t('dataTable.noMatchesDescription')}
        action={
          <Button type="button" variant="outline" onClick={list.clearFilters}>
            {t('dataTable.clearFilters')}
          </Button>
        }
      />
    ) : (
      <EmptyState
        icon={empty.icon}
        title={empty.title}
        description={empty.description}
        action={empty.action}
      />
    )
  }

  const { sort } = list.query

  return (
    <div className="flex flex-col gap-3">
      <div
        className={cn(
          'overflow-x-auto rounded-2xl border bg-card transition-opacity',
          isFetching && 'opacity-60',
        )}
        data-lenis-prevent
        aria-busy={isFetching}
      >
        <Table aria-label={label}>
          <TableHeader>
            {table.getHeaderGroups().map((group) => (
              <TableRow key={group.id} className="hover:bg-transparent">
                {group.headers.map((header) => {
                  const { sortField, className } = header.column.columnDef.meta ?? {}
                  const direction = sortField && sort.field === sortField ? sort.direction : null
                  return (
                    <TableHead
                      key={header.id}
                      className={cn(
                        'h-11 px-4 text-xs font-medium text-muted-foreground',
                        className,
                      )}
                      aria-sort={
                        direction ? (direction === 'asc' ? 'ascending' : 'descending') : undefined
                      }
                    >
                      {header.isPlaceholder ? null : sortField ? (
                        <SortButton
                          column={String(header.column.columnDef.header ?? sortField)}
                          direction={direction}
                          onClick={() =>
                            list.setSort({
                              field: sortField,
                              direction: direction === 'asc' ? 'desc' : 'asc',
                            })
                          }
                        >
                          <table.FlexRender header={header} />
                        </SortButton>
                      ) : (
                        <table.FlexRender header={header} />
                      )}
                    </TableHead>
                  )
                })}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {table.getRowModel().rows.map((row) => (
              <TableRow key={row.id}>
                {row.getAllCells().map((cell) => (
                  <TableCell
                    key={cell.id}
                    className={cn('px-4 py-3', cell.column.columnDef.meta?.className)}
                  >
                    <table.FlexRender cell={cell} />
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
      <DataTablePagination meta={page.meta} list={list} />
    </div>
  )
}

/** A sortable column's header: one click sorts by it, the next reverses the order. */
function SortButton({
  column,
  direction,
  onClick,
  children,
}: {
  /** The column's header text (sortable columns have plain-text headers). */
  column: string
  direction: 'asc' | 'desc' | null
  onClick: () => void
  children: ReactNode
}) {
  const { t } = useTranslation()
  const Icon =
    direction === 'asc' ? ArrowUpIcon : direction === 'desc' ? ArrowDownIcon : ArrowUpDownIcon

  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      onClick={onClick}
      aria-label={t('dataTable.sortBy', { column })}
      className={cn(
        '-ms-2.5 h-8 gap-1.5 px-2.5 text-xs font-medium',
        direction ? 'text-foreground' : 'text-muted-foreground',
      )}
    >
      {children}
      <Icon className={cn('size-3.5', !direction && 'opacity-50')} aria-hidden="true" />
    </Button>
  )
}

function TableSkeleton({ columns, label }: { columns: number; label: string }) {
  return (
    <div role="status" aria-label={label} className="overflow-hidden rounded-2xl border bg-card">
      <div className="flex h-11 items-center gap-6 border-b px-4">
        {Array.from({ length: columns }, (_, index) => (
          <Skeleton key={index} className="h-3 w-20" />
        ))}
      </div>
      {Array.from({ length: 6 }, (_, row) => (
        <div key={row} className="flex h-14 items-center gap-6 border-b px-4 last:border-b-0">
          {Array.from({ length: columns }, (_, column) => (
            <Skeleton key={column} className={cn('h-4', column === 0 ? 'w-40' : 'w-20')} />
          ))}
        </div>
      ))}
    </div>
  )
}
