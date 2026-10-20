import {
  keepPreviousData,
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query'
import type { ListQuery } from '@/lib/list-query'
import { customerKeys, customersApi } from '../api/customers-api'
import type { AddressInput, CustomerInput } from '../types'

/** A page of Customers; the previous page stays on screen while the next one loads. */
export function useCustomers(query: ListQuery) {
  return useQuery({
    queryKey: customerKeys.list(query),
    queryFn: () => customersApi.list(query),
    placeholderData: keepPreviousData,
  })
}

/** A Customer's profile: details, Tags and addresses. */
export function useCustomer(id: number) {
  return useQuery({ queryKey: customerKeys.detail(id), queryFn: () => customersApi.get(id) })
}

/** Every Tag, for the list filter and the Tag picker. */
export function useCustomerTags() {
  return useQuery({ queryKey: customerKeys.tags(), queryFn: () => customersApi.tags() })
}

export function useSaveCustomer() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: CustomerInput }) =>
      id === null ? customersApi.create(input) : customersApi.update(id, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: customerKeys.all }),
  })
}

export function useDeleteCustomer() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => customersApi.remove(id),
    onSuccess: (_, id) => {
      queryClient.removeQueries({ queryKey: customerKeys.detail(id) })
      return queryClient.invalidateQueries({ queryKey: customerKeys.lists() })
    },
  })
}

/**
 * Adds or saves one of a Customer's addresses. Moving a default changes other addresses
 * too, so the whole profile is fetched again.
 */
export function useSaveAddress(customerId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: AddressInput }) =>
      id === null
        ? customersApi.addAddress(customerId, input)
        : customersApi.updateAddress(customerId, id, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: customerKeys.detail(customerId) }),
  })
}

export function useRemoveAddress(customerId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (addressId: number) => customersApi.removeAddress(customerId, addressId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: customerKeys.detail(customerId) }),
  })
}

/** A Customer's notes, newest first, one page at a time ("Show older notes"). */
export function useCustomerNotes(customerId: number) {
  return useInfiniteQuery({
    queryKey: customerKeys.notes(customerId),
    queryFn: ({ pageParam }) => customersApi.notes(customerId, pageParam),
    initialPageParam: 1,
    getNextPageParam: ({ meta }) =>
      meta.current_page < meta.last_page ? meta.current_page + 1 : undefined,
  })
}

export function useAddNote(customerId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (body: string) => customersApi.addNote(customerId, body),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: customerKeys.notes(customerId) }),
  })
}
