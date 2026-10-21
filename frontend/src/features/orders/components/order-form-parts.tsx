import { type ReactNode, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useShippingQuote } from '@/features/shipping/hooks/use-shipping-zones'
import { useFormatters } from '@/lib/format'
import { useCustomerProfile } from '../hooks/use-order-form'

/**
 * The Shipping methods that deliver to the chosen shipping address (cheapest first), the
 * cheapest pre-selected; explains when none does.
 */
export function ShippingMethodField({
  customerId,
  shippingAddressId,
  current,
  onDefault,
  field,
}: {
  customerId: number
  shippingAddressId: string
  current: string
  onDefault: (id: string) => void
  field: (options: { value: string; label: string }[], hint: string | null) => ReactNode
}) {
  const { t } = useTranslation('orders')
  const { money } = useFormatters()
  const customer = useCustomerProfile(customerId)
  const address = customer.data?.addresses.find((item) => String(item.id) === shippingAddressId)
  const quote = useShippingQuote(
    address ? { country_code: address.country_code, city: address.city } : null,
  )
  const methods = address ? (quote.data?.methods ?? []) : []

  const cheapest = methods.at(0)
  const currentOffered = methods.some((method) => String(method.id) === current)
  useEffect(() => {
    if (cheapest && !currentOffered) onDefault(String(cheapest.id))
  }, [cheapest, currentOffered, onDefault])

  return field(
    [
      { value: '', label: address ? t('chooseShippingMethod') : t('chooseAddressFirst') },
      ...methods.map((method) => ({
        value: String(method.id),
        label: `${method.name} · ${money(method.price)}`,
      })),
    ],
    address && quote.data && methods.length === 0
      ? (quote.data.message ?? t('noShippingMethods'))
      : null,
  )
}
