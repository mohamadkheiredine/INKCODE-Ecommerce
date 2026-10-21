import { BanknoteIcon, CreditCardIcon, Undo2Icon, WalletIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
import type { Order } from '@/features/orders/types'
import { isApiError } from '@/lib/api/api-error'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { cn } from '@/lib/utils'
import { useIssueRefund, useRecordPayment } from '../hooks/use-payments'
import { fromCents, isAmount, netCapturedCents, toCents } from '../money'
import { type CardOutcome, PAYMENT_METHODS, type Payment, type PaymentMethod } from '../types'
import { PaymentStatusBadge } from './payment-status-badge'

const METHOD_ICONS = { cash_on_delivery: BanknoteIcon, manual: WalletIcon, card: CreditCardIcon }

/**
 * The Order's Payment ledger: its Payment status, what was captured, refunded and is still
 * due, every row (failed Card charges too), and the buttons to record a payment or issue a
 * Refund for the Roles allowed to. Shown only to Roles with payments.view.
 */
export function PaymentLedgerCard({ order }: { order: Order }) {
  const { t } = useTranslation('payments')
  const can = useCan()
  const { money, dateTime } = useFormatters()
  const [dialog, setDialog] = useState<'payment' | 'refund' | null>(null)
  const payments = order.payments ?? []
  const mayPay = can('payments.record') && toCents(order.amount_due) > 0
  const mayRefund = can('refunds.issue') && netCapturedCents(order) > 0

  return (
    <Card className="js-reveal rounded-2xl">
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <CardTitle className="text-base">{t('ledger.title')}</CardTitle>
          <PaymentStatusBadge status={order.payment_status} />
        </div>
        <div className="flex flex-wrap gap-2">
          {mayRefund && (
            <Button
              variant="outline"
              size="sm"
              className="gap-2"
              onClick={() => setDialog('refund')}
            >
              <Undo2Icon className="size-4" aria-hidden="true" />
              {t('refund.action')}
            </Button>
          )}
          {mayPay && (
            <Button size="sm" className="gap-2" onClick={() => setDialog('payment')}>
              <WalletIcon className="size-4" aria-hidden="true" />
              {t('record.action')}
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <dl className="grid grid-cols-3 gap-3 rounded-xl border bg-muted/30 p-3 text-sm">
          {(
            [
              ['captured', order.captured_amount],
              ['refunded', order.refunded_amount],
              ['due', order.amount_due],
            ] as const
          ).map(([key, amount]) => (
            <div key={key} className="flex flex-col gap-0.5">
              <dt className="text-xs text-muted-foreground">{t(`ledger.${key}`)}</dt>
              <dd className="font-medium tabular-nums">{money(amount)}</dd>
            </div>
          ))}
        </dl>
        {payments.length === 0 ? (
          <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-6 text-center">
            <WalletIcon className="size-6 text-muted-foreground" aria-hidden="true" />
            <p className="text-sm font-medium">{t('ledger.emptyTitle')}</p>
            <p className="text-xs text-muted-foreground">
              {mayPay ? t('ledger.emptyDescription') : t('ledger.emptyDescriptionReadOnly')}
            </p>
            {mayPay && (
              <Button size="sm" variant="outline" onClick={() => setDialog('payment')}>
                {t('record.action')}
              </Button>
            )}
          </div>
        ) : (
          <ul className="flex flex-col divide-y rounded-xl border" aria-label={t('ledger.title')}>
            {payments.map((payment) => (
              <LedgerRow key={payment.id} payment={payment} money={money} dateTime={dateTime} />
            ))}
          </ul>
        )}
      </CardContent>
      <RecordPaymentDialog
        order={order}
        open={dialog === 'payment'}
        onClose={() => setDialog(null)}
      />
      <IssueRefundDialog order={order} open={dialog === 'refund'} onClose={() => setDialog(null)} />
    </Card>
  )
}

function LedgerRow({
  payment,
  money,
  dateTime,
}: {
  payment: Payment
  money: (amount: Payment['amount']) => string
  dateTime: (value: string) => string
}) {
  const { t } = useTranslation('payments')
  const Icon = payment.kind === 'refund' ? Undo2Icon : METHOD_ICONS[payment.method]
  const failed = payment.status === 'failed'

  return (
    <li className="flex items-start gap-3 p-3">
      <Icon
        className={cn(
          'mt-0.5 size-4 shrink-0',
          failed ? 'text-destructive' : 'text-muted-foreground',
        )}
        aria-hidden="true"
      />
      <div className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className="text-sm font-medium">
          {t(`kinds.${payment.kind}`)} · {t(`methods.${payment.method}`)}
          {failed && <span className="ms-2 text-xs text-destructive">{t('outcomes.failed')}</span>}
        </span>
        <span className="text-xs text-muted-foreground">
          {t('ledger.by', {
            user: payment.user?.name ?? t('system'),
            when: dateTime(payment.created_at),
          })}
        </span>
        {payment.gateway_reference && (
          <span dir="ltr" className="text-start font-mono text-xs text-muted-foreground">
            {payment.gateway_reference}
          </span>
        )}
        {payment.note && (
          <p dir="auto" className="text-xs text-muted-foreground">
            {payment.note}
          </p>
        )}
      </div>
      <span
        className={cn(
          'font-medium whitespace-nowrap tabular-nums',
          failed && 'text-muted-foreground line-through',
          payment.kind === 'refund' && 'text-destructive',
        )}
      >
        {payment.kind === 'refund' ? '−' : ''}
        {money(payment.amount)}
      </span>
    </li>
  )
}

type FieldErrors = Partial<Record<'method' | 'amount' | 'card_outcome' | 'note', string>>

/** Server field errors (422) by field, or null when the refusal is not about the fields. */
function fieldErrorsOf(refusal: unknown, fields: (keyof FieldErrors)[]): FieldErrors | null {
  if (!isApiError(refusal) || refusal.status !== 422) return null
  const errors = Object.fromEntries(
    fields.flatMap((field) => {
      const message = refusal.fieldError(field)
      return message ? [[field, message]] : []
    }),
  )
  return Object.keys(errors).length > 0 ? errors : null
}

/** Records a Cash on delivery, Manual or (mock) Card payment of up to the amount still due. */
function RecordPaymentDialog({
  order,
  open,
  onClose,
}: {
  order: Order
  open: boolean
  onClose: () => void
}) {
  const { t } = useTranslation('payments')
  const dir = useDirection()
  const { money } = useFormatters()
  const record = useRecordPayment()
  const [method, setMethod] = useState<PaymentMethod>('manual')
  const [amount, setAmount] = useState('')
  const [cardOutcome, setCardOutcome] = useState<CardOutcome>('approve')
  const [note, setNote] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [refusal, setRefusal] = useState<string | null>(null)
  const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID())

  const close = () => {
    setAmount('')
    setNote('')
    setErrors({})
    setRefusal(null)
    onClose()
  }

  const submit = async () => {
    setRefusal(null)
    const typed = amount.trim()
    if (!isAmount(typed)) {
      setErrors({ amount: t('validation.amount') })
      return
    }
    if (toCents(typed as `${number}`) > toCents(order.amount_due)) {
      setErrors({ amount: t('validation.aboveDue', { due: money(order.amount_due) }) })
      return
    }
    setErrors({})
    try {
      const { payment } = await record.mutateAsync({
        orderId: order.id,
        input: {
          method,
          amount: typed,
          note: note.trim(),
          ...(method === 'card' ? { card_outcome: cardOutcome } : {}),
        },
        idempotencyKey,
      })
      setIdempotencyKey(crypto.randomUUID())
      if (payment.status === 'failed') {
        toast.error(i18n.t('payments:record.declined', { amount: money(payment.amount) }))
      } else {
        toast.success(i18n.t('payments:record.done', { amount: money(payment.amount) }))
      }
      close()
    } catch (error) {
      setIdempotencyKey(crypto.randomUUID())
      const fieldErrors = fieldErrorsOf(error, ['method', 'amount', 'card_outcome', 'note'])
      if (fieldErrors) setErrors(fieldErrors)
      else setRefusal(apiErrorMessage(error))
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !next && !record.isPending && close()}>
      <DialogContent dir={dir} className="sm:max-w-md" data-lenis-prevent>
        <DialogHeader>
          <DialogTitle>{t('record.title', { number: order.number })}</DialogTitle>
          <DialogDescription>
            {t('record.description', { due: money(order.amount_due) })}
          </DialogDescription>
        </DialogHeader>
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="payment-method">{t('fields.method')}</Label>
            <NativeSelect
              id="payment-method"
              className="w-full"
              value={method}
              onChange={(event) => setMethod(event.target.value as PaymentMethod)}
            >
              {PAYMENT_METHODS.map((option) => (
                <NativeSelectOption key={option} value={option}>
                  {t(`methods.${option}`)}
                </NativeSelectOption>
              ))}
            </NativeSelect>
          </div>
          <AmountField
            id="payment-amount"
            value={amount}
            error={errors.amount}
            onChange={setAmount}
          />
          {method === 'card' && (
            <fieldset className="flex flex-col gap-2 rounded-xl border p-3">
              <legend className="px-1 text-sm font-medium">{t('fields.cardOutcome')}</legend>
              <p className="text-xs text-muted-foreground">{t('record.cardHint')}</p>
              <div className="flex gap-4">
                {(['approve', 'decline'] as const).map((outcome) => (
                  <label key={outcome} className="flex items-center gap-2 text-sm">
                    <input
                      type="radio"
                      name="card-outcome"
                      value={outcome}
                      checked={cardOutcome === outcome}
                      onChange={() => setCardOutcome(outcome)}
                      className="accent-primary"
                    />
                    {t(`cardOutcomes.${outcome}`)}
                  </label>
                ))}
              </div>
              {errors.card_outcome && (
                <p role="alert" className="text-sm text-destructive">
                  {errors.card_outcome}
                </p>
              )}
            </fieldset>
          )}
          <NoteField id="payment-note" value={note} error={errors.note} onChange={setNote} />
          {refusal && (
            <p role="alert" className="text-sm text-destructive">
              {refusal}
            </p>
          )}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={close} disabled={record.isPending}>
            {t('cancel')}
          </Button>
          <Button className="gap-2" disabled={record.isPending} onClick={() => void submit()}>
            {record.isPending && <InlineSpinner className="size-4" />}
            {t('record.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

/** Gives money back, up to the Captured amount minus earlier Refunds. */
function IssueRefundDialog({
  order,
  open,
  onClose,
}: {
  order: Order
  open: boolean
  onClose: () => void
}) {
  const { t } = useTranslation('payments')
  const dir = useDirection()
  const { money } = useFormatters()
  const refund = useIssueRefund()
  const lastMethod = [...(order.payments ?? [])]
    .reverse()
    .find((payment) => payment.kind === 'payment' && payment.status === 'succeeded')?.method
  const [method, setMethod] = useState<PaymentMethod>(lastMethod ?? 'manual')
  const [amount, setAmount] = useState('')
  const [note, setNote] = useState('')
  const [errors, setErrors] = useState<FieldErrors>({})
  const [refusal, setRefusal] = useState<string | null>(null)
  const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID())
  const refundableCents = netCapturedCents(order)
  const refundable = money(fromCents(refundableCents))

  const close = () => {
    setAmount('')
    setNote('')
    setErrors({})
    setRefusal(null)
    onClose()
  }

  const submit = async () => {
    setRefusal(null)
    const typed = amount.trim()
    if (!isAmount(typed)) {
      setErrors({ amount: t('validation.amount') })
      return
    }
    if (toCents(typed as `${number}`) > refundableCents) {
      setErrors({ amount: t('validation.aboveRefundable', { refundable }) })
      return
    }
    setErrors({})
    try {
      const { payment } = await refund.mutateAsync({
        orderId: order.id,
        input: { method, amount: typed, note: note.trim() },
        idempotencyKey,
      })
      setIdempotencyKey(crypto.randomUUID())
      toast.success(i18n.t('payments:refund.done', { amount: money(payment.amount) }))
      close()
    } catch (error) {
      setIdempotencyKey(crypto.randomUUID())
      const fieldErrors = fieldErrorsOf(error, ['method', 'amount', 'note'])
      if (fieldErrors) setErrors(fieldErrors)
      else setRefusal(apiErrorMessage(error))
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !next && !refund.isPending && close()}>
      <DialogContent dir={dir} className="sm:max-w-md" data-lenis-prevent>
        <DialogHeader>
          <DialogTitle>{t('refund.title', { number: order.number })}</DialogTitle>
          <DialogDescription>{t('refund.description', { refundable })}</DialogDescription>
        </DialogHeader>
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="refund-method">{t('fields.method')}</Label>
            <NativeSelect
              id="refund-method"
              className="w-full"
              value={method}
              onChange={(event) => setMethod(event.target.value as PaymentMethod)}
            >
              {PAYMENT_METHODS.map((option) => (
                <NativeSelectOption key={option} value={option}>
                  {t(`methods.${option}`)}
                </NativeSelectOption>
              ))}
            </NativeSelect>
          </div>
          <AmountField
            id="refund-amount"
            value={amount}
            error={errors.amount}
            onChange={setAmount}
          />
          <NoteField id="refund-note" value={note} error={errors.note} onChange={setNote} />
          {refusal && (
            <p role="alert" className="text-sm text-destructive">
              {refusal}
            </p>
          )}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={close} disabled={refund.isPending}>
            {t('cancel')}
          </Button>
          <Button
            variant="destructive"
            className="gap-2"
            disabled={refund.isPending}
            onClick={() => void submit()}
          >
            {refund.isPending && <InlineSpinner className="size-4" />}
            {t('refund.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function AmountField({
  id,
  value,
  error,
  onChange,
}: {
  id: string
  value: string
  error: string | undefined
  onChange: (value: string) => void
}) {
  const { t } = useTranslation('payments')
  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor={id}>{t('fields.amount')}</Label>
      <Input
        id={id}
        dir="ltr"
        inputMode="decimal"
        placeholder="0.00"
        value={value}
        aria-invalid={error !== undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        onChange={(event) => onChange(event.target.value)}
      />
      {error && (
        <p id={`${id}-error`} role="alert" className="text-sm text-destructive">
          {error}
        </p>
      )}
    </div>
  )
}

function NoteField({
  id,
  value,
  error,
  onChange,
}: {
  id: string
  value: string
  error: string | undefined
  onChange: (value: string) => void
}) {
  const { t } = useTranslation('payments')
  return (
    <div className="flex flex-col gap-2">
      <Label htmlFor={id}>{t('fields.note')}</Label>
      <Textarea
        id={id}
        dir="auto"
        rows={2}
        maxLength={500}
        value={value}
        aria-invalid={error !== undefined}
        onChange={(event) => onChange(event.target.value)}
      />
      {error && (
        <p role="alert" className="text-sm text-destructive">
          {error}
        </p>
      )}
    </div>
  )
}
