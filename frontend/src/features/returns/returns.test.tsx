import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import type { Order } from '@/features/orders/types'
import { apiError } from '@/test/msw/handlers'
import { aWarehouse, warehouseStore } from '@/test/msw/inventory'
import { listResponse } from '@/test/msw/lists'
import { anOrder, orderStore } from '@/test/msw/orders'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'
import type { OrderReturn } from './types'

const support: CurrentUser = {
  id: 5,
  name: 'Lina Saab',
  email: 'support@inkcode.test',
  role: 'support',
  permissions: [
    'dashboard.view',
    'orders.view',
    'returns.view',
    'returns.request',
    'returns.approve',
  ],
  preferences: { locale: 'en', theme: 'light' },
}

const finance: CurrentUser = {
  ...support,
  id: 6,
  role: 'finance',
  permissions: ['dashboard.view', 'orders.view', 'payments.view', 'refunds.issue', 'returns.view'],
}

const viewer: CurrentUser = {
  ...support,
  id: 9,
  role: 'viewer',
  permissions: ['dashboard.view', 'orders.view', 'returns.view'],
}

const STAMP = '2026-10-20T10:00:00+00:00'

function aReturn(overrides: Partial<OrderReturn> = {}): OrderReturn {
  return {
    id: 45,
    number: 'RET-2026-000045',
    status: 'requested',
    reason: 'damaged',
    note: 'Torn seam',
    decision_note: null,
    refund_shipping: false,
    refund_amount: null,
    order: {
      id: 900,
      number: 'ORD-2026-000900',
      currency_code: 'USD',
      customer: { id: 1, name: 'Rana Haddad' },
    },
    user: { id: 5, name: 'Lina Saab' },
    refund: null,
    units: 1,
    lines: [
      {
        id: 1,
        order_line_id: 9000,
        product_name: 'Classic Cotton Tee',
        sku: 'TEE-RED-M',
        options: [{ attribute: 'Color', value: 'Red' }],
        quantity: 1,
        ordered_quantity: 2,
        paid_amount: '18.90',
        restock_decision: null,
        decided_at: null,
      },
    ],
    refund_suggestion: { items: '18.90', shipping: '5.00', refundable: '72.25' },
    requested_at: STAMP,
    decided_at: null,
    received_at: null,
    completed_at: null,
    ...overrides,
  }
}

/** Serves one Return (and the list), answering each step with the next state. */
function returnStore(initial: OrderReturn[]) {
  const rows = initial.map((row) => ({ ...row }))
  const sent: { path: string; body: unknown; key: string | null }[] = []
  const find = (id: unknown) => rows.find((row) => row.id === Number(id))
  server.use(
    http.get('*/api/v1/returns', ({ request }) =>
      listResponse(rows, request, {
        searchIn: ['number'],
        defaultSort: '-requested_at',
        filters: { status: (row, value) => row.status === value },
      }),
    ),
    http.get('*/api/v1/returns/:id', ({ params }) => {
      const row = find(params.id)
      return row ? HttpResponse.json({ data: row }) : apiError(404, 'not_found', 'Not found')
    }),
    http.post('*/api/v1/returns/:id/:step', async ({ request, params }) => {
      const row = find(params.id)!
      const body =
        request.headers.get('content-length') === '0'
          ? null
          : await request.json().catch(() => null)
      sent.push({ path: String(params.step), body, key: request.headers.get('Idempotency-Key') })
      if (params.step === 'approve') Object.assign(row, { status: 'approved', decided_at: STAMP })
      if (params.step === 'reject') Object.assign(row, { status: 'rejected', decided_at: STAMP })
      if (params.step === 'receive') Object.assign(row, { status: 'received', received_at: STAMP })
      if (params.step === 'restock') {
        row.lines = row.lines!.map((line) => ({
          ...line,
          restock_decision: 'restock',
          decided_at: STAMP,
        }))
      }
      if (params.step === 'refund') {
        const { amount } = body as { amount: string }
        if (Number(amount) > 18.9) {
          return apiError(
            409,
            'refund_exceeds_cap',
            'The Refund for Return RET-2026-000045 can be at most 18.90.',
          )
        }
        Object.assign(row, { status: 'completed', refund_amount: amount, completed_at: STAMP })
      }
      return HttpResponse.json({ data: row })
    }),
  )
  return { rows, sent }
}

async function open(path: string, heading: string, user: CurrentUser = support) {
  signedInAs(user)
  warehouseStore([aWarehouse({ id: 1, name: 'Beirut Central', code: 'BEY-01' })])
  const view = renderApp(path)
  await screen.findByRole('heading', { name: heading, level: 1 }, { timeout: 10_000 })
  return view
}

describe('the Returns list', () => {
  it('lists Returns with their Order, Customer, reason and status, and filters them', async () => {
    returnStore([aReturn(), aReturn({ id: 46, number: 'RET-2026-000046', status: 'completed' })])
    const { user } = await open('/returns', 'Returns')

    const row = (await screen.findByText('RET-2026-000045')).closest('tr')!
    expect(within(row).getByText('Requested')).toBeInTheDocument()
    expect(within(row).getByText('Rana Haddad')).toBeInTheDocument()
    expect(within(row).getByText('Damaged')).toBeInTheDocument()

    await user.selectOptions(screen.getByRole('combobox', { name: 'Status' }), 'completed')
    await waitFor(() => expect(screen.queryByText('RET-2026-000045')).not.toBeInTheDocument())
    expect(screen.getByText('RET-2026-000046')).toBeInTheDocument()
  }, 20_000)

  it('starts empty with a way to the Delivered Orders', async () => {
    returnStore([])
    await open('/returns', 'Returns')

    expect(await screen.findByText('No Returns yet')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Go to Delivered Orders' })).toBeInTheDocument()
  })

  it('shows an error with a retry when the list fails', async () => {
    server.use(http.get('*/api/v1/returns', () => apiError(500, 'server_error', 'Boom')))
    await open('/returns', 'Returns')

    expect(await screen.findByRole('button', { name: /try again/i })).toBeInTheDocument()
  })
})

describe('one Return', () => {
  it('lets Support approve a Requested Return with a note', async () => {
    const { sent } = returnStore([aReturn()])
    const { user } = await open('/returns/45', 'RET-2026-000045')

    expect(screen.getByText('$18.90')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Approve' }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Note'), 'Photo checked')
    await user.click(within(dialog).getByRole('button', { name: 'Approve' }))

    expect(await screen.findByText('Return RET-2026-000045 approved.')).toBeInTheDocument()
    expect(sent[0]).toMatchObject({ path: 'approve', body: { note: 'Photo checked' } })
    expect(await screen.findAllByText('Approved')).not.toHaveLength(0)
  }, 20_000)

  it('suggests the exact Refund, refuses more than the cap and completes with a lower one', async () => {
    const { sent } = returnStore([
      aReturn({
        status: 'received',
        received_at: STAMP,
        lines: [{ ...aReturn().lines![0]!, restock_decision: 'restock', decided_at: STAMP }],
      }),
    ])
    const { user } = await open('/returns/45', 'RET-2026-000045', finance)

    const amount = await screen.findByLabelText('Refund amount')
    expect(amount).toHaveValue('18.90')
    await user.click(screen.getByLabelText('Also refund shipping'))
    expect(amount).toHaveValue('23.90')
    await user.click(screen.getByLabelText('Also refund shipping'))

    await user.clear(amount)
    await user.type(amount, '19.00')
    await user.click(screen.getByRole('button', { name: 'Complete with Refund' }))
    expect(await screen.findByText('The Refund can be at most $18.90.')).toBeInTheDocument()
    expect(sent).toHaveLength(0)

    await user.clear(amount)
    await user.type(amount, '15.00')
    await user.click(screen.getByRole('button', { name: 'Complete with Refund' }))
    expect(
      await screen.findByText('Return RET-2026-000045 completed with a Refund of $15.00.'),
    ).toBeInTheDocument()
    expect(sent[0]).toMatchObject({
      path: 'refund',
      body: { amount: '15.00', refund_shipping: false },
    })
    expect(sent[0]?.key).toBeTruthy()
  }, 20_000)

  it('shows the steps only to Roles that may take them', async () => {
    returnStore([aReturn()])
    await open('/returns/45', 'RET-2026-000045', viewer)

    expect(screen.getAllByText('Requested').length).toBeGreaterThan(0)
    expect(screen.queryByRole('button', { name: 'Approve' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Reject' })).not.toBeInTheDocument()
  })

  it('says when the Return does not exist', async () => {
    returnStore([])
    signedInAs(support)
    renderApp('/returns/999')

    expect(
      await screen.findByText(
        'This Return does not exist or was removed.',
        {},
        { timeout: 10_000 },
      ),
    ).toBeInTheDocument()
  })
})

describe('requesting a Return from a Delivered Order', () => {
  function deliveredOrder(): Order {
    const order = anOrder({ status: 'delivered', is_locked: true, allowed_moves: [] })
    order.lines = order.lines!.map((line) => ({
      ...line,
      shipped_quantity: 3,
      returned_quantity: 1,
    }))
    order.returns = []
    order.return_window_ends_at = '2999-01-01T00:00:00+00:00'
    return order
  }

  it('requests a Return for chosen units with a reason, and checks the quantity first', async () => {
    const order = deliveredOrder()
    orderStore([order])
    const sent: { body: unknown; key: string | null }[] = []
    server.use(
      http.post('*/api/v1/orders/:id/returns', async ({ request }) => {
        sent.push({ body: await request.json(), key: request.headers.get('Idempotency-Key') })
        return HttpResponse.json({ data: aReturn({ number: 'RET-2026-000001' }) }, { status: 201 })
      }),
    )
    const { user } = await open(`/orders/${order.id}`, order.number)

    await user.click(await screen.findByRole('button', { name: 'Request return' }))
    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText('2 units can be returned')).toBeInTheDocument()
    const quantity = within(dialog).getByLabelText('Units of TEE-RED-M to return')

    await user.click(within(dialog).getByRole('button', { name: 'Request return' }))
    expect(
      await within(dialog).findByText('Enter how many units of at least one item come back.'),
    ).toBeInTheDocument()

    await user.clear(quantity)
    await user.type(quantity, '3')
    await user.click(within(dialog).getByRole('button', { name: 'Request return' }))
    expect(
      await within(dialog).findByText('Return at most 2 units of an item.'),
    ).toBeInTheDocument()

    await user.clear(quantity)
    await user.type(quantity, '1')
    await user.selectOptions(within(dialog).getByLabelText('Reason'), 'wrong_item')
    await user.click(within(dialog).getByRole('button', { name: 'Request return' }))

    expect(await screen.findByText('Return RET-2026-000001 requested.')).toBeInTheDocument()
    expect(sent[0]).toMatchObject({
      body: {
        reason: 'wrong_item',
        note: null,
        lines: [{ line_id: order.lines![0]!.id, quantity: 1 }],
      },
    })
    expect(sent[0]?.key).toBeTruthy()
  }, 20_000)

  it('shows the API refusal when the Return window has closed', async () => {
    const order = deliveredOrder()
    orderStore([order])
    server.use(
      http.post('*/api/v1/orders/:id/returns', () =>
        apiError(
          409,
          'return_window_closed',
          'The Return window for Order ORD has closed (14 days after delivery).',
        ),
      ),
    )
    const { user } = await open(`/orders/${order.id}`, order.number)

    await user.click(await screen.findByRole('button', { name: 'Request return' }))
    const dialog = await screen.findByRole('dialog')
    await user.clear(within(dialog).getByLabelText('Units of TEE-RED-M to return'))
    await user.type(within(dialog).getByLabelText('Units of TEE-RED-M to return'), '1')
    await user.click(within(dialog).getByRole('button', { name: 'Request return' }))

    expect(await within(dialog).findByText(/has closed/)).toBeInTheDocument()
  }, 20_000)

  it('offers no request once the Return window has closed', async () => {
    const order = deliveredOrder()
    order.return_window_ends_at = '2000-01-01T00:00:00+00:00'
    orderStore([order])
    await open(`/orders/${order.id}`, order.number)

    expect(await screen.findByText('The Return window has closed.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Request return' })).not.toBeInTheDocument()
  })
})
