import { Undo2Icon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Order } from '@/features/orders/types'
import { useFormatters } from '@/lib/format'
import { refundNeeded, toCents } from '../money'

/**
 * "Refund needed: $X" in a cancel or reduce dialog, when the Order holds more money than it
 * will owe after the change. The API records that Refund in the same step.
 */
export function RefundNeeded({ order, owedAfterCents }: { order: Order; owedAfterCents: number }) {
  const { t } = useTranslation('orders')
  const { money } = useFormatters()
  const amount = refundNeeded(order, owedAfterCents)
  if (toCents(amount) === 0) return null

  return (
    <div
      role="status"
      className="flex gap-3 rounded-xl border border-warning/40 bg-warning/10 p-3 text-sm"
    >
      <Undo2Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      <div className="flex flex-col gap-0.5">
        <span className="font-medium">{t('cancel.refundNeeded', { amount: money(amount) })}</span>
        <span className="text-xs text-muted-foreground">{t('cancel.refundNeededHint')}</span>
      </div>
    </div>
  )
}
