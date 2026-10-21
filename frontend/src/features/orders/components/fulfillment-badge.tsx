import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { FULFILLMENT_TONES } from '../fulfillment-tones'
import type { Fulfillment } from '../types'

/** An Order's Shipment progress, separate from its Order status. */
export function FulfillmentBadge({ status }: { status: Fulfillment['status'] }) {
  const { t } = useTranslation('orders')
  return (
    <Badge
      variant="outline"
      className={cn('font-medium whitespace-nowrap', FULFILLMENT_TONES[status])}
    >
      {t(`shipments.progress.${status}`)}
    </Badge>
  )
}
