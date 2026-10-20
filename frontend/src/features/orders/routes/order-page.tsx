import {
  ArrowLeftIcon,
  BanIcon,
  CheckCircle2Icon,
  CircleDotIcon,
  ShoppingCartIcon,
  TriangleAlertIcon,
} from 'lucide-react'
import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { toast } from 'sonner'
import { EmptyState } from '@/components/feedback/empty-state'
import { ErrorState } from '@/components/feedback/error-state'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { PageSkeleton } from '@/components/feedback/page-skeleton'
import { PageHeader } from '@/components/layout/page-header'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { useDirection } from '@/components/ui/direction'
import { Label } from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { useCan } from '@/features/auth/hooks/use-can'
import { optionsLabel } from '@/features/inventory/types'
import { isApiError } from '@/lib/api/api-error'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useEntranceAnimation } from '@/lib/motion'
import { AddressBlock } from '../components/address-block'
import { OrderStatusBadge } from '../components/order-status-badge'
import { OrderTotals } from '../components/order-totals'
import { useCancelOrder, useConfirmOrder, useOrder } from '../hooks/use-orders'
import { CANCELLABLE_STATUSES, type Order, type StockShortage } from '../types'

/** One Order: its lines and totals, Customer and addresses, fulfilment, and status history. */
export function OrderPage() {
  const { t } = useTranslation('orders')
  const order = useOrder(Number(useParams().orderId))

  if (order.isPending) return <PageSkeleton />
  if (order.isError) {
    if (isApiError(order.error) && order.error.status === 404) {
      return (
        <EmptyState
          className="mt-6"
          icon={ShoppingCartIcon}
          title={t('common:pages.order')}
          description={t('notFound')}
          action={<BackLink />}
        />
      )
    }
    return <ErrorState onRetry={() => void order.refetch()} retrying={order.isFetching} />
  }
  return <OrderDetail order={order.data} />
}

function BackLink() {
  const { t } = useTranslation('orders')
  return (
    <Button asChild variant="outline" className="gap-2">
      <Link to="/orders">
        <ArrowLeftIcon className="size-4 rtl:rotate-180" aria-hidden="true" />
        {t('common:pages.orders')}
      </Link>
    </Button>
  )
}

/** The refusal of a confirmation: its message, and the lines lacking stock when it names them. */
type Refusal = { message: string; shortages: StockShortage[] }

function OrderDetail({ order }: { order: Order }) {
  const { t } = useTranslation('orders')
  const can = useCan()
  const { number, money, dateTime } = useFormatters()
  const confirm = useConfirmOrder()
  const [refusal, setRefusal] = useState<Refusal | null>(null)
  const [cancelling, setCancelling] = useState(false)
  // One key per attempt: a retried click is applied once, a new attempt gets a new key.
  const [confirmKey, setConfirmKey] = useState(() => crypto.randomUUID())
  const container = useRef<HTMLDivElement>(null)
  const lines = order.lines ?? []

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const canConfirm = order.status === 'pending' && can('orders.update')
  const canCancel = CANCELLABLE_STATUSES.includes(order.status) && can('orders.cancel')

  const confirmOrder = async () => {
    setRefusal(null)
    try {
      const confirmed = await confirm.mutateAsync({ id: order.id, idempotencyKey: confirmKey })
      toast.success(i18n.t('orders:confirmed', { number: confirmed.number }))
    } catch (error) {
      setConfirmKey(crypto.randomUUID())
      const shortages =
        isApiError(error) && Array.isArray(error.details.lines)
          ? (error.details.lines as StockShortage[])
          : []
      setRefusal({ message: apiErrorMessage(error), shortages })
    }
  }

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={order.number}
        description={t('detailDescription', {
          customer: order.customer.name,
          date: dateTime(order.placed_at),
        })}
        actions={
          <>
            <BackLink />
            {canCancel && (
              <Button variant="outline" className="gap-2" onClick={() => setCancelling(true)}>
                <BanIcon className="size-4" aria-hidden="true" />
                {t('cancel.action')}
              </Button>
            )}
            {canConfirm && (
              <Button
                className="gap-2"
                disabled={confirm.isPending}
                onClick={() => void confirmOrder()}
              >
                {confirm.isPending ? (
                  <InlineSpinner className="size-4" />
                ) : (
                  <CheckCircle2Icon className="size-4" aria-hidden="true" />
                )}
                {t('confirm.action')}
              </Button>
            )}
          </>
        }
      />

      {refusal && (
        <Alert variant="destructive" role="alert" className="js-reveal">
          <TriangleAlertIcon aria-hidden="true" />
          <AlertTitle>{t('confirm.refused')}</AlertTitle>
          <AlertDescription>
            {refusal.shortages.length > 0 ? (
              <ul className="flex flex-col gap-0.5">
                {refusal.shortages.map((shortage) => (
                  <li key={shortage.variant_id}>
                    <span dir="ltr" className="font-mono">
                      {shortage.sku}
                    </span>{' '}
                    {t('confirm.shortage', {
                      requested: number(shortage.requested),
                      available: number(shortage.available),
                    })}
                  </li>
                ))}
              </ul>
            ) : (
              refusal.message
            )}
          </AlertDescription>
        </Alert>
      )}

      <div className="grid items-start gap-6 lg:grid-cols-3">
        <div className="flex flex-col gap-6 lg:col-span-2">
          <Card className="js-reveal rounded-2xl">
            <CardHeader className="flex flex-row items-center justify-between gap-3">
              <CardTitle className="text-base">{t('lines')}</CardTitle>
              <OrderStatusBadge status={order.status} />
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
              <div className="overflow-x-auto" data-lenis-prevent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('fields.variant')}</TableHead>
                      <TableHead className="text-end">{t('fields.quantity')}</TableHead>
                      <TableHead className="text-end">{t('fields.unitPrice')}</TableHead>
                      <TableHead className="hidden text-end sm:table-cell">
                        {t('fields.vat')}
                      </TableHead>
                      <TableHead className="text-end">{t('fields.lineTotal')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {lines.map((line) => (
                      <TableRow key={line.id}>
                        <TableCell>
                          <div className="flex min-w-40 flex-col">
                            {line.product_id ? (
                              <Link
                                to={`/catalog/products/${line.product_id}`}
                                dir="auto"
                                className="font-medium hover:underline"
                              >
                                {line.product_name}
                              </Link>
                            ) : (
                              <span dir="auto" className="font-medium">
                                {line.product_name}
                              </span>
                            )}
                            <span className="text-xs text-muted-foreground">
                              <span dir="ltr" className="font-mono">
                                {line.sku}
                              </span>
                              {line.options.length > 0 && ` · ${optionsLabel(line.options)}`}
                            </span>
                          </div>
                        </TableCell>
                        <TableCell className="text-end tabular-nums">
                          {number(line.quantity)}
                        </TableCell>
                        <TableCell className="text-end tabular-nums">
                          {money(line.unit_price)}
                        </TableCell>
                        <TableCell className="hidden text-end tabular-nums sm:table-cell">
                          {line.taxable ? (
                            <span className="flex flex-col">
                              <span>{money(line.vat_amount)}</span>
                              <span className="text-xs text-muted-foreground">
                                {t('vatAt', { rate: number(Number(line.vat_rate)) })}
                              </span>
                            </span>
                          ) : (
                            <span className="text-muted-foreground">{t('untaxed')}</span>
                          )}
                        </TableCell>
                        <TableCell className="text-end font-medium tabular-nums">
                          {money(line.line_total)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
              <div className="ms-auto w-full max-w-sm">
                <OrderTotals totals={order} shippingLabel={order.shipping_method.name} />
              </div>
            </CardContent>
          </Card>

          <Card className="js-reveal rounded-2xl">
            <CardHeader>
              <CardTitle className="text-base">{t('history')}</CardTitle>
            </CardHeader>
            <CardContent>
              <ol className="flex flex-col gap-4">
                {(order.status_history ?? []).map((change) => (
                  <li key={change.id} className="flex gap-3">
                    <CircleDotIcon
                      className="mt-0.5 size-4 shrink-0 text-primary"
                      aria-hidden="true"
                    />
                    <div className="flex min-w-0 flex-col gap-0.5">
                      <span className="text-sm font-medium">
                        {change.from_status === null
                          ? t('historyCreated')
                          : t('historyMoved', { status: t(`statuses.${change.to_status}`) })}
                      </span>
                      <span className="text-xs text-muted-foreground">
                        {t('historyBy', {
                          user: change.user?.name ?? t('system'),
                          when: dateTime(change.created_at),
                        })}
                      </span>
                      {change.note && (
                        <p dir="auto" className="text-sm text-muted-foreground">
                          {change.note}
                        </p>
                      )}
                    </div>
                  </li>
                ))}
              </ol>
            </CardContent>
          </Card>
        </div>

        <div className="flex flex-col gap-6">
          <Card className="js-reveal rounded-2xl">
            <CardHeader>
              <CardTitle className="text-base">{t('fields.customer')}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-1 text-sm">
              <Link
                to={`/customers/${order.customer.id}`}
                dir="auto"
                className="font-medium hover:text-primary hover:underline"
              >
                {order.customer.name}
              </Link>
              {order.customer.email && (
                <span dir="ltr" className="text-start text-muted-foreground">
                  {order.customer.email}
                </span>
              )}
              {order.customer.phone && (
                <span dir="ltr" className="text-start text-muted-foreground">
                  {order.customer.phone}
                </span>
              )}
            </CardContent>
          </Card>
          <Card className="js-reveal rounded-2xl">
            <CardContent className="flex flex-col gap-5">
              <section className="flex flex-col gap-1.5">
                <h2 className="text-xs font-medium text-muted-foreground">
                  {t('fields.shippingAddress')}
                </h2>
                <AddressBlock address={order.shipping_address} />
              </section>
              <section className="flex flex-col gap-1.5">
                <h2 className="text-xs font-medium text-muted-foreground">
                  {t('fields.billingAddress')}
                </h2>
                <AddressBlock address={order.billing_address} />
              </section>
            </CardContent>
          </Card>
          <Card className="js-reveal rounded-2xl">
            <CardContent>
              <dl className="flex flex-col gap-3 text-sm">
                <div className="flex flex-col gap-0.5">
                  <dt className="text-xs text-muted-foreground">{t('fields.warehouse')}</dt>
                  <dd dir="auto" className="font-medium">
                    {order.warehouse.name}{' '}
                    <span dir="ltr" className="font-mono text-xs text-muted-foreground">
                      {order.warehouse.code}
                    </span>
                  </dd>
                </div>
                <div className="flex flex-col gap-0.5">
                  <dt className="text-xs text-muted-foreground">{t('fields.shippingMethod')}</dt>
                  <dd dir="auto" className="font-medium">
                    {order.shipping_method.name}
                  </dd>
                </div>
                <div className="flex flex-col gap-0.5">
                  <dt className="text-xs text-muted-foreground">{t('fields.createdBy')}</dt>
                  <dd dir="auto" className="font-medium">
                    {order.user?.name ?? t('system')}
                  </dd>
                </div>
                {order.notes && (
                  <div className="flex flex-col gap-0.5">
                    <dt className="text-xs text-muted-foreground">{t('fields.notes')}</dt>
                    <dd dir="auto">{order.notes}</dd>
                  </div>
                )}
              </dl>
            </CardContent>
          </Card>
        </div>
      </div>

      <CancelOrderDialog order={cancelling ? order : null} onClose={() => setCancelling(false)} />
    </div>
  )
}

/** "Cancel ORD-…?" with an optional reason; a Confirmed Order's stock is released. */
function CancelOrderDialog({ order, onClose }: { order: Order | null; onClose: () => void }) {
  const { t } = useTranslation('orders')
  const dir = useDirection()
  const cancel = useCancelOrder()
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID())

  const close = () => {
    setNote('')
    setError(null)
    onClose()
  }

  const submit = async () => {
    if (!order) return
    setError(null)
    try {
      await cancel.mutateAsync({ id: order.id, note: note.trim(), idempotencyKey })
      toast.success(i18n.t('orders:cancel.done', { number: order.number }))
      setIdempotencyKey(crypto.randomUUID())
      close()
    } catch (refusal) {
      setIdempotencyKey(crypto.randomUUID())
      setError(apiErrorMessage(refusal))
    }
  }

  return (
    <AlertDialog
      open={order !== null}
      onOpenChange={(open) => !open && !cancel.isPending && close()}
    >
      <AlertDialogContent dir={dir} data-lenis-prevent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('cancel.title', { number: order?.number })}</AlertDialogTitle>
          <AlertDialogDescription>
            {order?.status === 'pending'
              ? t('cancel.descriptionPending')
              : t('cancel.descriptionReserved')}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <div className="flex flex-col gap-2">
          <Label htmlFor="cancel-note">{t('cancel.reason')}</Label>
          <Textarea
            id="cancel-note"
            dir="auto"
            value={note}
            maxLength={500}
            rows={3}
            onChange={(event) => setNote(event.target.value)}
          />
          {error && (
            <p role="alert" className="text-sm text-destructive">
              {error}
            </p>
          )}
        </div>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={cancel.isPending}>{t('cancel.keep')}</AlertDialogCancel>
          <AlertDialogAction
            variant="destructive"
            disabled={cancel.isPending}
            onClick={(event) => {
              event.preventDefault()
              void submit()
            }}
            className="gap-2"
          >
            {cancel.isPending && <InlineSpinner className="size-4" />}
            {t('cancel.submit')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
