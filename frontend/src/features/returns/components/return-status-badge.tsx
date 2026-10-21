import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import type { ReturnStatus } from '../types'

/** Each Return status's tint, from theme tokens only (light and dark alike). */
const TONES: Record<ReturnStatus, string> = {
  requested: 'border-warning/40 bg-warning/15 text-foreground',
  approved: 'border-chart-2/40 bg-chart-2/15 text-foreground',
  rejected: 'border-destructive/30 bg-destructive/10 text-destructive',
  received: 'border-chart-3/40 bg-chart-3/15 text-foreground',
  completed: 'border-success/40 bg-success/15 text-foreground',
}

export function ReturnStatusBadge({
  status,
  className,
}: {
  status: ReturnStatus
  className?: string
}) {
  const { t } = useTranslation('returns')
  return (
    <Badge variant="outline" className={cn('gap-1.5 font-medium', TONES[status], className)}>
      <span aria-hidden="true" className="size-1.5 rounded-full bg-current opacity-70" />
      {t(`statuses.${status}`)}
    </Badge>
  )
}
