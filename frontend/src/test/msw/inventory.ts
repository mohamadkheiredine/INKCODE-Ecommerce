import { HttpResponse, http } from 'msw/http'
import type {
  Receipt,
  ReceiptInput,
  StockLevel,
  Warehouse,
  WarehouseInput,
} from '@/features/inventory/types'
import { apiError } from './handlers'
import { listResponse } from './lists'
import { server } from './server'

let nextId = 700
const STAMP = '2026-10-01T09:00:00+00:00'

export function aWarehouse(overrides: Partial<Warehouse> = {}): Warehouse {
  const id = overrides.id ?? nextId++
  return {
    id,
    name: `Warehouse ${id}`,
    code: `WH-${id}`,
    address: 'Port Road, Beirut',
    is_active: true,
    on_hand: 0,
    created_at: STAMP,
    updated_at: STAMP,
    ...overrides,
  }
}

export function aStockLevel(overrides: Partial<StockLevel> = {}): StockLevel {
  const id = nextId++
  return {
    variant: { id, sku: `SKU-${id}`, options: [] },
    product: { id: 1, name: 'Classic Cotton Tee' },
    warehouse: { id: 1, name: 'Beirut Central', code: 'BEY-01' },
    on_hand: 10,
    reserved: 0,
    available: 10,
    average_cost: '5.0000',
    stock_value: '50.00',
    low_stock_threshold: 5,
    is_low_stock: false,
    ...overrides,
  }
}

/**
 * The Warehouses API in memory: the list convention, and saves that refuse a taken code
 * with 422 like the real one.
 */
export function warehouseStore(initial: Warehouse[] = []) {
  const warehouses = [...initial]
  const save = (input: WarehouseInput, existing?: Warehouse): Response => {
    if (warehouses.some((w) => w !== existing && w.code === input.code)) {
      return apiError(422, 'validation_failed', 'The code has already been taken.', {
        code: ['The code has already been taken.'],
      })
    }
    const warehouse = existing ? Object.assign(existing, input) : aWarehouse({ ...input })
    if (!existing) warehouses.push(warehouse)
    return HttpResponse.json({ data: warehouse }, { status: existing ? 200 : 201 })
  }

  server.use(
    http.get('*/api/v1/warehouses', ({ request }) =>
      listResponse(warehouses, request, {
        searchIn: ['name', 'code'],
        defaultSort: 'name',
        filters: { status: (row, value) => row.is_active === (value === 'active') },
      }),
    ),
    http.post('*/api/v1/warehouses', async ({ request }) =>
      save((await request.json()) as WarehouseInput),
    ),
    http.put('*/api/v1/warehouses/:id', async ({ request, params }) =>
      save(
        (await request.json()) as WarehouseInput,
        warehouses.find((w) => w.id === Number(params.id)),
      ),
    ),
  )
  return warehouses
}

/** Stock levels with their totals, filtered by Warehouse and search like the API. */
export function stockLevelStore(levels: StockLevel[]) {
  server.use(
    http.get('*/api/v1/stock-levels', async ({ request }) => {
      const params = new URL(request.url).searchParams
      const warehouse = params.get('filter[warehouse]')
      const search = params.get('search')?.toLowerCase() ?? ''
      const kept = levels.filter(
        (level) =>
          (!warehouse || level.warehouse.id === Number(warehouse)) &&
          `${level.variant.sku} ${level.product.name}`.toLowerCase().includes(search),
      )
      const page = (await listResponse(
        kept.map((level) => ({
          ...level,
          product_name: level.product.name,
          searchable: `${level.variant.sku} ${level.product.name}`,
        })),
        request,
        { searchIn: ['searchable'], defaultSort: 'product_name' },
      ).json()) as { meta: Record<string, unknown> }
      const totals = {
        on_hand: kept.reduce((sum, level) => sum + level.on_hand, 0),
        stock_value: kept.reduce((sum, level) => sum + Number(level.stock_value), 0).toFixed(2),
      }
      return HttpResponse.json({ ...page, meta: { ...page.meta, totals } })
    }),
  )
}

/** Records the Receipts posted, with their Idempotency-Key, and answers like the API. */
export function receiptStore() {
  const posted: { input: ReceiptInput; key: string | null }[] = []
  server.use(
    http.post('*/api/v1/receipts', async ({ request }) => {
      const input = (await request.json()) as ReceiptInput
      posted.push({ input, key: request.headers.get('Idempotency-Key') })
      const receipt: Receipt = {
        id: 42,
        number: 'RCV-2026-000042',
        warehouse: { id: input.warehouse_id, name: 'Beirut Central', code: 'BEY-01' },
        supplier_reference: input.supplier_reference,
        notes: input.notes || null,
        user: { id: 3, name: 'Maya Haddad' },
        lines: [],
        created_at: STAMP,
      }
      return HttpResponse.json({ data: receipt }, { status: 201 })
    }),
    http.get('*/api/v1/receipts/42', () =>
      HttpResponse.json({
        data: {
          id: 42,
          number: 'RCV-2026-000042',
          warehouse: { id: 1, name: 'Beirut Central', code: 'BEY-01' },
          supplier_reference: posted.at(-1)?.input.supplier_reference ?? '',
          notes: null,
          user: null,
          lines: [],
          created_at: STAMP,
        } satisfies Receipt,
      }),
    ),
  )
  return posted
}
