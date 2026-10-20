import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import type { ProductStatus } from '../types'

const DOT: Record<ProductStatus, string> = {
  draft: 'bg-muted-foreground',
  active: 'bg-primary',
  archived: 'bg-destructive',
}

/** Draft, Active or Archived, with a colored dot so the status reads at a glance. */
export function ProductStatusBadge({ status }: { status: ProductStatus }) {
  const { t } = useTranslation('catalog')

  return (
    <Badge variant="outline" className="gap-1.5 font-normal whitespace-nowrap">
      <span className={cn('size-1.5 rounded-full', DOT[status])} aria-hidden="true" />
      {t(`products.status.${status}`)}
    </Badge>
  )
}
