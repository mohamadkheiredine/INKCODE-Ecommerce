import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import type { CouponState } from '../types'

/** Each state's tint, from theme tokens only (light and dark alike). */
const TONES: Record<CouponState, string> = {
  active: 'border-success/40 bg-success/15 text-foreground',
  scheduled: 'border-warning/40 bg-warning/15 text-foreground',
  expired: 'border-border bg-muted text-muted-foreground',
}

/** Whether the Coupon can be used today, starts later or has ended. */
export function CouponStateBadge({ state }: { state: CouponState }) {
  const { t } = useTranslation('coupons')
  return (
    <Badge variant="outline" className={cn('gap-1.5 font-medium', TONES[state])}>
      <span aria-hidden="true" className="size-1.5 rounded-full bg-current opacity-70" />
      {t(`states.${state}`)}
    </Badge>
  )
}
