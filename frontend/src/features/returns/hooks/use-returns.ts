import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { productKeys } from '@/features/catalog/api/products-api'
import { customerKeys } from '@/features/customers/api/customers-api'
import { dashboardKeys } from '@/features/dashboard/api/dashboard-api'
import { reportKeys } from '@/features/reports/api/reports-api'
import { inventoryKeys } from '@/features/inventory/api/inventory-api'
import { orderKeys } from '@/features/orders/api/orders-api'
import { paymentKeys } from '@/features/payments/api/payments-api'
import type { ListQuery } from '@/lib/list-query'
import { returnKeys, returnsApi } from '../api/returns-api'
import type { OrderReturn, RestockDecision, ReturnRequestInput } from '../types'

export function useReturns(query: ListQuery) {
  return useQuery({
    queryKey: returnKeys.list(query),
    queryFn: () => returnsApi.list(query),
    placeholderData: keepPreviousData,
  })
}

export function useReturn(id: number) {
  return useQuery({ queryKey: returnKeys.detail(id), queryFn: () => returnsApi.get(id) })
}

/**
 * A Return step changes the Return, its Order (lines, ledger) and the payments list; a
 * restock changes stock, and a Refund the Customer's Lifetime value and the sales figures.
 */
function useReturnChanged() {
  const queryClient = useQueryClient()
  return (changed: OrderReturn) => {
    queryClient.setQueryData(returnKeys.detail(changed.id), changed)
    return Promise.all([
      queryClient.invalidateQueries({ queryKey: returnKeys.all }),
      queryClient.invalidateQueries({ queryKey: orderKeys.all }),
      queryClient.invalidateQueries({ queryKey: paymentKeys.all }),
      queryClient.invalidateQueries({ queryKey: inventoryKeys.all }),
      queryClient.invalidateQueries({ queryKey: productKeys.all }),
      queryClient.invalidateQueries({ queryKey: customerKeys.all }),
      queryClient.invalidateQueries({ queryKey: dashboardKeys.all }),
      queryClient.invalidateQueries({ queryKey: reportKeys.all }),
    ])
  }
}

export function useRequestReturn() {
  const changed = useReturnChanged()
  return useMutation({
    mutationFn: ({
      orderId,
      input,
      idempotencyKey,
    }: {
      orderId: number
      input: ReturnRequestInput
      idempotencyKey: string
    }) => returnsApi.request(orderId, input, idempotencyKey),
    onSuccess: changed,
  })
}

export function useDecideReturn() {
  const changed = useReturnChanged()
  return useMutation({
    mutationFn: ({
      id,
      decision,
      note,
    }: {
      id: number
      decision: 'approve' | 'reject'
      note: string
    }) => returnsApi.decide(id, decision, note),
    onSuccess: changed,
  })
}

export function useReceiveReturn() {
  const changed = useReturnChanged()
  return useMutation({ mutationFn: (id: number) => returnsApi.receive(id), onSuccess: changed })
}

export function useRestockReturn() {
  const changed = useReturnChanged()
  return useMutation({
    mutationFn: ({
      id,
      lines,
      idempotencyKey,
    }: {
      id: number
      lines: { line_id: number; decision: RestockDecision }[]
      idempotencyKey: string
    }) => returnsApi.restock(id, lines, idempotencyKey),
    onSuccess: changed,
  })
}

export function useRefundReturn() {
  const changed = useReturnChanged()
  return useMutation({
    mutationFn: ({
      id,
      input,
      idempotencyKey,
    }: {
      id: number
      input: { amount: string; refund_shipping: boolean }
      idempotencyKey: string
    }) => returnsApi.refund(id, input, idempotencyKey),
    onSuccess: changed,
  })
}
