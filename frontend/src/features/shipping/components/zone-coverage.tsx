import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { usePlaceName } from '../labels'
import type { ZoneLocation } from '../types'

/** The places a Shipping zone covers as chips, the first few only unless `limit` is left out. */
export function ZoneCoverage({
  locations,
  limit,
  className,
}: {
  locations: ZoneLocation[]
  limit?: number
  className?: string
}) {
  const { t } = useTranslation('shipping')
  const placeName = usePlaceName()
  const shown = limit === undefined ? locations : locations.slice(0, limit)
  const hidden = locations.length - shown.length

  return (
    <ul className={cn('flex flex-wrap gap-1.5', className)}>
      {shown.map((location) => (
        <li key={`${location.country_code}/${location.city ?? ''}`}>
          <Badge variant={location.city === null ? 'secondary' : 'outline'} dir="auto">
            {placeName(location)}
          </Badge>
        </li>
      ))}
      {hidden > 0 && (
        <li>
          <Badge variant="ghost" className="text-muted-foreground">
            {t('zones.morePlaces', { count: hidden })}
          </Badge>
        </li>
      )}
    </ul>
  )
}
