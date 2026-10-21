import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ListQuery } from '@/lib/list-query'
import { orderKeys, ordersApi } from '../api/orders-api'
import type {
  LineCancellation,
  Order,
  OrderInput,
  OrderLineInput,
  OrderStatus,
  OrderUpdateInput,
} from '../types'

export function useOrders(query: ListQuery, enabled = true) {
  return useQuery({
    queryKey: orderKeys.list(query),
    queryFn: () => ordersApi.list(query),
    placeholderData: keepPreviousData,
    enabled,
  })
}

export function useOrder(id: number) {
  return useQuery({ queryKey: orderKeys.detail(id), queryFn: () => ordersApi.get(id) })
}

/** The live quote; it waits for at least one line. */
export function useOrderQuote(lines: OrderLineInput[], shippingMethodId: number | null) {
  return useQuery({
    queryKey: orderKeys.quote(lines, shippingMethodId),
    queryFn: () => ordersApi.quote(lines, shippingMethodId),
    placeholderData: keepPreviousData,
    enabled: lines.length > 0,
  })
}

export function useSellableVariants(search: string) {
  return useQuery({
    queryKey: orderKeys.sellableVariants(search),
    queryFn: () => ordersApi.sellableVariants(search),
    enabled: search !== '',
  })
}

/** An Order change moves stock too, so the stock screens and Products refresh with it. */
function useOrderChanged() {
  const queryClient = useQueryClient()
  return (order: Order) => {
    queryClient.setQueryData(orderKeys.detail(order.id), order)
    return Promise.all([
      queryClient.invalidateQueries({ queryKey: orderKeys.all }),
      queryClient.invalidateQueries({ queryKey: ['inventory'] }),
      queryClient.invalidateQueries({ queryKey: ['products'] }),
    ])
  }
}

export function useCreateOrder() {
  const changed = useOrderChanged()
  return useMutation({
    mutationFn: ({ input, idempotencyKey }: { input: OrderInput; idempotencyKey: string }) =>
      ordersApi.create(input, idempotencyKey),
    onSuccess: changed,
  })
}

export function useConfirmOrder() {
  const changed = useOrderChanged()
  return useMutation({
    mutationFn: ({ id, idempotencyKey }: { id: number; idempotencyKey: string }) =>
      ordersApi.confirm(id, idempotencyKey),
    onSuccess: changed,
  })
}

export function useCancelOrder() {
  const changed = useOrderChanged()
  return useMutation({
    mutationFn: ({
      id,
      note,
      idempotencyKey,
    }: {
      id: number
      note: string
      idempotencyKey: string
    }) => ordersApi.cancel(id, note, idempotencyKey),
    onSuccess: changed,
  })
}

export function useUpdateOrder() {
  const changed = useOrderChanged()
  return useMutation({
    mutationFn: ({
      id,
      input,
      idempotencyKey,
    }: {
      id: number
      input: OrderUpdateInput
      idempotencyKey: string
    }) => ordersApi.update(id, input, idempotencyKey),
    onSuccess: changed,
  })
}

export function useAdvanceOrder() {
  const changed = useOrderChanged()
  return useMutation({
    mutationFn: ({
      id,
      status,
      idempotencyKey,
    }: {
      id: number
      status: OrderStatus
      idempotencyKey: string
    }) => ordersApi.advance(id, status, idempotencyKey),
    onSuccess: changed,
  })
}

export function useCancelOrderLines() {
  const changed = useOrderChanged()
  return useMutation({
    mutationFn: ({
      id,
      lines,
      note,
      idempotencyKey,
    }: {
      id: number
      lines: LineCancellation[]
      note: string
      idempotencyKey: string
    }) => ordersApi.cancelLines(id, lines, note, idempotencyKey),
    onSuccess: changed,
  })
}

/** The Users an Order may be assigned to; only fetched for Users who may assign. */
export function useOrderAssignees(enabled: boolean) {
  return useQuery({ queryKey: orderKeys.assignees(), queryFn: ordersApi.assignees, enabled })
}

export function useAssignOrder() {
  const changed = useOrderChanged()
  return useMutation({
    mutationFn: ({ id, assigneeId }: { id: number; assigneeId: number | null }) =>
      ordersApi.assign(id, assigneeId),
    onSuccess: changed,
  })
}

export function useAddOrderNote() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, body }: { id: number; body: string }) => ordersApi.addNote(id, body),
    onSuccess: (order) => queryClient.setQueryData(orderKeys.detail(order.id), order),
  })
}
