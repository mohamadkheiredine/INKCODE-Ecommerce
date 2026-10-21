import { RotateCcwIcon } from 'lucide-react'
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
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import type { ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { ReturnStatusBadge } from '../components/return-status-badge'
import { useReturns } from '../hooks/use-returns'
import { type OrderReturn, RETURN_REASONS, RETURN_STATUSES } from '../types'

/** What the Returns list accepts in its URL (mirrors ListReturnsRequest). */
const RETURNS_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'requested_at', direction: 'desc' },
  sorts: ['requested_at', 'number'],
  filters: { status: RETURN_STATUSES, reason: RETURN_REASONS },
}

/** Every Return, newest first, with its Order, Customer, reason and status. */
export function ReturnsPage() {
  const { t } = useTranslation('returns')
  const list = useListQuery(RETURNS_CONFIG)
  const returns = useReturns(list.query)
  const { number, date } = useFormatters()
  const container = useRef<HTMLDivElement>(null)
  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<OrderReturn>[]>(
    () => [
      {
        id: 'number',
        header: t('fields.number'),
        meta: { sortField: 'number' },
        cell: ({ row }) => (
          <Link
            to={`/returns/${row.original.id}`}
            dir="ltr"
            className="font-mono text-sm font-medium whitespace-nowrap text-foreground hover:text-primary hover:underline"
          >
            {row.original.number}
          </Link>
        ),
      },
      {
        id: 'order',
        header: t('fields.order'),
        meta: { className: 'hidden sm:table-cell' },
        cell: ({ row }) =>
          row.original.order && (
            <Link
              to={`/orders/${row.original.order.id}`}
              dir="ltr"
              className="font-mono text-sm whitespace-nowrap text-muted-foreground hover:text-primary hover:underline"
            >
              {row.original.order.number}
            </Link>
          ),
      },
      {
        id: 'customer',
        header: t('fields.customer'),
        meta: { className: 'hidden md:table-cell' },
        cell: ({ row }) => <span dir="auto">{row.original.order?.customer?.name ?? '—'}</span>,
      },
      {
        id: 'reason',
        header: t('fields.reason'),
        meta: { className: 'hidden lg:table-cell' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap">{t(`reasons.${row.original.reason}`)}</span>
        ),
      },
      {
        id: 'units',
        header: t('fields.units'),
        meta: { className: 'text-end hidden lg:table-cell' },
        cell: ({ row }) => <span className="tabular-nums">{number(row.original.units ?? 0)}</span>,
      },
      {
        id: 'status',
        header: t('fields.status'),
        cell: ({ row }) => <ReturnStatusBadge status={row.original.status} />,
      },
      {
        id: 'requested_at',
        header: t('fields.requestedAt'),
        meta: { sortField: 'requested_at', className: 'hidden sm:table-cell' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap text-muted-foreground">
            {date(row.original.requested_at)}
          </span>
        ),
      },
    ],
    [t, number, date],
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader title={t('common:pages.returns')} description={t('description')} />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('search')} />
          <DataTableFilter
            list={list}
            name="status"
            label={t('fields.status')}
            allLabel={t('allStatuses')}
            options={RETURN_STATUSES.map((status) => ({
              value: status,
              label: t(`statuses.${status}`),
            }))}
          />
          <DataTableFilter
            list={list}
            name="reason"
            label={t('fields.reason')}
            allLabel={t('allReasons')}
            options={RETURN_REASONS.map((reason) => ({
              value: reason,
              label: t(`reasons.${reason}`),
            }))}
          />
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={returns.data}
          isPending={returns.isPending}
          isError={returns.isError}
          isFetching={returns.isFetching}
          onRetry={() => void returns.refetch()}
          list={list}
          getRowId={(item) => String(item.id)}
          label={t('common:pages.returns')}
          loadingLabel={t('loading')}
          empty={{
            icon: RotateCcwIcon,
            title: t('emptyTitle'),
            description: t('emptyDescription'),
            action: (
              <Link
                to="/orders?filter[status]=delivered"
                className="text-sm font-medium text-primary hover:underline"
              >
                {t('goToDelivered')}
              </Link>
            ),
          }}
        />
      </div>
    </div>
  )
}
