import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import type { Segment } from '../types'

/** Each Segment's tint, from theme tokens only (light and dark alike). */
const TONES: Record<Segment, string> = {
  new: 'border-primary/30 bg-primary/10 text-primary',
  vip: 'border-success/40 bg-success/15 text-foreground',
  at_risk: 'border-warning/40 bg-warning/15 text-foreground',
  regular: 'border-border bg-muted text-muted-foreground',
}

export function SegmentBadge({ segment, className }: { segment: Segment; className?: string }) {
  const { t } = useTranslation('customers')
  return (
    <Badge variant="outline" className={cn('gap-1.5 font-medium', TONES[segment], className)}>
      <span aria-hidden="true" className="size-1.5 rounded-full bg-current opacity-70" />
      {t(`segments.${segment}`)}
    </Badge>
  )
}
