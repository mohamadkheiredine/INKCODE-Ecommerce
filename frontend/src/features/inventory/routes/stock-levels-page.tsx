import { BoxesIcon, HistoryIcon, PackagePlusIcon, XIcon } from 'lucide-react'
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
import { Card, CardContent } from '@/components/ui/card'
import { useCan } from '@/features/auth/hooks/use-can'
import { useCategoryOptions } from '@/features/catalog/hooks/use-categories'
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import { isId, type ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { useAllWarehouses, useStockLevels } from '../hooks/use-inventory'
import { optionsLabel, type StockLevel } from '../types'

/** What the Stock levels list accepts in its URL (mirrors ListStockLevelsRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'product_name', direction: 'asc' },
  sorts: ['sku', 'product_name', 'on_hand', 'available', 'stock_value'],
  filters: { warehouse: isId, product: isId, category: isId, low_stock: ['1'] },
}

/** Stock levels: every Variant in every Warehouse, with totals for the current filters. */
export function StockLevelsPage() {
  const { t } = useTranslation('inventory')
  const canReceive = useCan()('inventory.receive')
  const list = useListQuery(LIST_CONFIG)
  const levels = useStockLevels(list.query)
  const warehouses = useAllWarehouses()
  const categories = useCategoryOptions()
  const { number, money } = useFormatters()
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<StockLevel>[]>(
    () => [
      {
        id: 'product_name',
        header: t('fields.product'),
        meta: { sortField: 'product_name' },
        cell: ({ row }) => (
          <div className="flex min-w-0 flex-col">
            <Link
              to={`/catalog/products/${row.original.product.id}`}
              dir="auto"
              className="font-medium text-foreground hover:underline"
            >
              {row.original.product.name}
            </Link>
            <span className="text-xs text-muted-foreground">
              {optionsLabel(row.original.variant.options)}
            </span>
          </div>
        ),
      },
      {
        id: 'sku',
        header: t('fields.sku'),
        meta: { sortField: 'sku', className: 'hidden md:table-cell' },
        cell: ({ row }) => (
          <span dir="ltr" className="font-mono text-xs">
            {row.original.variant.sku}
          </span>
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
        id: 'on_hand',
        header: t('fields.onHand'),
        meta: { sortField: 'on_hand', className: 'text-end' },
        cell: ({ row }) => <span className="tabular-nums">{number(row.original.on_hand)}</span>,
      },
      {
        id: 'reserved',
        header: t('fields.reserved'),
        meta: { className: 'hidden text-end sm:table-cell' },
        cell: ({ row }) => (
          <span className="text-muted-foreground tabular-nums">
            {number(row.original.reserved)}
          </span>
        ),
      },
      {
        id: 'available',
        header: t('fields.available'),
        meta: { sortField: 'available', className: 'text-end' },
        cell: ({ row }) => (
          <span className="inline-flex items-center justify-end gap-2">
            {row.original.is_low_stock && (
              <Badge
                variant="destructive"
                title={t('stock.threshold', { count: row.original.low_stock_threshold })}
              >
                {t('status.low')}
              </Badge>
            )}
            <span className="font-medium tabular-nums">{number(row.original.available)}</span>
          </span>
        ),
      },
      {
        id: 'average_cost',
        header: t('fields.averageCost'),
        meta: { className: 'hidden text-end lg:table-cell' },
        cell: ({ row }) => (
          <span className="text-muted-foreground tabular-nums">
            {money(row.original.average_cost)}
          </span>
        ),
      },
      {
        id: 'stock_value',
        header: t('fields.stockValue'),
        meta: { sortField: 'stock_value', className: 'text-end' },
        cell: ({ row }) => <span className="tabular-nums">{money(row.original.stock_value)}</span>,
      },
      {
        id: 'history',
        header: () => <span className="sr-only">{t('stock.history')}</span>,
        meta: { className: 'w-12 text-end' },
        cell: ({ row }) => (
          <Button asChild variant="ghost" size="icon-sm">
            <Link
              to={`/inventory/movements?filter[variant]=${row.original.variant.id}&filter[warehouse]=${row.original.warehouse.id}`}
              aria-label={`${t('stock.history')}: ${row.original.variant.sku} (${row.original.warehouse.code})`}
            >
              <HistoryIcon aria-hidden="true" />
            </Link>
          </Button>
        ),
      },
    ],
    [t, number, money],
  )

  const receiveButton = (
    <Button asChild className="gap-2">
      <Link to="/inventory/receipts/new">
        <PackagePlusIcon className="size-4" aria-hidden="true" />
        {t('stock.receive')}
      </Link>
    </Button>
  )

  const totals = levels.data?.meta.totals

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.stockLevels')}
        description={t('stock.description')}
        actions={canReceive && receiveButton}
      />
      <div className="js-reveal grid grid-cols-2 gap-3 sm:max-w-xl">
        <Card className="rounded-2xl py-4">
          <CardContent className="flex flex-col gap-1 px-4">
            <span className="text-xs text-muted-foreground">{t('stock.totalOnHand')}</span>
            <span className="text-xl font-semibold tabular-nums" data-testid="total-on-hand">
              {totals ? number(totals.on_hand) : '—'}
            </span>
          </CardContent>
        </Card>
        <Card className="rounded-2xl py-4">
          <CardContent className="flex flex-col gap-1 px-4">
            <span className="text-xs text-muted-foreground">{t('stock.totalValue')}</span>
            <span className="text-xl font-semibold tabular-nums" data-testid="total-value">
              {totals ? money(totals.stock_value) : '—'}
            </span>
          </CardContent>
        </Card>
      </div>
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('stock.search')} />
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
            name="category"
            label={t('fields.category')}
            allLabel={t('stock.allCategories')}
            options={(categories.data ?? []).map((category) => ({
              value: String(category.id),
              label: category.parent ? `${category.parent.name} › ${category.name}` : category.name,
            }))}
          />
          <DataTableFilter
            list={list}
            name="low_stock"
            label={t('status.low')}
            allLabel={t('status.all')}
            options={[{ value: '1', label: t('stock.lowOnly') }]}
          />
          {list.query.filters.product && (
            <Button
              type="button"
              variant="secondary"
              size="sm"
              className="gap-1.5 self-start sm:self-auto"
              onClick={() => list.setFilter('product', null)}
            >
              {t('stock.productFilter')}
              <XIcon className="size-4" aria-hidden="true" />
            </Button>
          )}
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={levels.data}
          isPending={levels.isPending}
          isError={levels.isError}
          isFetching={levels.isFetching}
          onRetry={() => void levels.refetch()}
          list={list}
          getRowId={(level) => `${level.variant.id}:${level.warehouse.id}`}
          label={t('common:pages.stockLevels')}
          loadingLabel={t('stock.loading')}
          empty={{
            icon: BoxesIcon,
            title: t('stock.emptyTitle'),
            description: t('stock.emptyDescription'),
            action: canReceive ? receiveButton : undefined,
          }}
        />
      </div>
    </div>
  )
}
