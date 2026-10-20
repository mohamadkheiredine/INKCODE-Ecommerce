import { ArrowRightLeftIcon, XIcon } from 'lucide-react'
import { useMemo, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import type { DataTableColumn } from '@/components/data-table/columns'
import { DataTable } from '@/components/data-table/data-table'
import {
  DataTableFilter,
  DataTableSearch,
  DataTableToolbar,
} from '@/components/data-table/data-table-toolbar'
import { PageHeader } from '@/components/layout/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import { isId, type ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { cn } from '@/lib/utils'
import { useAllWarehouses, useStockMovements } from '../hooks/use-inventory'
import { MOVEMENT_TYPES, optionsLabel, type StockMovement } from '../types'

const isDay = (value: string) => /^\d{4}-\d{2}-\d{2}$/.test(value)

/** What the history accepts in its URL (mirrors ListStockMovementsRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'created_at', direction: 'desc' },
  sorts: ['created_at'],
  filters: { variant: isId, warehouse: isId, type: MOVEMENT_TYPES, from: isDay, to: isDay },
}

/** Where a movement's source record lives in the app. */
function sourceHref(source: StockMovement['source']): string | null {
  if (source.type === 'receipt') return `/inventory/receipts/${source.id}`
  if (source.type === 'order') return `/orders/${source.id}`
  return null
}

/** Stock movement history: every change, newest first, linked to what caused it. */
export function StockMovementsPage() {
  const { t } = useTranslation('inventory')
  const list = useListQuery(LIST_CONFIG)
  const movements = useStockMovements(list.query)
  const warehouses = useAllWarehouses()
  const { number, dateTime } = useFormatters()
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<StockMovement>[]>(() => {
    const delta = (value: number) => (
      <span
        className={cn(
          'tabular-nums',
          value > 0 && 'text-success',
          value < 0 && 'text-destructive',
          value === 0 && 'text-muted-foreground',
        )}
        dir="ltr"
      >
        {value > 0 ? `+${number(value)}` : number(value)}
      </span>
    )

    return [
      {
        id: 'created_at',
        header: t('fields.date'),
        meta: { sortField: 'created_at' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap text-muted-foreground">
            {dateTime(row.original.created_at)}
          </span>
        ),
      },
      {
        id: 'variant',
        header: t('fields.variant'),
        cell: ({ row }) => (
          <div className="flex min-w-0 flex-col">
            <span dir="auto" className="font-medium text-foreground">
              {row.original.variant.product.name}
            </span>
            <span className="text-xs text-muted-foreground">
              <span dir="ltr" className="font-mono">
                {row.original.variant.sku}
              </span>
              {row.original.variant.options.length > 0 &&
                ` · ${optionsLabel(row.original.variant.options)}`}
            </span>
          </div>
        ),
      },
      {
        id: 'warehouse',
        header: t('fields.warehouse'),
        cell: ({ row }) => (
          <span dir="ltr" className="font-mono text-xs" title={row.original.warehouse.name}>
            {row.original.warehouse.code}
          </span>
        ),
      },
      {
        id: 'type',
        header: t('fields.type'),
        cell: ({ row }) => (
          <Badge variant="secondary">{t(`movementTypes.${row.original.type}`)}</Badge>
        ),
      },
      {
        id: 'on_hand_delta',
        header: t('fields.onHand'),
        meta: { className: 'text-end' },
        cell: ({ row }) => (
          <span className="inline-flex flex-col items-end">
            {delta(row.original.on_hand_delta)}
            <span className="text-xs text-muted-foreground tabular-nums">
              {t('fields.balanceAfter')} {number(row.original.on_hand_after)}
            </span>
          </span>
        ),
      },
      {
        id: 'reserved_delta',
        header: t('fields.reserved'),
        meta: { className: 'hidden text-end sm:table-cell' },
        cell: ({ row }) => delta(row.original.reserved_delta),
      },
      {
        id: 'source',
        header: t('fields.source'),
        cell: ({ row }) => {
          const href = sourceHref(row.original.source)
          const label = row.original.source.label ?? `#${row.original.source.id}`
          return href ? (
            <Link to={href} dir="ltr" className="font-mono text-xs text-primary hover:underline">
              {label}
            </Link>
          ) : (
            <span dir="ltr" className="font-mono text-xs">
              {label}
            </span>
          )
        },
      },
      {
        id: 'user',
        header: t('fields.user'),
        meta: { className: 'hidden lg:table-cell' },
        cell: ({ row }) => (
          <span dir="auto" className="text-muted-foreground">
            {row.original.user?.name ?? t('movements.system')}
          </span>
        ),
      },
    ]
  }, [t, number, dateTime])

  const dayFilter = (name: 'from' | 'to') => (
    <label className="flex items-center gap-2 text-sm text-muted-foreground">
      {t(`movements.${name}`)}
      <Input
        type="date"
        value={list.query.filters[name] ?? ''}
        onChange={(event) => list.setFilter(name, event.target.value || null)}
        className="h-9 w-40 bg-card"
      />
    </label>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.stockMovements')}
        description={t('movements.description')}
      />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('movements.search')} />
          <DataTableFilter
            list={list}
            name="warehouse"
            label={t('fields.warehouse')}
            allLabel={t('stock.allWarehouses')}
            options={(warehouses.data?.data ?? []).map((warehouse) => ({
              value: String(warehouse.id),
              label: `${warehouse.code} · ${warehouse.name}`,
            }))}
          />
          <DataTableFilter
            list={list}
            name="type"
            label={t('fields.type')}
            allLabel={t('movements.allTypes')}
            options={MOVEMENT_TYPES.map((type) => ({
              value: type,
              label: t(`movementTypes.${type}`),
            }))}
          />
          {dayFilter('from')}
          {dayFilter('to')}
          {list.query.filters.variant && (
            <Button
              type="button"
              variant="secondary"
              size="sm"
              className="gap-1.5 self-start sm:self-auto"
              onClick={() => list.setFilter('variant', null)}
            >
              {t('movements.variantFilter')}
              <XIcon className="size-4" aria-hidden="true" />
            </Button>
          )}
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={movements.data}
          isPending={movements.isPending}
          isError={movements.isError}
          isFetching={movements.isFetching}
          onRetry={() => void movements.refetch()}
          list={list}
          getRowId={(movement) => String(movement.id)}
          label={t('common:pages.stockMovements')}
          loadingLabel={t('movements.loading')}
          empty={{
            icon: ArrowRightLeftIcon,
            title: t('movements.emptyTitle'),
            description: t('movements.emptyDescription'),
          }}
        />
      </div>
    </div>
  )
}
