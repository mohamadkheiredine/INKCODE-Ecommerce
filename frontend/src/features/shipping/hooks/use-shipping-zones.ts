import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ListQuery } from '@/lib/list-query'
import { shippingZoneKeys, shippingZonesApi } from '../api/shipping-zones-api'
import type { QuoteAddress, ShippingMethodInput, ShippingZoneInput } from '../types'

/** A page of Shipping zones; the previous page stays on screen while the next one loads. */
export function useShippingZones(query: ListQuery) {
  return useQuery({
    queryKey: shippingZoneKeys.list(query),
    queryFn: () => shippingZonesApi.list(query),
    placeholderData: keepPreviousData,
  })
}

/** One Shipping zone with all its Shipping methods. */
export function useShippingZone(id: number) {
  return useQuery({
    queryKey: shippingZoneKeys.detail(id),
    queryFn: () => shippingZonesApi.get(id),
  })
}

/** What can be shipped to an address; nothing is asked until there is an address. */
export function useShippingQuote(address: QuoteAddress | null) {
  return useQuery({
    queryKey: shippingZoneKeys.quote(address ?? { country_code: '', city: '' }),
    queryFn: () => shippingZonesApi.quote(address!),
    enabled: address !== null,
  })
}

/**
 * Every change to a zone or its methods can change lists, the zone's page and quotes,
 * so all Shipping zone queries are refreshed.
 */
function useInvalidateZones() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: shippingZoneKeys.all })
}

export function useSaveShippingZone() {
  const invalidate = useInvalidateZones()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: ShippingZoneInput }) =>
      id === null ? shippingZonesApi.create(input) : shippingZonesApi.update(id, input),
    onSuccess: invalidate,
  })
}

export function useDeleteShippingZone() {
  const invalidate = useInvalidateZones()
  return useMutation({
    mutationFn: (id: number) => shippingZonesApi.remove(id),
    onSuccess: invalidate,
  })
}

type SaveMethod = { zoneId: number; id: number | null; input: ShippingMethodInput }

export function useSaveShippingMethod() {
  const invalidate = useInvalidateZones()
  return useMutation({
    mutationFn: ({ zoneId, id, input }: SaveMethod) =>
      id === null
        ? shippingZonesApi.createMethod(zoneId, input)
        : shippingZonesApi.updateMethod(id, input),
    onSuccess: invalidate,
  })
}

export function useDeleteShippingMethod() {
  const invalidate = useInvalidateZones()
  return useMutation({
    mutationFn: (id: number) => shippingZonesApi.removeMethod(id),
    onSuccess: invalidate,
  })
}
