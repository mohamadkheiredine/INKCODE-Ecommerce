import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type {
  Receipt,
  ReceiptInput,
  StockLevel,
  StockMovement,
  StockTotals,
  Warehouse,
  WarehouseInput,
} from '../types'

export const inventoryKeys = {
  all: ['inventory'] as const,
  warehouses: (query: ListQuery) => [...inventoryKeys.all, 'warehouses', query] as const,
  stockLevels: (query: ListQuery) => [...inventoryKeys.all, 'stock-levels', query] as const,
  movements: (query: ListQuery) => [...inventoryKeys.all, 'movements', query] as const,
  receipts: (query: ListQuery) => [...inventoryKeys.all, 'receipts', query] as const,
  receipt: (id: number) => [...inventoryKeys.all, 'receipt', id] as const,
}

export type StockLevelsPage = ListPage<StockLevel> & {
  meta: ListPage<StockLevel>['meta'] & { totals: StockTotals }
}

export const inventoryApi = {
  async warehouses(query: ListQuery): Promise<ListPage<Warehouse>> {
    const { data } = await http.get<ListPage<Warehouse>>('/warehouses', {
      params: toApiParams(query),
    })
    return data
  },

  async saveWarehouse(id: number | null, input: WarehouseInput): Promise<Warehouse> {
    const { data } =
      id === null
        ? await http.post<{ data: Warehouse }>('/warehouses', input)
        : await http.put<{ data: Warehouse }>(`/warehouses/${id}`, input)
    return data.data
  },

  async stockLevels(query: ListQuery): Promise<StockLevelsPage> {
    const { data } = await http.get<StockLevelsPage>('/stock-levels', {
      params: toApiParams(query),
    })
    return data
  },

  async movements(query: ListQuery): Promise<ListPage<StockMovement>> {
    const { data } = await http.get<ListPage<StockMovement>>('/stock-movements', {
      params: toApiParams(query),
    })
    return data
  },

  async receipts(query: ListQuery): Promise<ListPage<Receipt>> {
    const { data } = await http.get<ListPage<Receipt>>('/receipts', {
      params: toApiParams(query),
    })
    return data
  },

  async receipt(id: number): Promise<Receipt> {
    const { data } = await http.get<{ data: Receipt }>(`/receipts/${id}`)
    return data.data
  },

  /** The key makes a retried submit (a lost answer, a double click) record the Receipt once. */
  async createReceipt(input: ReceiptInput, idempotencyKey: string): Promise<Receipt> {
    const { data } = await http.post<{ data: Receipt }>('/receipts', input, {
      headers: { 'Idempotency-Key': idempotencyKey },
    })
    return data.data
  },
}
