import { RotateCcwIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
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
import type { Order, OrderLine } from '@/features/orders/types'
import { isApiError } from '@/lib/api/api-error'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useRequestReturn } from '../hooks/use-returns'
import { RETURN_REASONS, type ReturnReason } from '../types'
import { ReturnStatusBadge } from './return-status-badge'

/** The units of a line a new Return may still claim: delivered minus already returned. */
export function returnableQuantity(line: OrderLine): number {
  return Math.max(0, line.shipped_quantity - (line.returned_quantity ?? 0))
}

/**
 * The Order's Returns, and "Request return" for Roles with returns.request while the
 * Order is Delivered, inside its Return window and has units left to return.
 */
export function OrderReturnsCard({ order }: { order: Order }) {
  const { t } = useTranslation('returns')
  const can = useCan()
  const { date } = useFormatters()
  const [requesting, setRequesting] = useState(false)
  const returns = order.returns ?? []
  const windowEndsAt = order.return_window_ends_at ?? null
  const windowOpen = windowEndsAt !== null && new Date(windowEndsAt).getTime() >= Date.now()
  const hasReturnable = (order.lines ?? []).some((line) => returnableQuantity(line) > 0)
  const mayRequest = can('returns.request') && windowOpen && hasReturnable

  if (order.status !== 'delivered' && returns.length === 0) return null

  return (
    <Card className="js-reveal rounded-2xl">
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
        <div className="flex flex-col gap-1">
          <CardTitle className="text-base">{t('title')}</CardTitle>
          {order.status === 'delivered' && (
            <p className="text-sm text-muted-foreground">
              {windowOpen && windowEndsAt
                ? t('window.openUntil', { date: date(windowEndsAt) })
                : t('window.closed')}
            </p>
          )}
        </div>
        {mayRequest && (
          <Button size="sm" className="gap-2" onClick={() => setRequesting(true)}>
            <RotateCcwIcon className="size-4" aria-hidden="true" />
            {t('request.action')}
          </Button>
        )}
      </CardHeader>
      <CardContent>
        {returns.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('noneOnOrder')}</p>
        ) : (
          <ul className="flex flex-col divide-y">
            {returns.map((item) => (
              <li key={item.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                <div className="flex flex-col">
                  <Link
                    to={`/returns/${item.id}`}
                    dir="ltr"
                    className="w-fit font-mono text-sm font-medium hover:text-primary hover:underline"
                  >
                    {item.number}
                  </Link>
                  <span className="text-xs text-muted-foreground">
                    {t(`reasons.${item.reason}`)} · {date(item.requested_at)}
                  </span>
                </div>
                <ReturnStatusBadge status={item.status} />
              </li>
            ))}
          </ul>
        )}
      </CardContent>
      {requesting && <RequestReturnDialog order={order} onClose={() => setRequesting(false)} />}
    </Card>
  )
}

/** Chooses lines, quantities and a reason for a new Return. */
function RequestReturnDialog({ order, onClose }: { order: Order; onClose: () => void }) {
  const { t } = useTranslation('returns')
  const dir = useDirection()
  const request = useRequestReturn()
  const lines = (order.lines ?? []).filter((line) => returnableQuantity(line) > 0)
  const [quantities, setQuantities] = useState<Record<number, string>>({})
  const [reason, setReason] = useState<ReturnReason>('damaged')
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID())

  async function submit() {
    const chosen = lines.flatMap((line) => {
      const quantity = Number(quantities[line.id] ?? 0)
      return quantity > 0 ? [{ line_id: line.id, quantity, max: returnableQuantity(line) }] : []
    })
    if (chosen.length === 0) {
      setError(t('validation.chooseItems'))
      return
    }
    const tooMany = chosen.find(
      (line) => !Number.isInteger(line.quantity) || line.quantity > line.max,
    )
    if (tooMany) {
      setError(t('validation.tooMany', { max: tooMany.max }))
      return
    }
    setError(null)
    try {
      const created = await request.mutateAsync({
        orderId: order.id,
        input: {
          reason,
          note: note.trim(),
          lines: chosen.map(({ line_id, quantity }) => ({ line_id, quantity })),
        },
        idempotencyKey,
      })
      toast.success(i18n.t('returns:request.done', { number: created.number }))
      onClose()
    } catch (refusal) {
      setIdempotencyKey(crypto.randomUUID())
      setError(
        isApiError(refusal) && refusal.status === 422
          ? (refusal.fieldError('lines') ??
              refusal.fieldError('reason') ??
              apiErrorMessage(refusal))
          : apiErrorMessage(refusal),
      )
    }
  }

  return (
    <Dialog open onOpenChange={(next) => !next && !request.isPending && onClose()}>
      <DialogContent dir={dir} className="sm:max-w-lg" data-lenis-prevent>
        <DialogHeader>
          <DialogTitle>{t('request.title', { number: order.number })}</DialogTitle>
          <DialogDescription>{t('request.description')}</DialogDescription>
        </DialogHeader>
        <div className="flex flex-col gap-4">
          <ul className="flex flex-col gap-3">
            {lines.map((line) => (
              <li key={line.id} className="flex items-center justify-between gap-3">
                <div className="flex min-w-0 flex-col">
                  <span dir="auto" className="truncate text-sm font-medium">
                    {line.product_name}
                  </span>
                  <span dir="ltr" className="w-fit font-mono text-xs text-muted-foreground">
                    {line.sku}
                  </span>
                  <span className="text-xs text-muted-foreground">
                    {t('request.returnable', { count: returnableQuantity(line) })}
                  </span>
                </div>
                <div className="flex shrink-0 flex-col gap-1">
                  <Label htmlFor={`return-qty-${line.id}`} className="sr-only">
                    {t('request.quantityFor', { sku: line.sku })}
                  </Label>
                  <Input
                    id={`return-qty-${line.id}`}
                    type="number"
                    inputMode="numeric"
                    min={0}
                    max={returnableQuantity(line)}
                    dir="ltr"
                    className="w-20"
                    value={quantities[line.id] ?? '0'}
                    onChange={(event) =>
                      setQuantities((current) => ({ ...current, [line.id]: event.target.value }))
                    }
                  />
                </div>
              </li>
            ))}
          </ul>
          <div className="flex flex-col gap-2">
            <Label htmlFor="return-reason">{t('fields.reason')}</Label>
            <NativeSelect
              id="return-reason"
              className="w-full"
              value={reason}
              onChange={(event) => setReason(event.target.value as ReturnReason)}
            >
              {RETURN_REASONS.map((option) => (
                <NativeSelectOption key={option} value={option}>
                  {t(`reasons.${option}`)}
                </NativeSelectOption>
              ))}
            </NativeSelect>
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="return-note">{t('fields.note')}</Label>
            <Textarea
              id="return-note"
              dir="auto"
              rows={2}
              maxLength={1000}
              value={note}
              onChange={(event) => setNote(event.target.value)}
            />
          </div>
          {error && (
            <p role="alert" className="text-sm text-destructive">
              {error}
            </p>
          )}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={request.isPending}>
            {t('cancel')}
          </Button>
          <Button className="gap-2" disabled={request.isPending} onClick={() => void submit()}>
            {request.isPending && <InlineSpinner className="size-4" />}
            {t('request.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
