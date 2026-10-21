import { ArrowLeftIcon, PrinterIcon, ShoppingCartIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { EmptyState } from '@/components/feedback/empty-state'
import { ErrorState } from '@/components/feedback/error-state'
import { PageSkeleton } from '@/components/feedback/page-skeleton'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { optionsLabel } from '@/features/inventory/types'
import { useStoreProfile } from '@/features/settings/hooks/use-settings'
import type { StoreProfile } from '@/features/settings/types'
import { isApiError } from '@/lib/api/api-error'
import { useFormatters } from '@/lib/format'
import { AddressBlock } from '../components/address-block'
import { OrderTotals } from '../components/order-totals'
import { useOrder } from '../hooks/use-orders'
import type { Order } from '../types'

/**
 * The Invoice: a print-ready statement of the Order (store profile, Customer, lines,
 * discount, VAT, shipping, total) in the active language. The browser prints it alone.
 */
export function InvoicePage() {
  const { t } = useTranslation('orders')
  const orderId = Number(useParams().orderId)
  const order = useOrder(orderId)
  const store = useStoreProfile()

  if (order.isPending || store.isPending) return <PageSkeleton />
  if (order.isError && isApiError(order.error) && order.error.status === 404) {
    return (
      <EmptyState
        className="mt-6"
        icon={ShoppingCartIcon}
        title={t('common:pages.invoice')}
        description={t('notFound')}
        action={
          <Button asChild variant="outline">
            <Link to="/orders">{t('common:pages.orders')}</Link>
          </Button>
        }
      />
    )
  }
  if (order.isError || store.isError) {
    return (
      <ErrorState
        onRetry={() => void Promise.all([order.refetch(), store.refetch()])}
        retrying={order.isFetching || store.isFetching}
      />
    )
  }
  return <Invoice order={order.data} store={store.data} />
}

function Invoice({ order, store }: { order: Order; store: StoreProfile }) {
  const { t } = useTranslation('orders')
  const { number, money, date } = useFormatters()
  const lines = (order.lines ?? []).filter((line) => line.quantity > 0)

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-2 print:hidden">
        <Button asChild variant="outline" className="gap-2">
          <Link to={`/orders/${order.id}`}>
            <ArrowLeftIcon className="size-4 rtl:rotate-180" aria-hidden="true" />
            {order.number}
          </Link>
        </Button>
        <Button className="gap-2" onClick={() => window.print()}>
          <PrinterIcon className="size-4" aria-hidden="true" />
          {t('invoice.print')}
        </Button>
      </div>

      <article
        className="print-area mx-auto flex w-full max-w-3xl flex-col gap-8 rounded-2xl border bg-card p-6 text-card-foreground sm:p-10 print:max-w-none print:rounded-none print:border-0 print:p-0"
        aria-label={t('invoice.title', { number: order.number })}
      >
        <header className="flex flex-col justify-between gap-6 sm:flex-row">
          <div className="flex flex-col gap-1 text-sm">
            {store.logo_url && (
              <img src={store.logo_url} alt="" className="mb-2 h-10 w-auto self-start" />
            )}
            <span dir="auto" className="text-lg font-semibold">
              {store.name}
            </span>
            {store.address && (
              <span dir="auto" className="whitespace-pre-line text-muted-foreground">
                {store.address}
              </span>
            )}
            {store.contact_email && (
              <span dir="ltr" className="text-start text-muted-foreground">
                {store.contact_email}
              </span>
            )}
            {store.contact_phone && (
              <span dir="ltr" className="text-start text-muted-foreground">
                {store.contact_phone}
              </span>
            )}
          </div>
          <div className="flex flex-col gap-1 text-sm sm:text-end">
            <h1 className="text-2xl font-semibold tracking-tight">{t('invoice.heading')}</h1>
            <span dir="ltr" className="font-mono sm:text-end">
              {order.number}
            </span>
            <span className="text-muted-foreground">
              {t('invoice.date', { date: date(order.placed_at) })}
            </span>
            <span className="text-muted-foreground">
              {t('invoice.status', { status: t(`statuses.${order.status}`) })}
            </span>
          </div>
        </header>

        <section className="grid gap-6 sm:grid-cols-2">
          <div className="flex flex-col gap-1.5">
            <h2 className="text-xs font-medium text-muted-foreground">{t('invoice.billTo')}</h2>
            <span dir="auto" className="text-sm font-medium">
              {order.customer.name}
            </span>
            <AddressBlock address={order.billing_address} />
          </div>
          <div className="flex flex-col gap-1.5">
            <h2 className="text-xs font-medium text-muted-foreground">{t('invoice.shipTo')}</h2>
            <AddressBlock address={order.shipping_address} />
          </div>
        </section>

        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('fields.variant')}</TableHead>
              <TableHead className="text-end">{t('fields.quantity')}</TableHead>
              <TableHead className="text-end">{t('fields.unitPrice')}</TableHead>
              <TableHead className="text-end">{t('fields.vat')}</TableHead>
              <TableHead className="text-end">{t('fields.lineTotal')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {lines.map((line) => (
              <TableRow key={line.id}>
                <TableCell className="whitespace-normal">
                  <span dir="auto" className="font-medium">
                    {line.product_name}
                  </span>
                  <span className="block text-xs text-muted-foreground">
                    <span dir="ltr" className="font-mono">
                      {line.sku}
                    </span>
                    {line.options.length > 0 && ` · ${optionsLabel(line.options)}`}
                  </span>
                </TableCell>
                <TableCell className="text-end tabular-nums">{number(line.quantity)}</TableCell>
                <TableCell className="text-end tabular-nums">{money(line.unit_price)}</TableCell>
                <TableCell className="text-end tabular-nums">{money(line.vat_amount)}</TableCell>
                <TableCell className="text-end font-medium tabular-nums">
                  {money(line.line_total)}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>

        <div className="ms-auto w-full max-w-xs">
          <OrderTotals
            totals={order}
            shippingLabel={order.shipping_method.name}
            couponCode={order.coupon?.code}
          />
        </div>

        <footer className="border-t pt-4 text-xs text-muted-foreground">
          {t('invoice.vatNote', { rate: number(Number(order.vat_rate)) })}
        </footer>
      </article>
    </div>
  )
}
