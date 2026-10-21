import { useQuery } from '@tanstack/react-query'
import { customerKeys, customersApi } from '@/features/customers/api/customers-api'
import type { CustomerAddress } from '@/features/customers/types'
import { useSellableVariants } from './use-orders'

/** Helpers the New Order form and the Order edit dialog share. */

export function addressOptions(addresses: CustomerAddress[], placeholder: string) {
  return [
    { value: '', label: placeholder },
    ...addresses.map((address) => ({
      value: String(address.id),
      label: `${address.label} · ${address.line1}, ${address.city}`,
    })),
  ]
}

/** The chosen Customer's profile (with addresses); idle until one is chosen. */
export function useCustomerProfile(customerId: number) {
  return useQuery({
    queryKey: customerKeys.detail(customerId),
    queryFn: () => customersApi.get(customerId),
    enabled: customerId !== 0,
  })
}

export function useVariantSearch(search: string) {
  const variants = useSellableVariants(search)
  return { data: variants.data, isFetching: variants.isFetching }
}
