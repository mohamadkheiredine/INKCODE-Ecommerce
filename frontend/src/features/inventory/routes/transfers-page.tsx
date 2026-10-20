import { ArrowLeftRightIcon } from 'lucide-react'
import { useMemo, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { toast } from 'sonner'
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
import { useCan } from '@/features/auth/hooks/use-can'
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { isId, type ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { useAllWarehouses, useChangeTransfer, useTransfers } from '../hooks/use-inventory'
import { TRANSFER_STATUSES, type Transfer, type TransferStep } from '../types'

/** What the Transfers list accepts in its URL (mirrors ListTransfersRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'created_at', direction: 'desc' },
  sorts: ['created_at'],
  filters: { status: TRANSFER_STATUSES, warehouse: isId },
}

/** The steps each status allows: a Draft is sent or cancelled, an in-transit one received. */
const STEPS: Record<Transfer['status'], TransferStep[]> = {
  draft: ['send', 'cancel'],
  in_transit: ['receive'],
  received: [],
  cancelled: [],
}

const DONE_MESSAGE: Record<TransferStep, string> = {
  send: 'inventory:transfers.sent',
  receive: 'inventory:transfers.received',
  cancel: 'inventory:transfers.cancelled',
}

/** Transfers between Warehouses: what is in transit, and the send / receive / cancel steps. */
export function TransfersPage() {
  const { t } = useTranslation('inventory')
  const canTransfer = useCan()('inventory.transfer')
  const list = useListQuery(LIST_CONFIG)
  const transfers = useTransfers(list.query)
  const warehouses = useAllWarehouses()
  const change = useChangeTransfer()
  const { number, date } = useFormatters()
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<Transfer>[]>(() => {
    const run = (transfer: Transfer, step: TransferStep) =>
      change.mutate(
        { id: transfer.id, step },
        {
          onSuccess: () => toast.success(i18n.t(DONE_MESSAGE[step], { number: transfer.number })),
          onError: (error) => toast.error(apiErrorMessage(error)),
        },
      )

    return [
      {
        id: 'number',
        header: t('transfers.number'),
        cell: ({ row }) => (
          <span dir="ltr" className="font-mono text-sm font-medium">
            {row.original.number}
          </span>
        ),
      },
      {
        id: 'route',
        header: t('transfers.route'),
        cell: ({ row }) => (
          <span dir="auto" className="whitespace-nowrap">
            {row.original.source_warehouse.code} → {row.original.destination_warehouse.code}
          </span>
        ),
      },
      {
        id: 'status',
        header: t('transfers.status'),
        cell: ({ row }) => (
          <Badge variant={row.original.status === 'in_transit' ? 'default' : 'secondary'}>
            {t(`transferStatus.${row.original.status}`)}
          </Badge>
        ),
      },
      {
        id: 'units',
        header: t('transfers.units'),
        meta: { className: 'text-end' },
        cell: ({ row }) => <span className="tabular-nums">{number(row.original.units ?? 0)}</span>,
      },
      {
        id: 'created_at',
        header: t('transfers.date'),
        meta: { sortField: 'created_at', className: 'hidden md:table-cell' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap text-muted-foreground">
            {date(row.original.created_at)}
          </span>
        ),
      },
      {
        id: 'actions',
        header: () => <span className="sr-only">{t('transfers.actions')}</span>,
        meta: { className: 'text-end' },
        cell: ({ row }) =>
          canTransfer && (
            <div className="flex justify-end gap-2">
              {STEPS[row.original.status].map((step) => (
                <Button
                  key={step}
                  size="sm"
                  variant={step === 'cancel' ? 'outline' : 'default'}
                  disabled={change.isPending}
                  onClick={() => run(row.original, step)}
                >
                  {t(`transfers.${step}`)}
                </Button>
              ))}
            </div>
          ),
      },
    ]
  }, [t, number, date, canTransfer, change])

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader title={t('common:pages.transfers')} description={t('transfers.description')} />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('transfers.search')} />
          <DataTableFilter
            list={list}
            name="status"
            label={t('transfers.status')}
            allLabel={t('transfers.allStatuses')}
            options={TRANSFER_STATUSES.map((status) => ({
              value: status,
              label: t(`transferStatus.${status}`),
            }))}
          />
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
          page={transfers.data}
          isPending={transfers.isPending}
          isError={transfers.isError}
          isFetching={transfers.isFetching}
          onRetry={() => void transfers.refetch()}
          list={list}
          getRowId={(transfer) => String(transfer.id)}
          label={t('common:pages.transfers')}
          loadingLabel={t('transfers.loading')}
          empty={{
            icon: ArrowLeftRightIcon,
            title: t('transfers.emptyTitle'),
            description: canTransfer
              ? t('transfers.emptyDescription')
              : t('transfers.emptyDescriptionReadOnly'),
            action: canTransfer ? (
              <Button asChild variant="outline">
                <Link to="/inventory/stock">{t('common:pages.stockLevels')}</Link>
              </Button>
            ) : undefined,
          }}
        />
      </div>
    </div>
  )
}
