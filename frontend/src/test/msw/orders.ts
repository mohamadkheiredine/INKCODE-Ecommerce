import { HttpResponse, http } from 'msw/http'
import type {
  Order,
  OrderInput,
  OrderLineInput,
  OrderQuote,
  SellableVariant,
  StockShortage,
} from '@/features/orders/types'
import type { Amount } from '@/lib/format'
import { apiError } from './handlers'
import { listResponse } from './lists'
import { server } from './server'

let nextId = 900

const fromCents = (cents: number) => (cents / 100).toFixed(2) as Amount
const STAMP = '2026-10-20T09:00:00+00:00'

const ADDRESS = {
  recipient_name: 'Rana Haddad',
  phone: '+961 70 123 456',
  country_code: 'LB',
  city: 'Beirut',
  line1: 'Hamra Street 12',
  line2: null,
  postal_code: '1103',
}

export function anOrder(overrides: Partial<Order> = {}): Order {
  const id = overrides.id ?? nextId++
  return {
    id,
    number: `ORD-2026-${String(id).padStart(6, '0')}`,
    status: 'pending',
    placed_at: STAMP,
    customer: { id: 1, name: 'Rana Haddad', email: 'rana@example.com', phone: null },
    warehouse: { id: 1, name: 'Beirut Central', code: 'BEY-01' },
    shipping_method: { id: 3, name: 'Standard' },
    billing_address: ADDRESS,
    shipping_address: ADDRESS,
    currency_code: 'USD',
    vat_rate: '5.00',
    items_total: '59.97',
    discount_total: '0.00',
    vat_total: '3.00',
    shipping_total: '5.00',
    total: '67.97',
    notes: null,
    user: { id: 4, name: 'Sami Nassar' },
    units: 3,
    lines: [
      {
        id: id * 10,
        variant_id: 11,
        product_id: 2,
        product_name: 'Classic Cotton Tee',
        sku: 'TEE-RED-M',
        options: [{ attribute: 'Color', value: 'Red' }],
        quantity: 3,
        unit_price: '19.99',
        discount_amount: '0.00',
        taxable: true,
        vat_rate: '5.00',
        vat_amount: '3.00',
        line_total: '62.97',
      },
    ],
    status_history: [
      {
        id: id * 10,
        from_status: null,
        to_status: 'pending',
        note: null,
        user: { id: 4, name: 'Sami Nassar' },
        created_at: STAMP,
      },
    ],
    created_at: STAMP,
    ...overrides,
  }
}

export function aSellableVariant(overrides: Partial<SellableVariant> = {}): SellableVariant {
  const id = overrides.id ?? nextId++
  return {
    id,
    sku: `SKU-${id}`,
    options: [],
    price: '19.99',
    product: { id: 2, name: 'Classic Cotton Tee' },
    available: 12,
    ...overrides,
  }
}

type StoreOptions = {
  variants?: SellableVariant[]
  /** Lines the next confirmation is refused for (409 insufficient_stock). */
  shortages?: StockShortage[]
}

/**
 * The Orders API in memory: the list (status and Customer filters), one Order, creating,
 * confirming (or refusing with the given shortages), cancelling, the quote (5% VAT per
 * line, $5 shipping once a method is chosen) and the Variant search. It records every
 * request body with its Idempotency-Key.
 */
export function orderStore(initial: Order[] = [], options: StoreOptions = {}) {
  const orders = [...initial]
  const requests: { path: string; body: unknown; key: string | null }[] = []
  const variants = options.variants ?? []
  const shortages = options.shortages ?? []

  const record = async (path: string, request: Request) => {
    const text = await request.text()
    requests.push({
      path,
      body: text ? JSON.parse(text) : null,
      key: request.headers.get('Idempotency-Key'),
    })
  }
  const find = (id: unknown) => orders.find((order) => order.id === Number(id))
  const move = (order: Order, to: Order['status'], note: string | null = null) => {
    order.status = to
    order.status_history = [
      ...(order.status_history ?? []),
      {
        id: nextId++,
        from_status: order.status_history?.at(-1)?.to_status ?? null,
        to_status: to,
        note,
        user: { id: 4, name: 'Sami Nassar' },
        created_at: STAMP,
      },
    ]
  }

  server.use(
    http.get('*/api/v1/orders', ({ request }) =>
      listResponse(
        orders.map((order) => ({ ...order, customer_name: order.customer.name })),
        request,
        {
          searchIn: ['number', 'customer_name'],
          defaultSort: '-placed_at',
          filters: {
            status: (row, value) => row.status === value,
            customer: (row, value) => row.customer.id === Number(value),
          },
        },
      ),
    ),
    http.get('*/api/v1/orders/sellable-variants', ({ request }) => {
      const search = new URL(request.url).searchParams.get('search')?.toLowerCase() ?? ''
      return HttpResponse.json({
        data: variants.filter((variant) =>
          `${variant.sku} ${variant.product.name}`.toLowerCase().includes(search),
        ),
      })
    }),
    http.post('*/api/v1/orders/quote', async ({ request }) => {
      const body = (await request.json()) as {
        lines: OrderLineInput[]
        shipping_method_id: number | null
      }
      const cents = (amount: number) => Math.round(amount * 100)
      const lines = body.lines.map((line) => {
        const variant = variants.find((item) => item.id === line.variant_id)!
        const amount = cents(Number(variant.price) * line.quantity)
        const vat = Math.round(amount * 0.05)
        return {
          variant_id: variant.id,
          product_name: variant.product.name,
          sku: variant.sku,
          options: variant.options,
          quantity: line.quantity,
          unit_price: variant.price,
          discount_amount: '0.00',
          taxable: true,
          vat_rate: '5.00',
          vat_amount: fromCents(vat),
          line_total: fromCents(amount + vat),
        } satisfies OrderQuote['lines'][number]
      })
      const items = lines.reduce(
        (sum, line) => sum + cents(Number(line.unit_price) * line.quantity),
        0,
      )
      const vat = lines.reduce((sum, line) => sum + cents(Number(line.vat_amount)), 0)
      const shipping = body.shipping_method_id ? 500 : 0
      return HttpResponse.json({
        data: {
          vat_rate: '5.00',
          items_total: fromCents(items),
          discount_total: '0.00',
          vat_total: fromCents(vat),
          shipping_total: fromCents(shipping),
          total: fromCents(items + vat + shipping),
          lines,
          warehouses: [
            {
              id: 2,
              name: 'Tripoli North',
              code: 'TRI-01',
              is_sufficient: false,
              shortages: [
                {
                  variant_id: lines[0]?.variant_id ?? 0,
                  sku: lines[0]?.sku ?? '',
                  requested: 1,
                  available: 0,
                },
              ],
            },
            { id: 1, name: 'Beirut Central', code: 'BEY-01', is_sufficient: true, shortages: [] },
          ].sort((a, b) => Number(b.is_sufficient) - Number(a.is_sufficient)),
        } satisfies OrderQuote,
      })
    }),
    http.post('*/api/v1/orders', async ({ request }) => {
      await record('create', request.clone())
      const input = (await request.json()) as OrderInput
      const order = anOrder({ notes: input.notes || null })
      orders.push(order)
      return HttpResponse.json({ data: order }, { status: 201 })
    }),
    http.get('*/api/v1/orders/:id', ({ params }) => {
      const order = find(params.id)
      return order
        ? HttpResponse.json({ data: order })
        : apiError(404, 'not_found', 'The requested resource was not found.')
    }),
    http.post('*/api/v1/orders/:id/confirm', async ({ request, params }) => {
      await record('confirm', request)
      const order = find(params.id)!
      if (shortages.length > 0) {
        const response = apiError(
          409,
          'insufficient_stock',
          'Not enough Available stock in Beirut Central.',
        )
        const body = (await response.json()) as Record<string, unknown>
        return HttpResponse.json({ ...body, details: { lines: shortages } }, { status: 409 })
      }
      move(order, 'confirmed')
      return HttpResponse.json({ data: order })
    }),
    http.post('*/api/v1/orders/:id/cancel', async ({ request, params }) => {
      await record('cancel', request.clone())
      const { note } = (await request.json()) as { note: string | null }
      const order = find(params.id)!
      move(order, 'cancelled', note)
      return HttpResponse.json({ data: order })
    }),
  )

  return { orders, requests }
}
