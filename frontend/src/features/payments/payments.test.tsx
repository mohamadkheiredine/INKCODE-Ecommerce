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
import type { Payment, ReconciliationRow } from './types'

const finance: CurrentUser = {
  id: 6,
  name: 'Nadia Farouk',
  email: 'finance@inkcode.test',
  role: 'finance',
  permissions: [
    'dashboard.view',
    'orders.view',
    'payments.view',
    'payments.record',
    'refunds.issue',
  ],
  preferences: { locale: 'en', theme: 'light' },
}

const operations: CurrentUser = {
  ...finance,
  id: 7,
  role: 'operations',
  permissions: ['dashboard.view', 'orders.view', 'orders.update', 'orders.cancel', 'payments.view'],
}

const inventory: CurrentUser = {
  ...finance,
  id: 8,
  role: 'inventory',
  permissions: ['dashboard.view', 'orders.view'],
}

const STAMP = '2026-10-20T10:00:00+00:00'

function aPayment(overrides: Partial<Payment> = {}): Payment {
  return {
    id: 1,
    kind: 'payment',
    method: 'manual',
    amount: '20.00',
    status: 'succeeded',
    gateway_reference: null,
    note: null,
    idempotency_key: null,
    user: { id: 6, name: 'Nadia Farouk' },
    order: {
      id: 900,
      number: 'ORD-2026-000900',
      currency_code: 'USD',
      payment_status: 'partially_paid',
    },
    created_at: STAMP,
    ...overrides,
  }
}

function paymentsStore(rows: Payment[]) {
  server.use(
    http.get('*/api/v1/payments', ({ request }) =>
      listResponse(rows, request, {
        searchIn: ['gateway_reference'],
        defaultSort: '-created_at',
        filters: { status: (row, value) => row.status === value },
      }),
    ),
  )
}

/** The Order after a ledger write, as the API answers it. */
function withLedger(order: Order, payments: Payment[], values: Partial<Order>): Order {
  return {
    ...order,
    ...values,
    payments,
    timeline: [
      ...(order.timeline ?? []),
      ...payments.map((p) => ({ type: 'payment' as const, ...p })),
    ],
  }
}

async function open(path: string, heading: string, user: CurrentUser = finance) {
  signedInAs(user)
  warehouseStore([aWarehouse({ id: 1, name: 'Beirut Central', code: 'BEY-01' })])
  const view = renderApp(path)
  await screen.findByRole('heading', { name: heading, level: 1 }, { timeout: 10_000 })
  return view
}

describe('the payments list', () => {
  it('lists every ledger row with its Order, method and outcome', async () => {
    paymentsStore([
      aPayment({ id: 1, amount: '20.00' }),
      aPayment({
        id: 2,
        method: 'card',
        status: 'failed',
        amount: '15.00',
        gateway_reference: 'mock_ch_abc',
      }),
    ])
    const { user } = await open('/payments', 'Payments')

    const failed = (await screen.findByText('mock_ch_abc')).closest('tr')!
    expect(within(failed).getByText('Failed')).toBeInTheDocument()
    expect(within(failed).getByText('Card')).toBeInTheDocument()

    await user.selectOptions(screen.getByRole('combobox', { name: 'Outcome' }), 'failed')
    await waitFor(() => expect(screen.queryByText('$20.00')).not.toBeInTheDocument())
  }, 20_000)

  it('starts empty with a way to the Orders', async () => {
    paymentsStore([])
    await open('/payments', 'Payments')

    expect(await screen.findByText('No payments yet')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Go to Orders' })).toBeInTheDocument()
  })

  it('shows an error with a retry when the list fails', async () => {
    server.use(http.get('*/api/v1/payments', () => apiError(500, 'server_error', 'Boom')))
    await open('/payments', 'Payments')

    expect(await screen.findByRole('button', { name: /try again/i })).toBeInTheDocument()
  })

  it('reconciles Order totals with ledger totals', async () => {
    const row: ReconciliationRow = {
      id: 901,
      number: 'ORD-2026-000901',
      status: 'confirmed',
      payment_status: 'partially_paid',
      placed_at: STAMP,
      customer: { id: 1, name: 'Rana Haddad' },
      currency_code: 'USD',
      total: '67.97',
      amount_owed: '67.97',
      ledger_total: '20.00',
      difference: '-47.97',
    }
    server.use(
      http.get('*/api/v1/payments/reconciliation', ({ request }) =>
        listResponse([row], request, { searchIn: ['number'], defaultSort: '-placed_at' }),
      ),
    )
    await open('/payments/reconciliation', 'Payments')

    const line = (await screen.findByText('ORD-2026-000901')).closest('tr')!
    expect(within(line).getByText('Partially paid')).toBeInTheDocument()
    expect(within(line).getByText('-$47.97')).toBeInTheDocument()
  })
})

describe("an Order's Payment ledger", () => {
  it('records a Manual payment with an Idempotency-Key and shows the new Payment status', async () => {
    const order = anOrder({ status: 'confirmed' })
    orderStore([order])
    const sent: { body: unknown; key: string | null }[] = []
    server.use(
      http.post('*/api/v1/orders/:id/payments', async ({ request }) => {
        sent.push({ body: await request.json(), key: request.headers.get('Idempotency-Key') })
        const payment = aPayment({ id: 5, amount: '67.97', order: undefined })
        // The stored Order changes too, so the refetch after the write sees the new ledger.
        Object.assign(
          order,
          withLedger(order, [payment], {
            payment_status: 'paid',
            captured_amount: '67.97',
            amount_due: '0.00',
          }),
        )
        return HttpResponse.json({ data: order, payment }, { status: 201 })
      }),
    )
    const { user } = await open(`/orders/${order.id}`, order.number)

    expect(screen.getByText('Nothing recorded yet')).toBeInTheDocument()
    await user.click(screen.getAllByRole('button', { name: 'Record payment' })[0]!)
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Amount'), '67.97')
    await user.click(within(dialog).getByRole('button', { name: 'Record payment' }))

    expect(await screen.findByText('Payment of $67.97 recorded')).toBeInTheDocument()
    expect(sent[0]).toMatchObject({ body: { method: 'manual', amount: '67.97', note: null } })
    expect(sent[0]?.key).toBeTruthy()
    expect(await screen.findByText('Paid')).toBeInTheDocument()
    expect(screen.getByText('Payment of $67.97 · Manual')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Record payment' })).not.toBeInTheDocument()
  }, 20_000)

  it('refuses an amount above what is still due before sending it', async () => {
    const order = anOrder({ status: 'confirmed' })
    orderStore([order])
    let calls = 0
    server.use(
      http.post('*/api/v1/orders/:id/payments', () => {
        calls++
        return HttpResponse.json({}, { status: 500 })
      }),
    )
    const { user } = await open(`/orders/${order.id}`, order.number)

    await user.click(screen.getAllByRole('button', { name: 'Record payment' })[0]!)
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Amount'), '70')
    await user.click(within(dialog).getByRole('button', { name: 'Record payment' }))

    expect(
      await within(dialog).findByText('A payment can be at most $67.97, the amount still due.'),
    ).toBeInTheDocument()
    expect(calls).toBe(0)
  })

  it('charges a Card through the test option only and reports a decline', async () => {
    const order = anOrder({ status: 'confirmed' })
    orderStore([order])
    let body: Record<string, unknown> = {}
    server.use(
      http.post('*/api/v1/orders/:id/payments', async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        const payment = aPayment({ id: 6, method: 'card', status: 'failed', amount: '10.00' })
        Object.assign(order, withLedger(order, [payment], {}))
        return HttpResponse.json({ data: order, payment }, { status: 201 })
      }),
    )
    const { user } = await open(`/orders/${order.id}`, order.number)

    await user.click(screen.getAllByRole('button', { name: 'Record payment' })[0]!)
    const dialog = await screen.findByRole('dialog')
    await user.selectOptions(within(dialog).getByLabelText('Payment method'), 'card')
    await user.click(within(dialog).getByLabelText('Decline'))
    await user.type(within(dialog).getByLabelText('Amount'), '10')
    await user.click(within(dialog).getByRole('button', { name: 'Record payment' }))

    expect(await screen.findByText('The Card payment of $10.00 was declined')).toBeInTheDocument()
    expect(body).toEqual({ method: 'card', amount: '10', note: null, card_outcome: 'decline' })
    expect(await screen.findByText('Declined Card payment of $10.00')).toBeInTheDocument()
  }, 20_000)

  it('shows the refusal when a Refund exceeds the Captured amount', async () => {
    const payment = aPayment({ amount: '67.97', order: undefined })
    const order = withLedger(anOrder({ status: 'confirmed' }), [payment], {
      payment_status: 'paid',
      captured_amount: '67.97',
      amount_due: '0.00',
    })
    orderStore([order])
    server.use(
      http.post('*/api/v1/orders/:id/refunds', () =>
        apiError(409, 'refund_exceeds_captured', 'Order can be refunded at most 60.00.'),
      ),
    )
    const { user } = await open(`/orders/${order.id}`, order.number)

    await user.click(screen.getByRole('button', { name: 'Issue Refund' }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Amount'), '10')
    await user.click(within(dialog).getByRole('button', { name: 'Issue Refund' }))

    expect(
      await within(dialog).findByText('Order can be refunded at most 60.00.'),
    ).toBeInTheDocument()
  })

  it('shows "Refund needed" when cancelling a paid Order', async () => {
    const order = withLedger(anOrder({ status: 'confirmed' }), [aPayment({ amount: '67.97' })], {
      payment_status: 'paid',
      captured_amount: '67.97',
      amount_due: '0.00',
    })
    orderStore([order])
    const { user } = await open(`/orders/${order.id}`, order.number, operations)

    await user.click(screen.getByRole('button', { name: 'Cancel Order' }))
    expect(await screen.findByText('Refund needed: $67.97')).toBeInTheDocument()
  })

  it('shows the Refund a Partial cancellation of a paid Order needs', async () => {
    const order = withLedger(anOrder({ status: 'confirmed' }), [aPayment({ amount: '67.97' })], {
      payment_status: 'paid',
      captured_amount: '67.97',
      amount_due: '0.00',
    })
    orderStore([order])
    const { user } = await open(`/orders/${order.id}`, order.number, operations)

    await user.click(screen.getByRole('button', { name: 'Cancel items' }))
    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Units of TEE-RED-M to cancel'), '1')

    // 2 × 19.99 = 39.98 + 5% VAT 2.00 + 5.00 shipping = 46.98, so 67.97 − 46.98 goes back.
    expect(await within(dialog).findByText('Refund needed: $20.99')).toBeInTheDocument()
  })

  it('hides the ledger from Roles without payments.view', async () => {
    const order = anOrder({ status: 'confirmed' })
    orderStore([order])
    await open(`/orders/${order.id}`, order.number, inventory)

    expect(screen.queryByText('Payment ledger')).not.toBeInTheDocument()
  })
})
