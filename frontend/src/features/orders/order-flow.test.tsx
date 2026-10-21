import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { apiError } from '@/test/msw/handlers'
import { aWarehouse, warehouseStore } from '@/test/msw/inventory'
import { anOrder, orderStore } from '@/test/msw/orders'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'

const operations: CurrentUser = {
  id: 7,
  name: 'Lina Operations',
  email: 'ops@inkcode.test',
  role: 'operations',
  permissions: [
    'dashboard.view',
    'orders.view',
    'orders.create',
    'orders.update',
    'orders.transition',
    'orders.cancel',
    'orders.assign',
  ],
  preferences: { locale: 'en', theme: 'light' },
}

const viewer: CurrentUser = {
  ...operations,
  id: 9,
  role: 'viewer',
  permissions: ['dashboard.view', 'orders.view'],
}

async function open(path: string, heading: string, user: CurrentUser = operations) {
  signedInAs(user)
  warehouseStore([aWarehouse({ id: 1, name: 'Beirut Central', code: 'BEY-01' })])
  const view = renderApp(path)
  await screen.findByRole('heading', { name: heading, level: 1 }, { timeout: 10_000 })
  return view
}

describe('moving an Order forward', () => {
  it('moves a Confirmed Order to Preparing with an Idempotency-Key, then locks editing', async () => {
    const order = anOrder({ status: 'confirmed' })
    const { requests } = orderStore([order])
    const { user } = await open(`/orders/${order.id}`, order.number)

    expect(screen.getByRole('button', { name: 'Edit Order' })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Move to Preparing' }))

    expect(await screen.findByText('Moved to Preparing')).toBeInTheDocument()
    expect(requests.at(-1)).toMatchObject({ path: 'advance', body: { status: 'preparing' } })
    expect(requests.at(-1)?.key).toBeTruthy()
    expect(screen.getByRole('button', { name: 'Move to Ready for shipping' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Order' })).not.toBeInTheDocument()
    expect(screen.getByText('Locked')).toBeInTheDocument()
  })

  it('shows the refusal when the move is no longer allowed', async () => {
    const order = anOrder({ status: 'confirmed' })
    orderStore([order])
    server.use(
      http.post('*/api/v1/orders/:id/advance', () =>
        apiError(409, 'invalid_transition', 'Order is Cancelled, so it cannot become Preparing.'),
      ),
    )
    const { user } = await open(`/orders/${order.id}`, order.number)

    await user.click(screen.getByRole('button', { name: 'Move to Preparing' }))

    expect(
      await screen.findByText('Order is Cancelled, so it cannot become Preparing.'),
    ).toBeInTheDocument()
  })

  it('offers no flow actions to a Role that only looks, but still the Invoice', async () => {
    const order = anOrder({ status: 'confirmed' })
    orderStore([order])
    await open(`/orders/${order.id}`, order.number, viewer)

    for (const name of ['Move to Preparing', 'Edit Order', 'Cancel items', 'Add note']) {
      expect(screen.queryByRole('button', { name })).not.toBeInTheDocument()
    }
    expect(screen.queryByRole('combobox', { name: 'Assignee' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Invoice' })).toHaveAttribute(
      'href',
      `/orders/${order.id}/invoice`,
    )
  })
})

describe('Partial cancellation', () => {
  it('cancels some units of a line with a reason', async () => {
    const order = anOrder({ status: 'confirmed' })
    const { requests } = orderStore([order])
    const { user } = await open(`/orders/${order.id}`, order.number)

    await user.click(screen.getByRole('button', { name: 'Cancel items' }))
    const dialog = await screen.findByRole('dialog')
    const submit = within(dialog).getByRole('button', { name: 'Cancel these units' })
    expect(submit).toBeDisabled()

    const units = within(dialog).getByRole('textbox', { name: 'Units of TEE-RED-M to cancel' })
    await user.type(units, '4')
    expect(within(dialog).getByRole('alert')).toHaveTextContent(
      'Cancel at most the units each line still has.',
    )
    await user.clear(units)
    await user.type(units, '2')
    await user.type(within(dialog).getByRole('textbox', { name: 'Reason (optional)' }), 'Too many')
    await user.click(submit)

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(requests.at(-1)).toMatchObject({
      path: 'cancel-lines',
      body: { lines: [{ line_id: order.lines![0]!.id, quantity: 2 }], note: 'Too many' },
    })
    expect(await screen.findByText('2 cancelled')).toBeInTheDocument()
  })
})

describe('the Assignee and the timeline', () => {
  it('assigns the Order to a User', async () => {
    const order = anOrder({ status: 'preparing' })
    const { requests } = orderStore([order])
    const { user } = await open(`/orders/${order.id}`, order.number)

    const select = screen.getByRole('combobox', { name: 'Assignee' })
    await waitFor(() =>
      expect(within(select).getByRole('option', { name: 'Lina Operations' })).toBeInTheDocument(),
    )
    await user.selectOptions(select, 'Lina Operations')

    await waitFor(() =>
      expect(requests.at(-1)).toMatchObject({ path: 'assignee', body: { assignee_id: 7 } }),
    )
    expect(await screen.findByText('The Order is assigned to Lina Operations.')).toBeInTheDocument()
  })

  it('adds a note to a Locked order, and asks for text first', async () => {
    const order = anOrder({ status: 'ready_for_shipping' })
    const { requests } = orderStore([order])
    const { user } = await open(`/orders/${order.id}`, order.number)

    await user.click(screen.getByRole('button', { name: 'Add note' }))
    expect(await screen.findByText('Write the note first.')).toBeInTheDocument()
    expect(requests).toHaveLength(0)

    await user.type(screen.getByRole('textbox', { name: 'Add a note' }), 'Gift wrap, please.')
    await user.click(screen.getByRole('button', { name: 'Add note' }))

    expect(await screen.findByText('Gift wrap, please.')).toBeInTheDocument()
    expect(requests.at(-1)).toMatchObject({ path: 'note', body: { body: 'Gift wrap, please.' } })
  })
})

describe('the Invoice', () => {
  it('prints the store profile, Customer, lines and totals', async () => {
    const order = anOrder({ status: 'confirmed' })
    orderStore([order])
    await open(`/orders/${order.id}/invoice`, 'Invoice')

    const invoice = screen.getByRole('article', { name: `Invoice ${order.number}` })
    expect(within(invoice).getByText('Inkcode Demo Store')).toBeInTheDocument()
    expect(within(invoice).getByText('hello@inkcode-demo.test')).toBeInTheDocument()
    expect(within(invoice).getByText('TEE-RED-M')).toBeInTheDocument()
    expect(within(invoice).getByTestId('order-total')).toHaveTextContent('$67.97')
    expect(screen.getByRole('button', { name: 'Print' })).toBeInTheDocument()
  })

  it('shows an error with a retry when the Order cannot be loaded', async () => {
    const order = anOrder()
    orderStore([order])
    server.use(http.get('*/api/v1/orders/:id', () => HttpResponse.json({}, { status: 500 })))
    signedInAs(operations)
    renderApp(`/orders/${order.id}/invoice`)

    expect(
      await screen.findByRole('button', { name: 'Try again' }, { timeout: 10_000 }),
    ).toBeInTheDocument()
  })
})
