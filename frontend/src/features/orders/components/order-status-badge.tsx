import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import type { OrderStatus } from '../types'

/** Each status's tint, from theme tokens only (light and dark alike). */
const TONES: Record<OrderStatus, string> = {
  pending: 'border-warning/40 bg-warning/15 text-foreground',
  confirmed: 'border-primary/30 bg-primary/10 text-primary',
  preparing: 'border-chart-2/40 bg-chart-2/15 text-foreground',
  ready_for_shipping: 'border-chart-2/40 bg-chart-2/15 text-foreground',
  shipped: 'border-chart-3/40 bg-chart-3/15 text-foreground',
  delivered: 'border-success/40 bg-success/15 text-foreground',
  cancelled: 'border-destructive/30 bg-destructive/10 text-destructive',
}

export function OrderStatusBadge({
  status,
  className,
}: {
  status: OrderStatus
  className?: string
}) {
  const { t } = useTranslation('orders')
  return (
    <Badge variant="outline" className={cn('gap-1.5 font-medium', TONES[status], className)}>
      <span aria-hidden="true" className="size-1.5 rounded-full bg-current opacity-70" />
      {t(`statuses.${status}`)}
    </Badge>
  )
}
