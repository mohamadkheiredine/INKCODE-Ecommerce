import {
  ArrowLeftIcon,
  CheckIcon,
  PackageCheckIcon,
  RotateCcwIcon,
  Undo2Icon,
  XIcon,
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
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useDirection } from '@/components/ui/direction'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import { Textarea } from '@/components/ui/textarea'
import { useCan } from '@/features/auth/hooks/use-can'
import { optionsLabel } from '@/features/inventory/types'
import { fromCents, toCents } from '@/features/payments/money'
import { isApiError } from '@/lib/api/api-error'
import type { Amount } from '@/lib/format'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useEntranceAnimation } from '@/lib/motion'
import { ReturnStatusBadge } from '../components/return-status-badge'
import {
  useDecideReturn,
  useReceiveReturn,
  useRefundReturn,
  useRestockReturn,
  useReturn,
} from '../hooks/use-returns'
import { type OrderReturn, RESTOCK_DECISIONS, type RestockDecision } from '../types'

/** One Return: its items with what was paid for them, its steps, and the Refund. */
export function ReturnPage() {
  const { t } = useTranslation('returns')
  const item = useReturn(Number(useParams().returnId))

  if (item.isPending) return <PageSkeleton />
  if (item.isError) {
    if (isApiError(item.error) && item.error.status === 404) {
      return (
        <EmptyState
          className="mt-6"
          icon={RotateCcwIcon}
          title={t('common:pages.return')}
          description={t('notFound')}
          action={<BackLink />}
        />
      )
    }
    return <ErrorState onRetry={() => void item.refetch()} retrying={item.isFetching} />
  }
  return <ReturnDetail item={item.data} />
}

function BackLink() {
  const { t } = useTranslation('returns')
  return (
    <Button asChild variant="outline" className="gap-2">
      <Link to="/returns">
        <ArrowLeftIcon className="size-4 rtl:rotate-180" aria-hidden="true" />
        {t('common:pages.returns')}
      </Link>
    </Button>
  )
}

function ReturnDetail({ item }: { item: OrderReturn }) {
  const { t } = useTranslation('returns')
  const can = useCan()
  const { money, dateTime } = useFormatters()
  const receive = useReceiveReturn()
  const [deciding, setDeciding] = useState<'approve' | 'reject' | null>(null)
  const container = useRef<HTMLDivElement>(null)
  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])
  const lines = item.lines ?? []

  async function markReceived() {
    try {
      await receive.mutateAsync(item.id)
      toast.success(i18n.t('returns:receive.done', { number: item.number }))
    } catch (error) {
      toast.error(apiErrorMessage(error))
    }
  }

  const actions = (
    <div className="flex flex-wrap items-center gap-2">
      <ReturnStatusBadge status={item.status} className="me-1" />
      {item.status === 'requested' && can('returns.approve') && (
        <>
          <Button variant="outline" className="gap-2" onClick={() => setDeciding('reject')}>
            <XIcon className="size-4" aria-hidden="true" />
            {t('decide.reject')}
          </Button>
          <Button className="gap-2" onClick={() => setDeciding('approve')}>
            <CheckIcon className="size-4" aria-hidden="true" />
            {t('decide.approve')}
          </Button>
        </>
      )}
      {item.status === 'approved' && can('returns.receive') && (
        <Button className="gap-2" disabled={receive.isPending} onClick={() => void markReceived()}>
          {receive.isPending ? (
            <InlineSpinner className="size-4" />
          ) : (
            <PackageCheckIcon className="size-4" aria-hidden="true" />
          )}
          {t('receive.action')}
        </Button>
      )}
    </div>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader title={item.number} description={t('detailDescription')} actions={actions} />
      <div className="grid gap-6 lg:grid-cols-3">
        <div className="flex flex-col gap-6 lg:col-span-2">
          <ItemsCard item={item} />
          {item.status === 'received' &&
            can('refunds.issue') &&
            lines.every((line) => line.restock_decision !== null) && <RefundCard item={item} />}
        </div>
        <Card className="js-reveal h-fit rounded-2xl">
          <CardHeader>
            <CardTitle className="text-base">{t('details')}</CardTitle>
          </CardHeader>
          <CardContent>
            <dl className="flex flex-col gap-3 text-sm">
              {item.order && (
                <Detail label={t('fields.order')}>
                  <Link
                    to={`/orders/${item.order.id}`}
                    dir="ltr"
                    className="font-mono hover:text-primary hover:underline"
                  >
                    {item.order.number}
                  </Link>
                </Detail>
              )}
              <Detail label={t('fields.customer')}>
                <span dir="auto">{item.order?.customer?.name ?? '—'}</span>
              </Detail>
              <Detail label={t('fields.reason')}>{t(`reasons.${item.reason}`)}</Detail>
              {item.note && (
                <Detail label={t('fields.note')}>
                  <span dir="auto">{item.note}</span>
                </Detail>
              )}
              <Detail label={t('fields.requestedAt')}>
                {dateTime(item.requested_at)}
                {item.user && <span className="text-muted-foreground"> · {item.user.name}</span>}
              </Detail>
              {item.decided_at && (
                <Detail
                  label={t(item.status === 'rejected' ? 'fields.rejectedAt' : 'fields.approvedAt')}
                >
                  {dateTime(item.decided_at)}
                  {item.decision_note && (
                    <span dir="auto" className="block text-muted-foreground">
                      {item.decision_note}
                    </span>
                  )}
                </Detail>
              )}
              {item.received_at && (
                <Detail label={t('fields.receivedAt')}>{dateTime(item.received_at)}</Detail>
              )}
              {item.completed_at && (
                <Detail label={t('fields.completedAt')}>{dateTime(item.completed_at)}</Detail>
              )}
              {item.status === 'completed' && (
                <Detail label={t('fields.refund')}>
                  <span className="font-medium tabular-nums">
                    {money(item.refund_amount ?? '0.00')}
                  </span>
                  {item.refund_shipping && (
                    <span className="text-muted-foreground"> · {t('refund.withShipping')}</span>
                  )}
                  {item.refund && (
                    <span className="block text-muted-foreground">
                      {t(`payments:methods.${item.refund.method}`)} ·{' '}
                      {dateTime(item.refund.created_at)}
                    </span>
                  )}
                </Detail>
              )}
            </dl>
          </CardContent>
        </Card>
      </div>
      {deciding && (
        <DecideDialog item={item} decision={deciding} onClose={() => setDeciding(null)} />
      )}
    </div>
  )
}

function Detail({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd>{children}</dd>
    </div>
  )
}

/** The returned items, and their Restock decisions once the Return is Received. */
function ItemsCard({ item }: { item: OrderReturn }) {
  const { t } = useTranslation('returns')
  const can = useCan()
  const { money, number } = useFormatters()
  const restock = useRestockReturn()
  const lines = item.lines ?? []
  const undecided = lines.filter((line) => line.restock_decision === null)
  const mayDecide = item.status === 'received' && can('returns.receive') && undecided.length > 0
  const [decisions, setDecisions] = useState<Record<number, RestockDecision>>({})
  const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID())

  async function save() {
    const chosen = undecided.flatMap((line) =>
      decisions[line.id] ? [{ line_id: line.id, decision: decisions[line.id]! }] : [],
    )
    if (chosen.length === 0) {
      toast.error(t('restock.chooseFirst'))
      return
    }
    try {
      await restock.mutateAsync({ id: item.id, lines: chosen, idempotencyKey })
      setIdempotencyKey(crypto.randomUUID())
      setDecisions({})
      toast.success(i18n.t('returns:restock.done'))
    } catch (error) {
      setIdempotencyKey(crypto.randomUUID())
      toast.error(apiErrorMessage(error))
    }
  }

  return (
    <Card className="js-reveal rounded-2xl">
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
        <div className="flex flex-col gap-1">
          <CardTitle className="text-base">{t('items')}</CardTitle>
          {item.status !== 'received' && item.status !== 'completed' && (
            <p className="text-sm text-muted-foreground">{t('restock.notYet')}</p>
          )}
        </div>
        {mayDecide && (
          <Button
            size="sm"
            className="gap-2"
            disabled={restock.isPending}
            onClick={() => void save()}
          >
            {restock.isPending && <InlineSpinner className="size-4" />}
            {t('restock.save')}
          </Button>
        )}
      </CardHeader>
      <CardContent>
        <ul className="flex flex-col divide-y">
          {lines.map((line) => (
            <li key={line.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
              <div className="flex min-w-0 flex-col">
                <span dir="auto" className="text-sm font-medium">
                  {line.product_name}
                </span>
                <span className="text-xs text-muted-foreground">
                  <span dir="ltr" className="font-mono">
                    {line.sku}
                  </span>
                  {line.options.length > 0 && <> · {optionsLabel(line.options)}</>}
                </span>
                <span className="text-xs text-muted-foreground">
                  {t('returnedOf', {
                    count: line.quantity,
                    returned: number(line.quantity),
                    ordered: number(line.ordered_quantity),
                  })}{' '}
                  · {t('paid')} <span className="tabular-nums">{money(line.paid_amount)}</span>
                </span>
              </div>
              {line.restock_decision ? (
                <span className="text-sm font-medium">
                  {t(`decisions.${line.restock_decision}`)}
                </span>
              ) : mayDecide ? (
                <div className="flex flex-col gap-1">
                  <Label htmlFor={`decision-${line.id}`} className="sr-only">
                    {t('restock.decisionFor', { sku: line.sku })}
                  </Label>
                  <NativeSelect
                    id={`decision-${line.id}`}
                    value={decisions[line.id] ?? ''}
                    onChange={(event) =>
                      setDecisions((current) => ({
                        ...current,
                        [line.id]: event.target.value as RestockDecision,
                      }))
                    }
                  >
                    <NativeSelectOption value="">{t('restock.choose')}</NativeSelectOption>
                    {RESTOCK_DECISIONS.map((decision) => (
                      <NativeSelectOption key={decision} value={decision}>
                        {t(`decisions.${decision}`)}
                      </NativeSelectOption>
                    ))}
                  </NativeSelect>
                </div>
              ) : (
                <span className="text-sm text-muted-foreground">{t('restock.pending')}</span>
              )}
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  )
}

/**
 * The Refund that completes the Return: suggested as what the Customer paid for the items
 * (plus shipping when ticked), never above that or what the Order may still refund.
 */
function RefundCard({ item }: { item: OrderReturn }) {
  const { t } = useTranslation('returns')
  const { money } = useFormatters()
  const refund = useRefundReturn()
  const suggestion = item.refund_suggestion ?? { items: '0.00', shipping: null, refundable: '0.00' }
  const [withShipping, setWithShipping] = useState(false)
  const capFor = (shipping: boolean): Amount => {
    const suggested =
      toCents(suggestion.items) +
      (shipping && suggestion.shipping ? toCents(suggestion.shipping) : 0)
    return fromCents(Math.min(suggested, toCents(suggestion.refundable)))
  }
  const [amount, setAmount] = useState(() => String(capFor(false)))
  const [error, setError] = useState<string | null>(null)
  const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID())
  const cap = capFor(withShipping)

  function toggleShipping(next: boolean) {
    setWithShipping(next)
    setAmount(String(capFor(next)))
  }

  async function submit() {
    const typed = amount.trim()
    if (!/^\d+(\.\d{1,2})?$/.test(typed)) {
      setError(t('validation.amount'))
      return
    }
    if (toCents(typed as Amount) > toCents(cap)) {
      setError(t('validation.aboveCap', { cap: money(cap) }))
      return
    }
    setError(null)
    try {
      const completed = await refund.mutateAsync({
        id: item.id,
        input: { amount: typed, refund_shipping: withShipping },
        idempotencyKey,
      })
      setIdempotencyKey(crypto.randomUUID())
      toast.success(
        i18n.t('returns:refund.done', {
          number: completed.number,
          amount: money(completed.refund_amount ?? '0.00'),
        }),
      )
    } catch (refusal) {
      setIdempotencyKey(crypto.randomUUID())
      setError(
        isApiError(refusal) && refusal.status === 422
          ? (refusal.fieldError('amount') ?? apiErrorMessage(refusal))
          : apiErrorMessage(refusal),
      )
    }
  }

  return (
    <Card className="js-reveal rounded-2xl">
      <CardHeader>
        <CardTitle className="text-base">{t('refund.title')}</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <dl className="grid grid-cols-1 gap-3 rounded-xl border bg-muted/30 p-3 text-sm sm:grid-cols-3">
          <div className="flex flex-col gap-0.5">
            <dt className="text-xs text-muted-foreground">{t('refund.items')}</dt>
            <dd className="font-medium tabular-nums">{money(suggestion.items)}</dd>
          </div>
          <div className="flex flex-col gap-0.5">
            <dt className="text-xs text-muted-foreground">{t('refund.shipping')}</dt>
            <dd className="font-medium tabular-nums">
              {suggestion.shipping === null
                ? t('refund.shippingRefunded')
                : money(suggestion.shipping)}
            </dd>
          </div>
          <div className="flex flex-col gap-0.5">
            <dt className="text-xs text-muted-foreground">{t('refund.refundable')}</dt>
            <dd className="font-medium tabular-nums">{money(suggestion.refundable)}</dd>
          </div>
        </dl>
        {suggestion.shipping !== null && (
          <div className="flex items-center gap-2">
            <Checkbox
              id="refund-shipping"
              checked={withShipping}
              onCheckedChange={(checked) => toggleShipping(checked === true)}
            />
            <Label htmlFor="refund-shipping">{t('refund.alsoShipping')}</Label>
          </div>
        )}
        <div className="flex flex-col gap-2">
          <Label htmlFor="refund-amount">{t('fields.refundAmount')}</Label>
          <Input
            id="refund-amount"
            dir="ltr"
            inputMode="decimal"
            className="max-w-48"
            value={amount}
            aria-invalid={error !== null}
            aria-describedby="refund-amount-hint"
            onChange={(event) => setAmount(event.target.value)}
          />
          <p id="refund-amount-hint" className="text-xs text-muted-foreground">
            {t('refund.cap', { cap: money(cap) })}
          </p>
        </div>
        {error && (
          <p role="alert" className="text-sm text-destructive">
            {error}
          </p>
        )}
        <Button className="w-fit gap-2" disabled={refund.isPending} onClick={() => void submit()}>
          {refund.isPending ? (
            <InlineSpinner className="size-4" />
          ) : (
            <Undo2Icon className="size-4" aria-hidden="true" />
          )}
          {t('refund.submit')}
        </Button>
      </CardContent>
    </Card>
  )
}

/** Approves or rejects a Requested Return, with an optional note. */
function DecideDialog({
  item,
  decision,
  onClose,
}: {
  item: OrderReturn
  decision: 'approve' | 'reject'
  onClose: () => void
}) {
  const { t } = useTranslation('returns')
  const dir = useDirection()
  const decide = useDecideReturn()
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function submit() {
    try {
      await decide.mutateAsync({ id: item.id, decision, note: note.trim() })
      toast.success(i18n.t(`returns:decide.${decision}Done`, { number: item.number }))
      onClose()
    } catch (refusal) {
      setError(apiErrorMessage(refusal))
    }
  }

  return (
    <Dialog open onOpenChange={(next) => !next && !decide.isPending && onClose()}>
      <DialogContent dir={dir} className="sm:max-w-md" data-lenis-prevent>
        <DialogHeader>
          <DialogTitle>{t(`decide.${decision}Title`, { number: item.number })}</DialogTitle>
          <DialogDescription>{t(`decide.${decision}Description`)}</DialogDescription>
        </DialogHeader>
        <div className="flex flex-col gap-2">
          <Label htmlFor="decision-note">{t('fields.note')}</Label>
          <Textarea
            id="decision-note"
            dir="auto"
            rows={2}
            maxLength={1000}
            value={note}
            onChange={(event) => setNote(event.target.value)}
          />
          {error && (
            <p role="alert" className="text-sm text-destructive">
              {error}
            </p>
          )}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={decide.isPending}>
            {t('cancel')}
          </Button>
          <Button
            variant={decision === 'reject' ? 'destructive' : 'default'}
            className="gap-2"
            disabled={decide.isPending}
            onClick={() => void submit()}
          >
            {decide.isPending && <InlineSpinner className="size-4" />}
            {t(`decide.${decision}`)}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
