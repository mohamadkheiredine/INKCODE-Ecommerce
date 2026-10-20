import { countryName } from '@/lib/countries'
import { useTranslation } from 'react-i18next'
import type { OrderAddress } from '../types'

/** A postal address on several lines, in the order people write it. */
export function AddressBlock({ address }: { address: OrderAddress }) {
  const { i18n } = useTranslation()
  return (
    <address className="flex flex-col text-sm not-italic" dir="auto">
      <span className="font-medium">{address.recipient_name}</span>
      <span>{address.line1}</span>
      {address.line2 && <span>{address.line2}</span>}
      <span>
        {[address.city, address.postal_code].filter(Boolean).join(' ')} ·{' '}
        {countryName(address.country_code, i18n.language)}
      </span>
      {address.phone && (
        <span dir="ltr" className="text-start text-muted-foreground">
          {address.phone}
        </span>
      )}
    </address>
  )
}
