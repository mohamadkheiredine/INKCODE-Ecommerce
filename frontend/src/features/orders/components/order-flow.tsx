import {
  CircleDotIcon,
  MessageSquareTextIcon,
  TruckIcon,
  Undo2Icon,
  WalletIcon,
} from 'lucide-react'
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
import { RefundNeeded } from '@/features/payments/components/refund-needed'
import { totalAfterCancellingCents } from '@/features/payments/money'
import { isApiError } from '@/lib/api/api-error'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import {
  useAddOrderNote,
  useAssignOrder,
  useCancelOrderLines,
  useOrderAssignees,
} from '../hooks/use-orders'
import { cn } from '@/lib/utils'
import type { Order, TimelineEvent } from '../types'
import type { Amount } from '@/lib/format'

const NOTE_MAX_LENGTH = 2000

/**
 * Partial cancellation: how many units of each line to cancel, with an optional reason.
 * Their stock is released and the Order re-priced; cancelling every unit cancels it.
 */
export function CancelLinesDialog({
  order,
  open,
  onClose,
}: {
  order: Order
  open: boolean
  onClose: () => void
}) {
  const { t } = useTranslation('orders')
  const dir = useDirection()
  const { number } = useFormatters()
  const cancelLines = useCancelOrderLines()
  // Shipped units cannot be cancelled: each line offers only what is left to ship.
  const lines = (order.lines ?? [])
    .map((line) => ({ ...line, quantity: line.quantity - (line.shipped_quantity ?? 0) }))
    .filter((line) => line.quantity > 0)
  const [quantities, setQuantities] = useState<Record<number, string>>({})
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID())

  const close = () => {
    setQuantities({})
    setNote('')
    setError(null)
    onClose()
  }

  const chosen = lines
    .map((line) => ({ line_id: line.id, quantity: Number(quantities[line.id] || 0) }))
    .filter((line) => line.quantity > 0)
  const invalid = lines.some((line) => {
    const value = quantities[line.id]?.trim() ?? ''
    return value !== '' && (!/^\d+$/.test(value) || Number(value) > line.quantity)
  })
  // Cancelling every unit (none shipped) cancels the whole Order, which then owes nothing.
  const cancelsEverything =
    chosen.reduce((sum, line) => sum + line.quantity, 0) ===
    (order.lines ?? []).reduce((sum, line) => sum + line.quantity, 0)

  const submit = async () => {
    setError(null)
    try {
      const updated = await cancelLines.mutateAsync({
        id: order.id,
        lines: chosen,
        note: note.trim(),
        idempotencyKey,
      })
      toast.success(
        i18n.t(updated.status === 'cancelled' ? 'orders:cancel.done' : 'orders:cancelLines.done', {
          number: updated.number,
        }),
      )
      setIdempotencyKey(crypto.randomUUID())
      close()
    } catch (refusal) {
      setIdempotencyKey(crypto.randomUUID())
      const fieldMessage =
        isApiError(refusal) && refusal.status === 422
          ? chosen.map((_, index) => refusal.fieldError(`lines.${index}.quantity`)).find(Boolean)
          : undefined
      setError(fieldMessage ?? apiErrorMessage(refusal))
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !next && !cancelLines.isPending && close()}>
      <DialogContent dir={dir} className="sm:max-w-lg" data-lenis-prevent>
        <DialogHeader>
          <DialogTitle>{t('cancelLines.title', { number: order.number })}</DialogTitle>
          <DialogDescription>{t('cancelLines.description')}</DialogDescription>
        </DialogHeader>
        <ul className="flex flex-col divide-y rounded-xl border">
          {lines.map((line) => (
            <li key={line.id} className="flex items-center gap-3 p-3">
              <div className="flex min-w-0 flex-1 flex-col">
                <span dir="auto" className="truncate font-medium">
                  {line.product_name}
                </span>
                <span className="text-xs text-muted-foreground">
                  <span dir="ltr" className="font-mono">
                    {line.sku}
                  </span>{' '}
                  ·{' '}
                  {t('cancelLines.ordered', {
                    count: line.quantity,
                    formatted: number(line.quantity),
                  })}
                </span>
              </div>
              <div className="flex w-24 flex-col gap-1">
                <Label htmlFor={`cancel-line-${line.id}`} className="sr-only">
                  {t('cancelLines.quantityFor', { sku: line.sku })}
                </Label>
                <Input
                  id={`cancel-line-${line.id}`}
                  inputMode="numeric"
                  placeholder="0"
                  value={quantities[line.id] ?? ''}
                  aria-invalid={
                    Number(quantities[line.id] || 0) > line.quantity ||
                    /\D/.test(quantities[line.id] ?? '')
                  }
                  onChange={(event) =>
                    setQuantities((current) => ({ ...current, [line.id]: event.target.value }))
                  }
                />
              </div>
            </li>
          ))}
        </ul>
        {!invalid && chosen.length > 0 && (
          <RefundNeeded
            order={order}
            owedAfterCents={cancelsEverything ? 0 : totalAfterCancellingCents(order, chosen)}
          />
        )}
        <div className="flex flex-col gap-2">
          <Label htmlFor="cancel-lines-note">{t('cancel.reason')}</Label>
          <Textarea
            id="cancel-lines-note"
            dir="auto"
            value={note}
            maxLength={500}
            rows={2}
            onChange={(event) => setNote(event.target.value)}
          />
        </div>
        {(error || invalid) && (
          <p role="alert" className="text-sm text-destructive">
            {error ?? t('cancelLines.tooMany')}
          </p>
        )}
        <DialogFooter>
          <Button variant="outline" onClick={close} disabled={cancelLines.isPending}>
            {t('cancel.keep')}
          </Button>
          <Button
            variant="destructive"
            className="gap-2"
            disabled={cancelLines.isPending || invalid || chosen.length === 0}
            onClick={() => void submit()}
          >
            {cancelLines.isPending && <InlineSpinner className="size-4" />}
            {t('cancelLines.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

/** Who handles the Order; Users who may assign pick an active Admin, Operations or Sales User. */
export function AssigneeCard({ order }: { order: Order }) {
  const { t } = useTranslation('orders')
  const can = useCan()
  const mayAssign = can('orders.assign')
  const assignees = useOrderAssignees(mayAssign)
  const assign = useAssignOrder()

  const choose = async (value: string) => {
    try {
      const updated = await assign.mutateAsync({
        id: order.id,
        assigneeId: value === '' ? null : Number(value),
      })
      toast.success(
        updated.assignee
          ? i18n.t('orders:assignee.done', { name: updated.assignee.name })
          : i18n.t('orders:assignee.cleared'),
      )
    } catch (error) {
      toast.error(apiErrorMessage(error))
    }
  }

  return (
    <Card className="js-reveal rounded-2xl">
      <CardHeader>
        <CardTitle className="text-base">{t('assignee.title')}</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-2 text-sm">
        {mayAssign ? (
          <>
            <Label htmlFor="order-assignee" className="sr-only">
              {t('assignee.title')}
            </Label>
            <div className="flex items-center gap-2">
              <NativeSelect
                id="order-assignee"
                className="w-full"
                value={order.assignee ? String(order.assignee.id) : ''}
                disabled={assign.isPending || assignees.isPending}
                onChange={(event) => void choose(event.target.value)}
              >
                <NativeSelectOption value="">{t('assignee.none')}</NativeSelectOption>
                {order.assignee &&
                  !assignees.data?.some((user) => user.id === order.assignee?.id) && (
                    <NativeSelectOption value={String(order.assignee.id)}>
                      {order.assignee.name}
                    </NativeSelectOption>
                  )}
                {(assignees.data ?? []).map((user) => (
                  <NativeSelectOption key={user.id} value={String(user.id)}>
                    {user.name}
                  </NativeSelectOption>
                ))}
              </NativeSelect>
              {assign.isPending && <InlineSpinner className="size-4" />}
            </div>
          </>
        ) : (
          <span dir="auto" className="font-medium">
            {order.assignee?.name ?? t('assignee.none')}
          </span>
        )}
      </CardContent>
    </Card>
  )
}

/** The headline of one timeline entry. */
function timelineTitle(event: TimelineEvent, money: (amount: Amount) => string): string {
  const t = i18n.t.bind(i18n)
  switch (event.type) {
    case 'note':
      return t('orders:timeline.note')
    case 'payment':
      return event.status === 'failed'
        ? t('orders:timeline.paymentFailed', { amount: money(event.amount) })
        : t(`orders:timeline.${event.kind}`, {
            amount: money(event.amount),
            method: t(`payments:methods.${event.method}`),
          })
    case 'shipment':
      return t(
        event.to_status === 'delivered'
          ? 'orders:timeline.shipmentDelivered'
          : 'orders:timeline.shipmentShipped',
        { number: event.shipment.number },
      )
    case 'status':
      return event.from_status === null
        ? t('orders:historyCreated')
        : t('orders:historyMoved', { status: t(`orders:statuses.${event.to_status}`) })
  }
}

/** The note shown under an entry: a note's body, or the reason a status or payment carries. */
function timelineNote(event: TimelineEvent): string | null {
  if (event.type === 'note') return event.body
  if (event.type === 'shipment') return null
  return event.note
}

const TIMELINE_ICONS = {
  status: { icon: CircleDotIcon, className: 'text-primary' },
  shipment: { icon: TruckIcon, className: 'text-chart-3' },
  note: { icon: MessageSquareTextIcon, className: 'text-muted-foreground' },
  payment: { icon: WalletIcon, className: 'text-success' },
  refund: { icon: Undo2Icon, className: 'text-destructive' },
} as const

/**
 * Status changes, payments and Refunds (for Roles that see the Payment ledger) and notes,
 * oldest first; Users who edit Orders add notes (Locked ones too).
 */
export function TimelineCard({ order }: { order: Order }) {
  const { t } = useTranslation('orders')
  const can = useCan()
  const { dateTime, money } = useFormatters()
  const addNote = useAddOrderNote()
  const [body, setBody] = useState('')
  const [error, setError] = useState<string | null>(null)

  const submit = async () => {
    setError(null)
    if (body.trim() === '') {
      setError(t('validation.noteRequired'))
      return
    }
    try {
      await addNote.mutateAsync({ id: order.id, body: body.trim() })
      setBody('')
      toast.success(i18n.t('orders:timeline.noteAdded'))
    } catch (refusal) {
      setError(
        isApiError(refusal) && refusal.status === 422
          ? (refusal.fieldError('body') ?? apiErrorMessage(refusal))
          : apiErrorMessage(refusal),
      )
    }
  }

  return (
    <Card className="js-reveal rounded-2xl">
      <CardHeader>
        <CardTitle className="text-base">{t('timeline.title')}</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-5">
        <ol className="flex flex-col gap-4">
          {(order.timeline ?? []).map((event) => {
            const { icon: Icon, className } =
              TIMELINE_ICONS[event.type === 'payment' ? event.kind : event.type]
            return (
              <li key={`${event.type}-${event.id}`} className="flex gap-3">
                <Icon
                  className={cn(
                    'mt-0.5 size-4 shrink-0',
                    event.type === 'payment' && event.status === 'failed'
                      ? 'text-destructive'
                      : className,
                  )}
                  aria-hidden="true"
                />
                <div className="flex min-w-0 flex-col gap-0.5">
                  <span className="text-sm font-medium">{timelineTitle(event, money)}</span>
                  <span className="text-xs text-muted-foreground">
                    {t('historyBy', {
                      user: event.user?.name ?? t('system'),
                      when: dateTime(event.created_at),
                    })}
                  </span>
                  {timelineNote(event) && (
                    <p dir="auto" className="text-sm whitespace-pre-line text-muted-foreground">
                      {timelineNote(event)}
                    </p>
                  )}
                </div>
              </li>
            )
          })}
        </ol>
        {can('orders.update') && (
          <div className="flex flex-col gap-2 border-t pt-4">
            <Label htmlFor="order-note">{t('timeline.addNote')}</Label>
            <Textarea
              id="order-note"
              dir="auto"
              rows={2}
              maxLength={NOTE_MAX_LENGTH}
              value={body}
              aria-invalid={error !== null}
              aria-describedby={error ? 'order-note-error' : undefined}
              onChange={(event) => setBody(event.target.value)}
            />
            {error && (
              <p id="order-note-error" role="alert" className="text-sm text-destructive">
                {error}
              </p>
            )}
            <Button
              variant="outline"
              className="gap-2 self-end"
              disabled={addNote.isPending}
              onClick={() => void submit()}
            >
              {addNote.isPending && <InlineSpinner className="size-4" />}
              {t('timeline.submitNote')}
            </Button>
          </div>
        )}
      </CardContent>
    </Card>
  )
}
