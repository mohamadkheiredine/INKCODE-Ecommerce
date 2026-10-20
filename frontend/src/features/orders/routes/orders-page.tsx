import { PlusIcon, ShoppingCartIcon } from 'lucide-react'
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
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { useCan } from '@/features/auth/hooks/use-can'
import { useAllWarehouses } from '@/features/inventory/hooks/use-inventory'
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import { isId, type ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { OrderStatusBadge } from '../components/order-status-badge'
import { useOrders } from '../hooks/use-orders'
import { ORDER_STATUSES, type Order } from '../types'

const isDay = (value: string) => /^\d{4}-\d{2}-\d{2}$/.test(value)

/** What the Orders list accepts in its URL (mirrors ListOrdersRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'placed_at', direction: 'desc' },
  sorts: ['placed_at', 'number', 'total'],
  filters: { status: ORDER_STATUSES, warehouse: isId, customer: isId, from: isDay, to: isDay },
}

/** Orders: newest first, searchable by number or Customer, filterable by status, Warehouse and date. */
export function OrdersPage() {
  const { t } = useTranslation('orders')
  const canCreate = useCan()('orders.create')
  const list = useListQuery(LIST_CONFIG)
  const orders = useOrders(list.query)
  const warehouses = useAllWarehouses()
  const { number, money, dateTime } = useFormatters()
  const container = useRef<HTMLDivElement>(null)
  const { filters } = list.query

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<Order>[]>(
    () => [
      {
        id: 'number',
        header: t('fields.number'),
        meta: { sortField: 'number' },
        cell: ({ row }) => (
          <Link
            to={`/orders/${row.original.id}`}
            dir="ltr"
            className="font-mono text-sm font-medium whitespace-nowrap text-foreground hover:text-primary hover:underline"
          >
            {row.original.number}
          </Link>
        ),
      },
      {
        id: 'customer',
        header: t('fields.customer'),
        cell: ({ row }) => (
          <div className="flex min-w-36 flex-col">
            <span dir="auto" className="font-medium">
              {row.original.customer.name}
            </span>
            {row.original.customer.email && (
              <span dir="ltr" className="text-start text-xs text-muted-foreground">
                {row.original.customer.email}
              </span>
            )}
          </div>
        ),
      },
      {
        id: 'status',
        header: t('fields.status'),
        cell: ({ row }) => <OrderStatusBadge status={row.original.status} />,
      },
      {
        id: 'warehouse',
        header: t('fields.warehouse'),
        meta: { className: 'hidden lg:table-cell' },
        cell: ({ row }) => <span dir="auto">{row.original.warehouse.name}</span>,
      },
      {
        id: 'units',
        header: t('fields.units'),
        meta: { className: 'hidden sm:table-cell text-end' },
        cell: ({ row }) => <span className="tabular-nums">{number(row.original.units ?? 0)}</span>,
      },
      {
        id: 'total',
        header: t('fields.total'),
        meta: { sortField: 'total', className: 'text-end' },
        cell: ({ row }) => (
          <span className="font-medium tabular-nums">{money(row.original.total)}</span>
        ),
      },
      {
        id: 'placed_at',
        header: t('fields.placedAt'),
        meta: { sortField: 'placed_at', className: 'hidden md:table-cell' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap text-muted-foreground">
            {dateTime(row.original.placed_at)}
          </span>
        ),
      },
    ],
    [t, number, money, dateTime],
  )

  const newButton = (
    <Button asChild className="gap-2">
      <Link to="/orders/new">
        <PlusIcon className="size-4" aria-hidden="true" />
        {t('new')}
      </Link>
    </Button>
  )
  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.orders')}
        description={t('description')}
        actions={canCreate && newButton}
      />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('search')} />
          <DataTableFilter
            list={list}
            name="status"
            label={t('fields.status')}
            allLabel={t('allStatuses')}
            options={ORDER_STATUSES.map((status) => ({
              value: status,
              label: t(`statuses.${status}`),
            }))}
          />
          <DataTableFilter
            list={list}
            name="warehouse"
            label={t('fields.warehouse')}
            allLabel={t('allWarehouses')}
            options={(warehouses.data?.data ?? []).map((warehouse) => ({
              value: String(warehouse.id),
              label: `${warehouse.code} · ${warehouse.name}`,
            }))}
          />
          <div className="flex flex-wrap items-center gap-3">
            <label className="flex items-center gap-2 text-sm text-muted-foreground">
              {t('from')}
              <Input
                type="date"
                value={filters.from ?? ''}
                max={filters.to}
                onChange={(event) => list.setFilter('from', event.target.value || null)}
                className="h-9 w-40 bg-card"
              />
            </label>
            <label className="flex items-center gap-2 text-sm text-muted-foreground">
              {t('to')}
              <Input
                type="date"
                value={filters.to ?? ''}
                min={filters.from}
                onChange={(event) => list.setFilter('to', event.target.value || null)}
                className="h-9 w-40 bg-card"
              />
            </label>
          </div>
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={orders.data}
          isPending={orders.isPending}
          isError={orders.isError}
          isFetching={orders.isFetching}
          onRetry={() => void orders.refetch()}
          list={list}
          getRowId={(order) => String(order.id)}
          label={t('common:pages.orders')}
          loadingLabel={t('loading')}
          empty={{
            icon: ShoppingCartIcon,
            title: t('emptyTitle'),
            description: canCreate ? t('emptyDescription') : t('emptyDescriptionReadOnly'),
            action: canCreate ? newButton : undefined,
          }}
        />
      </div>
    </div>
  )
}
