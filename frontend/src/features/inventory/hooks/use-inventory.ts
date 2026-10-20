import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ListQuery } from '@/lib/list-query'
import { inventoryApi, inventoryKeys } from '../api/inventory-api'
import type {
  AdjustmentInput,
  ReceiptInput,
  TransferInput,
  TransferStep,
  WarehouseInput,
} from '../types'

export function useWarehouses(query: ListQuery) {
  return useQuery({
    queryKey: inventoryKeys.warehouses(query),
    queryFn: () => inventoryApi.warehouses(query),
    placeholderData: keepPreviousData,
  })
}

/** Every Warehouse, for filters and pickers (the store has a handful). */
export function useAllWarehouses() {
  return useWarehouses({
    search: '',
    filters: {},
    sort: { field: 'name', direction: 'asc' },
    page: 1,
    perPage: 100,
  })
}

export function useStockLevels(query: ListQuery, enabled = true) {
  return useQuery({
    queryKey: inventoryKeys.stockLevels(query),
    queryFn: () => inventoryApi.stockLevels(query),
    placeholderData: keepPreviousData,
    enabled,
  })
}

export function useStockMovements(query: ListQuery) {
  return useQuery({
    queryKey: inventoryKeys.movements(query),
    queryFn: () => inventoryApi.movements(query),
    placeholderData: keepPreviousData,
  })
}

export function useReceipts(query: ListQuery) {
  return useQuery({
    queryKey: inventoryKeys.receipts(query),
    queryFn: () => inventoryApi.receipts(query),
    placeholderData: keepPreviousData,
  })
}

export function useReceipt(id: number) {
  return useQuery({ queryKey: inventoryKeys.receipt(id), queryFn: () => inventoryApi.receipt(id) })
}

/** Saving a Warehouse or a Receipt changes stock screens and the Product pages' stock. */
function useInvalidateStock() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: inventoryKeys.all }),
      queryClient.invalidateQueries({ queryKey: ['products'] }),
    ])
}

export function useSaveWarehouse() {
  const invalidate = useInvalidateStock()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: WarehouseInput }) =>
      inventoryApi.saveWarehouse(id, input),
    onSuccess: invalidate,
  })
}

export function useCreateReceipt() {
  const invalidate = useInvalidateStock()
  return useMutation({
    mutationFn: ({ input, idempotencyKey }: { input: ReceiptInput; idempotencyKey: string }) =>
      inventoryApi.createReceipt(input, idempotencyKey),
    onSuccess: invalidate,
  })
}

export function useTransfers(query: ListQuery) {
  return useQuery({
    queryKey: inventoryKeys.transfers(query),
    queryFn: () => inventoryApi.transfers(query),
    placeholderData: keepPreviousData,
  })
}

export function useCreateAdjustment() {
  const invalidate = useInvalidateStock()
  return useMutation({
    mutationFn: ({ input, idempotencyKey }: { input: AdjustmentInput; idempotencyKey: string }) =>
      inventoryApi.createAdjustment(input, idempotencyKey),
    onSuccess: invalidate,
  })
}

export function useCreateTransfer() {
  const invalidate = useInvalidateStock()
  return useMutation({
    mutationFn: ({ input, idempotencyKey }: { input: TransferInput; idempotencyKey: string }) =>
      inventoryApi.createTransfer(input, idempotencyKey),
    onSuccess: invalidate,
  })
}

export function useChangeTransfer() {
  const invalidate = useInvalidateStock()
  return useMutation({
    mutationFn: ({ id, step }: { id: number; step: TransferStep }) =>
      inventoryApi.changeTransfer(id, step, crypto.randomUUID()),
    onSuccess: invalidate,
  })
}
