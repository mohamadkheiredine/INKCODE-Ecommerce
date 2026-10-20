import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ListQuery } from '@/lib/list-query'
import { carrierKeys, carriersApi } from '../api/carriers-api'
import type { CarrierInput } from '../types'

/** A page of Carriers; the previous page stays on screen while the next one loads. */
export function useCarriers(query: ListQuery) {
  return useQuery({
    queryKey: carrierKeys.list(query),
    queryFn: () => carriersApi.list(query),
    placeholderData: keepPreviousData,
  })
}

export function useSaveCarrier() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: CarrierInput }) =>
      id === null ? carriersApi.create(input) : carriersApi.update(id, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: carrierKeys.all }),
  })
}

export function useDeleteCarrier() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => carriersApi.remove(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: carrierKeys.all }),
  })
}
