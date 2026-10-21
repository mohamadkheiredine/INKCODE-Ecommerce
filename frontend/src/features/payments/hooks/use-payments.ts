import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { orderKeys } from '@/features/orders/api/orders-api'
import type { ListQuery } from '@/lib/list-query'
import { type LedgerWrite, paymentKeys, paymentsApi } from '../api/payments-api'
import type { PaymentInput, RefundInput } from '../types'

export function usePayments(query: ListQuery) {
  return useQuery({
    queryKey: paymentKeys.list(query),
    queryFn: () => paymentsApi.list(query),
    placeholderData: keepPreviousData,
  })
}

export function useReconciliation(query: ListQuery) {
  return useQuery({
    queryKey: paymentKeys.reconciliation(query),
    queryFn: () => paymentsApi.reconciliation(query),
    placeholderData: keepPreviousData,
  })
}

/** A ledger write changes the Order, the payments list and reconciliation together. */
function useLedgerChanged() {
  const queryClient = useQueryClient()
  return ({ order }: LedgerWrite) => {
    queryClient.setQueryData(orderKeys.detail(order.id), order)
    return Promise.all([
      queryClient.invalidateQueries({ queryKey: orderKeys.all }),
      queryClient.invalidateQueries({ queryKey: paymentKeys.all }),
    ])
  }
}

export function useRecordPayment() {
  const changed = useLedgerChanged()
  return useMutation({
    mutationFn: ({
      orderId,
      input,
      idempotencyKey,
    }: {
      orderId: number
      input: PaymentInput
      idempotencyKey: string
    }) => paymentsApi.record(orderId, input, idempotencyKey),
    onSuccess: changed,
  })
}

export function useIssueRefund() {
  const changed = useLedgerChanged()
  return useMutation({
    mutationFn: ({
      orderId,
      input,
      idempotencyKey,
    }: {
      orderId: number
      input: RefundInput
      idempotencyKey: string
    }) => paymentsApi.refund(orderId, input, idempotencyKey),
    onSuccess: changed,
  })
}
