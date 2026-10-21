import { screen, waitFor, within } from '@testing-library/react'
import { http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { apiError } from '@/test/msw/handlers'
import { aWarehouse, warehouseStore } from '@/test/msw/inventory'
import { anOrder, orderStore } from '@/test/msw/orders'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { aCarrier, carrierStore } from '@/test/msw/shipping'
import { renderApp } from '@/test/render-app'

const operations: CurrentUser = {
  id: 7,
  name: 'Lina Operations',
  email: 'ops@inkcode.test',
  role: 'operations',
  permissions: ['dashboard.view', 'orders.view', 'orders.transition', 'shipments.manage'],
  preferences: { locale: 'en', theme: 'light' },
}

const viewer: CurrentUser = {
  ...operations,
  id: 9,
  role: 'viewer',
  permissions: ['dashboard.view', 'orders.view'],
}

async function open(orderId: number, heading: string, user: CurrentUser = operations) {
  signedInAs(user)
  warehouseStore([aWarehouse({ id: 1, name: 'Beirut Central', code: 'BEY-01' })])
  carrierStore([aCarrier({ id: 5, name: 'Aramex' })])
  const view = renderApp(`/orders/${orderId}`)
  await screen.findByRole('heading', { name: heading, level: 1 }, { timeout: 10_000 })
  return view
}

async function fillShipment(
  user: Awaited<ReturnType<typeof open>>['user'],
  tracking: string,
  units: string,
) {
  const dialog = await screen.findByRole('dialog')
  const carrier = within(dialog).getByRole('combobox', { name: 'Carrier' })
  await waitFor(() =>
    expect(within(carrier).getByRole('option', { name: 'Aramex' })).toBeInTheDocument(),
  )
  await user.selectOptions(carrier, 'Aramex')
  await user.type(within(dialog).getByRole('textbox', { name: 'Tracking number' }), tracking)
  const quantity = within(dialog).getByRole('textbox', { name: 'Units of TEE-RED-M to ship' })
  await user.clear(quantity)
  await user.type(quantity, units)
  await user.click(within(dialog).getByRole('button', { name: 'Ship these units' }))
  return dialog
}

describe('Shipments', () => {
  it('ships an Order in two Shipments, then marks both delivered', async () => {
    const order = anOrder({ status: 'ready_for_shipping' })
    const { requests } = orderStore([order])
    const { user } = await open(order.id, order.number)

    expect(screen.getByText('No Shipments yet')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Create Shipment' }))
    await fillShipment(user, 'AWB-1', '2')

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(requests.at(-1)).toMatchObject({
      path: 'shipments',
      body: {
        carrier_id: 5,
        tracking_number: 'AWB-1',
        lines: [{ line_id: order.lines![0]!.id, quantity: 2 }],
      },
    })
    expect(requests.at(-1)?.key).toBeTruthy()
    expect(await screen.findByText('2 of 3 units shipped')).toBeInTheDocument()
    expect(screen.getByText('Partially shipped')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /AWB-1/ })).toHaveAttribute(
      'href',
      'https://track.example/?n=AWB-1',
    )

    await user.click(screen.getByRole('button', { name: 'Create Shipment' }))
    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText(/1 unit left to ship/)).toBeInTheDocument()
    await fillShipment(user, 'AWB-2', '1')

    expect(
      await screen.findByText('Everything left of ' + order.number + ' has shipped.'),
    ).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Create Shipment' })).not.toBeInTheDocument()

    for (const button of screen.getAllByRole('button', { name: 'Mark delivered' })) {
      await user.click(button)
      await waitFor(() => expect(button).not.toBeInTheDocument())
    }
    expect(requests.filter((request) => request.path === 'deliver')).toHaveLength(2)
    expect(await screen.findByText('Moved to Delivered')).toBeInTheDocument()
  }, 15_000)

  it('shows a refused tracking number on its field', async () => {
    const order = anOrder({ status: 'ready_for_shipping' })
    orderStore([order])
    server.use(
      http.post('*/api/v1/orders/:id/shipments', () =>
        apiError(422, 'validation_failed', 'The given data was invalid.', {
          tracking_number: ['This Carrier already has a Shipment with this tracking number.'],
        }),
      ),
    )
    const { user } = await open(order.id, order.number)

    await user.click(screen.getByRole('button', { name: 'Create Shipment' }))
    const dialog = await fillShipment(user, 'AWB-1', '1')

    expect(
      await within(dialog).findByText(
        'This Carrier already has a Shipment with this tracking number.',
      ),
    ).toBeInTheDocument()
  })

  it('asks for a Carrier and a tracking number before sending', async () => {
    const order = anOrder({ status: 'ready_for_shipping' })
    const { requests } = orderStore([order])
    const { user } = await open(order.id, order.number)

    await user.click(screen.getByRole('button', { name: 'Create Shipment' }))
    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: 'Ship these units' }))

    expect(within(dialog).getByText('Choose a Carrier.')).toBeInTheDocument()
    expect(within(dialog).getByText('Enter the tracking number.')).toBeInTheDocument()
    expect(requests.filter((request) => request.path === 'shipments')).toHaveLength(0)
  })

  it('explains when Shipments cannot be created yet, and offers no actions to a Role that only looks', async () => {
    const preparing = anOrder({ status: 'preparing' })
    orderStore([preparing])
    await open(preparing.id, preparing.number, viewer)

    expect(
      screen.getByText('Shipments can be created once the Order is Ready for shipping.'),
    ).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Create Shipment' })).not.toBeInTheDocument()
    expect(screen.getByText('Not shipped')).toBeInTheDocument()
  })
})
