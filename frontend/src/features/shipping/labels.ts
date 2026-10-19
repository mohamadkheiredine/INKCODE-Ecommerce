import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useCountryNames } from '@/lib/countries'
import type { ZoneLocation } from './types'

/** "Lebanon" for a whole country, "Beirut, Lebanon" for a city, in the interface language. */
export function usePlaceName(): (location: ZoneLocation) => string {
  const { t } = useTranslation('shipping')
  const { name } = useCountryNames()
  return useCallback(
    ({ country_code, city }: ZoneLocation) =>
      city === null ? name(country_code) : t('zones.cityIn', { city, country: name(country_code) }),
    [t, name],
  )
}

/** "Same day", "1 day", "2–4 days": how long a Shipping method takes. */
export function useDeliveryDays(): (min: number, max: number) => string {
  const { t } = useTranslation('shipping')
  return useCallback(
    (min: number, max: number) => {
      if (max === 0) return t('delivery.sameDay')
      if (min === max) return t('delivery.days', { count: max })
      return t('delivery.range', { min, max })
    },
    [t],
  )
}
