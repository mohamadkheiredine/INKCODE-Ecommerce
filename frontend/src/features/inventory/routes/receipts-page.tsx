import { PackagePlusIcon } from 'lucide-react'
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
import { useCan } from '@/features/auth/hooks/use-can'
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import { isId, type ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { useAllWarehouses, useReceipts } from '../hooks/use-inventory'
import type { Receipt } from '../types'

/** What the Receipts list accepts in its URL (mirrors ListReceiptsRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'created_at', direction: 'desc' },
  sorts: ['created_at'],
  filters: { warehouse: isId },
}

/** Receipts: newest first, searchable by number or supplier reference. */
export function ReceiptsPage() {
  const { t } = useTranslation('inventory')
  const canReceive = useCan()('inventory.receive')
  const list = useListQuery(LIST_CONFIG)
  const receipts = useReceipts(list.query)
  const warehouses = useAllWarehouses()
  const { number, money, date } = useFormatters()
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<Receipt>[]>(
    () => [
      {
        id: 'number',
        header: t('fields.receipt'),
        cell: ({ row }) => (
          <Link
            to={`/inventory/receipts/${row.original.id}`}
            dir="ltr"
            className="font-mono text-sm font-medium text-foreground hover:underline"
          >
            {row.original.number}
          </Link>
        ),
      },
      {
        id: 'supplier_reference',
        header: t('fields.supplierReference'),
        cell: ({ row }) => (
          <span dir="auto" className="text-muted-foreground">
            {row.original.supplier_reference}
          </span>
        ),
      },
      {
        id: 'warehouse',
        header: t('fields.warehouse'),
        meta: { className: 'hidden sm:table-cell' },
        cell: ({ row }) => <span dir="auto">{row.original.warehouse.name}</span>,
      },
      {
        id: 'units',
        header: t('fields.units'),
        meta: { className: 'text-end' },
        cell: ({ row }) => <span className="tabular-nums">{number(row.original.units ?? 0)}</span>,
      },
      {
        id: 'total_cost',
        header: t('fields.totalCost'),
        meta: { className: 'text-end' },
        cell: ({ row }) => (
          <span className="tabular-nums">{money(row.original.total_cost ?? 0)}</span>
        ),
      },
      {
        id: 'created_at',
        header: t('fields.date'),
        meta: { sortField: 'created_at', className: 'hidden md:table-cell' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap text-muted-foreground">
            {date(row.original.created_at)}
          </span>
        ),
      },
    ],
    [t, number, money, date],
  )

  const newButton = (
    <Button asChild className="gap-2">
      <Link to="/inventory/receipts/new">
        <PackagePlusIcon className="size-4" aria-hidden="true" />
        {t('receipts.new')}
      </Link>
    </Button>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.receipts')}
        description={t('receipts.description')}
        actions={canReceive && newButton}
      />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('receipts.search')} />
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
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={receipts.data}
          isPending={receipts.isPending}
          isError={receipts.isError}
          isFetching={receipts.isFetching}
          onRetry={() => void receipts.refetch()}
          list={list}
          getRowId={(receipt) => String(receipt.id)}
          label={t('common:pages.receipts')}
          loadingLabel={t('receipts.loading')}
          empty={{
            icon: PackagePlusIcon,
            title: t('receipts.emptyTitle'),
            description: canReceive
              ? t('receipts.emptyDescription')
              : t('receipts.emptyDescriptionReadOnly'),
            action: canReceive ? newButton : undefined,
          }}
        />
      </div>
    </div>
  )
}
