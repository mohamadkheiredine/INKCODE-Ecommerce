import { ScaleIcon, WalletIcon } from 'lucide-react'
import { type ReactNode, useMemo, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, NavLink } from 'react-router'
import type { DataTableColumn } from '@/components/data-table/columns'
import { DataTable } from '@/components/data-table/data-table'
import {
  DataTableFilter,
  DataTableSearch,
  DataTableToolbar,
} from '@/components/data-table/data-table-toolbar'
import { PageHeader } from '@/components/layout/page-header'
import { OrderStatusBadge } from '@/features/orders/components/order-status-badge'
import type { OrderStatus } from '@/features/orders/types'
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import type { ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { cn } from '@/lib/utils'
import { PaymentStatusBadge } from '../components/payment-status-badge'
import { usePayments, useReconciliation } from '../hooks/use-payments'
import { toCents } from '../money'
import {
  PAYMENT_KINDS,
  PAYMENT_METHODS,
  PAYMENT_OUTCOMES,
  type Payment,
  type ReconciliationRow,
} from '../types'

/** What the payments list accepts in its URL (mirrors ListPaymentsRequest). */
const LEDGER_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'created_at', direction: 'desc' },
  sorts: ['created_at', 'amount'],
  filters: { kind: PAYMENT_KINDS, method: PAYMENT_METHODS, status: PAYMENT_OUTCOMES },
}

/** What reconciliation accepts in its URL (mirrors ListReconciliationRequest). */
const RECONCILIATION_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'placed_at', direction: 'desc' },
  sorts: ['placed_at', 'total', 'difference'],
  filters: { balance: ['due', 'over'] },
}

const orderLink = (id: number, number: string) => (
  <Link
    to={`/orders/${id}`}
    dir="ltr"
    className="font-mono text-sm font-medium whitespace-nowrap text-foreground hover:text-primary hover:underline"
  >
    {number}
  </Link>
)

/** The page header and the two views: every ledger row, and reconciliation. */
function PaymentsShell({ children }: { children: ReactNode }) {
  const { t } = useTranslation('payments')
  const container = useRef<HTMLDivElement>(null)
  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const tab = ({ isActive }: { isActive: boolean }) =>
    cn(
      'flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
      isActive
        ? 'bg-background text-foreground shadow-sm'
        : 'text-muted-foreground hover:text-foreground',
    )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader title={t('common:pages.payments')} description={t('description')} />
      <nav aria-label={t('views')} className="js-reveal flex w-fit gap-1 rounded-lg bg-muted p-1">
        <NavLink to="/payments" end className={tab}>
          <WalletIcon className="size-4" aria-hidden="true" />
          {t('ledger.all')}
        </NavLink>
        <NavLink to="/payments/reconciliation" className={tab}>
          <ScaleIcon className="size-4" aria-hidden="true" />
          {t('reconciliation.title')}
        </NavLink>
      </nav>
      <div className="js-reveal flex flex-col gap-4">{children}</div>
    </div>
  )
}

/** Every payment and Refund across Orders, newest first, with failed Card charges too. */
export function PaymentsPage() {
  const { t } = useTranslation('payments')
  const list = useListQuery(LEDGER_CONFIG)
  const payments = usePayments(list.query)
  const { money, dateTime } = useFormatters()

  const columns = useMemo<DataTableColumn<Payment>[]>(
    () => [
      {
        id: 'order',
        header: t('fields.order'),
        cell: ({ row }) =>
          row.original.order && orderLink(row.original.order.id, row.original.order.number),
      },
      {
        id: 'kind',
        header: t('fields.kind'),
        cell: ({ row }) => (
          <span className="whitespace-nowrap">
            {t(`kinds.${row.original.kind}`)}
            {row.original.status === 'failed' && (
              <span className="ms-2 text-xs text-destructive">{t('outcomes.failed')}</span>
            )}
          </span>
        ),
      },
      {
        id: 'method',
        header: t('fields.method'),
        cell: ({ row }) => (
          <span className="whitespace-nowrap">{t(`methods.${row.original.method}`)}</span>
        ),
      },
      {
        id: 'reference',
        header: t('fields.reference'),
        meta: { className: 'hidden lg:table-cell' },
        cell: ({ row }) => (
          <span dir="ltr" className="font-mono text-xs text-muted-foreground">
            {row.original.gateway_reference ?? '—'}
          </span>
        ),
      },
      {
        id: 'user',
        header: t('fields.user'),
        meta: { className: 'hidden md:table-cell' },
        cell: ({ row }) => <span dir="auto">{row.original.user?.name ?? t('system')}</span>,
      },
      {
        id: 'amount',
        header: t('fields.amount'),
        meta: { sortField: 'amount', className: 'text-end' },
        cell: ({ row }) => (
          <span
            className={cn(
              'font-medium whitespace-nowrap tabular-nums',
              row.original.status === 'failed' && 'text-muted-foreground line-through',
              row.original.kind === 'refund' && 'text-destructive',
            )}
          >
            {row.original.kind === 'refund' ? '−' : ''}
            {money(row.original.amount)}
          </span>
        ),
      },
      {
        id: 'created_at',
        header: t('fields.recordedAt'),
        meta: { sortField: 'created_at', className: 'hidden sm:table-cell' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap text-muted-foreground">
            {dateTime(row.original.created_at)}
          </span>
        ),
      },
    ],
    [t, money, dateTime],
  )

  return (
    <PaymentsShell>
      <DataTableToolbar list={list}>
        <DataTableSearch list={list} label={t('search')} />
        <DataTableFilter
          list={list}
          name="kind"
          label={t('fields.kind')}
          allLabel={t('allKinds')}
          options={PAYMENT_KINDS.map((kind) => ({ value: kind, label: t(`kinds.${kind}`) }))}
        />
        <DataTableFilter
          list={list}
          name="method"
          label={t('fields.method')}
          allLabel={t('allMethods')}
          options={PAYMENT_METHODS.map((method) => ({
            value: method,
            label: t(`methods.${method}`),
          }))}
        />
        <DataTableFilter
          list={list}
          name="status"
          label={t('fields.outcome')}
          allLabel={t('allOutcomes')}
          options={PAYMENT_OUTCOMES.map((outcome) => ({
            value: outcome,
            label: t(`outcomes.${outcome}`),
          }))}
        />
      </DataTableToolbar>
      <DataTable
        columns={columns}
        page={payments.data}
        isPending={payments.isPending}
        isError={payments.isError}
        isFetching={payments.isFetching}
        onRetry={() => void payments.refetch()}
        list={list}
        getRowId={(payment) => String(payment.id)}
        label={t('ledger.all')}
        loadingLabel={t('loading')}
        empty={{
          icon: WalletIcon,
          title: t('emptyTitle'),
          description: t('emptyDescription'),
          action: (
            <Link to="/orders" className="text-sm font-medium text-primary hover:underline">
              {t('goToOrders')}
            </Link>
          ),
        }}
      />
    </PaymentsShell>
  )
}

/** The Orders whose ledger total differs from what they owe: money still due, or held over. */
export function ReconciliationPage() {
  const { t } = useTranslation('payments')
  const list = useListQuery(RECONCILIATION_CONFIG)
  const rows = useReconciliation(list.query)
  const { money, date } = useFormatters()

  const columns = useMemo<DataTableColumn<ReconciliationRow>[]>(
    () => [
      {
        id: 'number',
        header: t('fields.order'),
        cell: ({ row }) => orderLink(row.original.id, row.original.number),
      },
      {
        id: 'customer',
        header: t('fields.customer'),
        meta: { className: 'hidden md:table-cell' },
        cell: ({ row }) => <span dir="auto">{row.original.customer.name}</span>,
      },
      {
        id: 'status',
        header: t('fields.orderStatus'),
        meta: { className: 'hidden lg:table-cell' },
        cell: ({ row }) => <OrderStatusBadge status={row.original.status as OrderStatus} />,
      },
      {
        id: 'payment_status',
        header: t('fields.paymentStatus'),
        cell: ({ row }) => <PaymentStatusBadge status={row.original.payment_status} />,
      },
      {
        id: 'total',
        header: t('reconciliation.owed'),
        meta: { sortField: 'total', className: 'text-end' },
        cell: ({ row }) => <span className="tabular-nums">{money(row.original.amount_owed)}</span>,
      },
      {
        id: 'ledger',
        header: t('reconciliation.ledger'),
        meta: { className: 'text-end' },
        cell: ({ row }) => <span className="tabular-nums">{money(row.original.ledger_total)}</span>,
      },
      {
        id: 'difference',
        header: t('reconciliation.difference'),
        meta: { sortField: 'difference', className: 'text-end' },
        cell: ({ row }) => (
          <span
            className={cn(
              'font-medium whitespace-nowrap tabular-nums',
              toCents(row.original.difference) < 0 ? 'text-destructive' : 'text-foreground',
            )}
          >
            {money(row.original.difference)}
          </span>
        ),
      },
      {
        id: 'placed_at',
        header: t('fields.placedAt'),
        meta: { sortField: 'placed_at', className: 'hidden sm:table-cell' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap text-muted-foreground">
            {date(row.original.placed_at)}
          </span>
        ),
      },
    ],
    [t, money, date],
  )

  return (
    <PaymentsShell>
      <DataTableToolbar list={list}>
        <DataTableSearch list={list} label={t('reconciliation.search')} />
        <DataTableFilter
          list={list}
          name="balance"
          label={t('reconciliation.balance')}
          allLabel={t('reconciliation.allBalances')}
          options={(['due', 'over'] as const).map((balance) => ({
            value: balance,
            label: t(`reconciliation.balances.${balance}`),
          }))}
        />
      </DataTableToolbar>
      <DataTable
        columns={columns}
        page={rows.data}
        isPending={rows.isPending}
        isError={rows.isError}
        isFetching={rows.isFetching}
        onRetry={() => void rows.refetch()}
        list={list}
        getRowId={(row) => String(row.id)}
        label={t('reconciliation.title')}
        loadingLabel={t('loading')}
        empty={{
          icon: ScaleIcon,
          title: t('reconciliation.emptyTitle'),
          description: t('reconciliation.emptyDescription'),
          action: (
            <Link to="/payments" className="text-sm font-medium text-primary hover:underline">
              {t('ledger.all')}
            </Link>
          ),
        }}
      />
    </PaymentsShell>
  )
}
