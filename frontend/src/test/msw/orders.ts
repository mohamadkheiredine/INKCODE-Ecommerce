import { HttpResponse, http } from 'msw/http'
import type {
  Order,
  OrderInput,
  OrderLineInput,
  OrderQuote,
  SellableVariant,
  Shipment,
  ShipmentInput,
  StockShortage,
  TimelineEvent,
  LineCancellation,
  OrderUpdateInput,
} from '@/features/orders/types'
import type { Amount } from '@/lib/format'
import { apiError } from './handlers'
import { listResponse } from './lists'
import { server } from './server'

let nextId = 900

const fromCents = (cents: number) => (cents / 100).toFixed(2) as Amount
const STAMP = '2026-10-20T09:00:00+00:00'

/** The Users an Order may be assigned to. */
export const ASSIGNEES = [
  { id: 4, name: 'Sami Nassar' },
  { id: 7, name: 'Lina Operations' },
]

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
  const order: Order = {
    id,
    number: `ORD-2026-${String(id).padStart(6, '0')}`,
    status: 'pending',
    is_locked: false,
    allowed_moves: ['confirmed', 'cancelled'],
    placed_at: STAMP,
    customer: { id: 1, name: 'Rana Haddad', email: 'rana@example.com', phone: null },
    warehouse: { id: 1, name: 'Beirut Central', code: 'BEY-01' },
    shipping_method: { id: 3, name: 'Standard' },
    coupon: null,
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
    assignee: null,
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
        cancelled_quantity: 0,
        shipped_quantity: 0,
        unit_price: '19.99',
        discount_amount: '0.00',
        coupon_eligible: false,
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
    shipments: [],
    fulfillment: { status: 'unfulfilled', shipped_units: 0, units: 3 },
    created_at: STAMP,
    ...overrides,
  }
  if (!overrides.allowed_moves) withStatus(order, order.status)
  return { ...order, timeline: overrides.timeline ?? timelineOf(order) }
}

const timelineOf = (order: Order): TimelineEvent[] =>
  (order.status_history ?? []).map((change) => ({ type: 'status' as const, ...change }))

/** What the API derives from the status: the Order flow's next moves and the lock. */
const FLOW: Order['status'][] = [
  'pending',
  'confirmed',
  'preparing',
  'ready_for_shipping',
  'shipped',
  'delivered',
]
function withStatus(order: Order, status: Order['status']) {
  const next = FLOW[FLOW.indexOf(status) + 1]
  const cancellable = ['pending', 'confirmed', 'preparing', 'ready_for_shipping'].includes(status)
  order.status = status
  order.is_locked = !['pending', 'confirmed'].includes(status)
  order.allowed_moves = [
    ...(status !== 'cancelled' && next ? [next] : []),
    ...(cancellable ? (['cancelled'] as const) : []),
  ]
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
    withStatus(order, to)
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
    order.timeline = [
      ...(order.timeline ?? []),
      { type: 'status', ...order.status_history.at(-1)! },
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
    http.get('*/api/v1/orders/assignees', () => HttpResponse.json({ data: ASSIGNEES })),
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
        coupon_code: string | null
      }
      // SAVE10 takes 10% off every line; any other code is unknown.
      const coupon: OrderQuote['coupon'] =
        body.coupon_code === null
          ? null
          : body.coupon_code === 'SAVE10'
            ? { code: 'SAVE10', is_valid: true, type: 'percentage', value: '10.00' }
            : {
                code: body.coupon_code,
                is_valid: false,
                reason: 'not_found',
                message: `There is no Coupon with the code ${body.coupon_code}.`,
              }
      const rate = coupon?.is_valid ? 0.1 : 0
      const cents = (amount: number) => Math.round(amount * 100)
      const lines = body.lines.map((line) => {
        const variant = variants.find((item) => item.id === line.variant_id)!
        const gross = cents(Number(variant.price) * line.quantity)
        const discount = Math.round(gross * rate)
        const amount = gross - discount
        const vat = Math.round(amount * 0.05)
        return {
          variant_id: variant.id,
          product_name: variant.product.name,
          sku: variant.sku,
          options: variant.options,
          quantity: line.quantity,
          unit_price: variant.price,
          discount_amount: fromCents(discount),
          coupon_eligible: rate > 0,
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
      const discounts = lines.reduce((sum, line) => sum + cents(Number(line.discount_amount)), 0)
      const vat = lines.reduce((sum, line) => sum + cents(Number(line.vat_amount)), 0)
      const shipping = body.shipping_method_id ? 500 : 0
      return HttpResponse.json({
        data: {
          vat_rate: '5.00',
          items_total: fromCents(items),
          discount_total: fromCents(discounts),
          vat_total: fromCents(vat),
          shipping_total: fromCents(shipping),
          total: fromCents(items - discounts + vat + shipping),
          lines,
          coupon,
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
    http.post('*/api/v1/orders/:id/advance', async ({ request, params }) => {
      await record('advance', request.clone())
      const { status } = (await request.json()) as { status: Order['status'] }
      const order = find(params.id)!
      if (!order.allowed_moves.includes(status)) {
        const response = apiError(
          409,
          'invalid_transition',
          `Order ${order.number} cannot become ${status}.`,
        )
        const body = (await response.json()) as Record<string, unknown>
        return HttpResponse.json(
          { ...body, details: { allowed: order.allowed_moves } },
          { status: 409 },
        )
      }
      move(order, status)
      return HttpResponse.json({ data: order })
    }),
    http.put('*/api/v1/orders/:id', async ({ request, params }) => {
      await record('update', request.clone())
      const input = (await request.json()) as OrderUpdateInput
      const order = find(params.id)!
      if (order.is_locked) {
        return apiError(409, 'order_locked', `Order ${order.number} is locked.`)
      }
      order.lines = (order.lines ?? []).map((line) => ({
        ...line,
        quantity: input.lines.find((item) => item.variant_id === line.variant_id)?.quantity ?? 0,
      }))
      return HttpResponse.json({ data: order })
    }),
    http.post('*/api/v1/orders/:id/cancel-lines', async ({ request, params }) => {
      await record('cancel-lines', request.clone())
      const input = (await request.json()) as { lines: LineCancellation[]; note: string | null }
      const order = find(params.id)!
      order.lines = (order.lines ?? []).map((line) => {
        const cancelled = input.lines.find((item) => item.line_id === line.id)?.quantity ?? 0
        return {
          ...line,
          quantity: line.quantity - cancelled,
          cancelled_quantity: line.cancelled_quantity + cancelled,
        }
      })
      return HttpResponse.json({ data: order })
    }),
    http.post('*/api/v1/orders/:id/shipments', async ({ request, params }) => {
      await record('shipments', request.clone())
      const input = (await request.json()) as ShipmentInput
      const order = find(params.id)!
      const shipment: Shipment = {
        id: nextId++,
        number: `${order.number}-${(order.shipments ?? []).length + 1}`,
        status: 'shipped',
        carrier: { id: input.carrier_id, name: 'Aramex' },
        tracking_number: input.tracking_number,
        tracking_url: `https://track.example/?n=${input.tracking_number}`,
        shipped_at: STAMP,
        delivered_at: null,
        lines: input.lines.map((line) => ({
          order_line_id: line.line_id,
          quantity: line.quantity,
        })),
        status_history: [
          { id: nextId++, from_status: null, to_status: 'shipped', user: null, created_at: STAMP },
        ],
      }
      order.shipments = [...(order.shipments ?? []), shipment]
      order.lines = (order.lines ?? []).map((line) => ({
        ...line,
        shipped_quantity:
          line.shipped_quantity +
          (input.lines.find((item) => item.line_id === line.id)?.quantity ?? 0),
      }))
      const units = order.lines.reduce((sum, line) => sum + line.quantity, 0)
      const shipped = order.lines.reduce((sum, line) => sum + line.shipped_quantity, 0)
      order.fulfillment = {
        status: shipped < units ? 'partially_shipped' : 'shipped',
        shipped_units: shipped,
        units,
      }
      if (shipped === units) move(order, 'shipped')
      return HttpResponse.json({ data: order })
    }),
    http.post('*/api/v1/orders/:id/shipments/:shipment/deliver', async ({ request, params }) => {
      await record('deliver', request)
      const order = find(params.id)!
      order.shipments = (order.shipments ?? []).map((shipment) =>
        shipment.id === Number(params.shipment)
          ? { ...shipment, status: 'delivered', delivered_at: STAMP }
          : shipment,
      )
      if (order.status === 'shipped' && order.shipments.every((s) => s.status === 'delivered')) {
        move(order, 'delivered')
        order.fulfillment = { ...order.fulfillment!, status: 'delivered' }
      }
      return HttpResponse.json({ data: order })
    }),
    http.put('*/api/v1/orders/:id/assignee', async ({ request, params }) => {
      await record('assignee', request.clone())
      const { assignee_id } = (await request.json()) as { assignee_id: number | null }
      const order = find(params.id)!
      order.assignee = ASSIGNEES.find((user) => user.id === assignee_id) ?? null
      return HttpResponse.json({ data: order })
    }),
    http.post('*/api/v1/orders/:id/notes', async ({ request, params }) => {
      await record('note', request.clone())
      const { body } = (await request.json()) as { body: string }
      const order = find(params.id)!
      order.timeline = [
        ...(order.timeline ?? []),
        {
          type: 'note',
          id: nextId++,
          body,
          user: { id: 4, name: 'Sami Nassar' },
          created_at: STAMP,
        },
      ]
      return HttpResponse.json({ data: order }, { status: 201 })
    }),
  )

  return { orders, requests }
}
