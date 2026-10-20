import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import type { CustomerAddress } from '@/features/customers/types'
import { aCustomer, customerStore } from '@/test/msw/customers'
import { apiError } from '@/test/msw/handlers'
import { aWarehouse, warehouseStore } from '@/test/msw/inventory'
import { anOrder, aSellableVariant, orderStore } from '@/test/msw/orders'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'

const salesUser: CurrentUser = {
  id: 4,
  name: 'Sami Nassar',
  email: 'sales@inkcode.test',
  role: 'sales',
  permissions: [
    'dashboard.view',
    'catalog.view',
    'inventory.view',
    'customers.view',
    'customers.manage',
    'orders.view',
    'orders.create',
    'orders.update',
    'orders.cancel',
  ],
  preferences: { locale: 'en', theme: 'light' },
}

const viewer: CurrentUser = {
  ...salesUser,
  id: 9,
  role: 'viewer',
  permissions: ['dashboard.view', 'orders.view', 'customers.view'],
}

const homeAddress: CustomerAddress = {
  id: 31,
  label: 'Home',
  recipient_name: 'Rana Haddad',
  phone: null,
  country_code: 'LB',
  city: 'Beirut',
  line1: 'Hamra Street 12',
  line2: null,
  postal_code: null,
  is_default_billing: true,
  is_default_shipping: true,
}

async function open(path: string, heading: string, user: CurrentUser = salesUser) {
  signedInAs(user)
  warehouseStore([aWarehouse({ id: 1, name: 'Beirut Central', code: 'BEY-01' })])
  const view = renderApp(path)
  await screen.findByRole('heading', { name: heading, level: 1 }, { timeout: 10_000 })
  return view
}

describe('the Orders list', () => {
  it('lists Orders with their status and total, and filters by status', async () => {
    orderStore([
      anOrder({ number: 'ORD-2026-000001', status: 'pending', total: '67.97' }),
      anOrder({ number: 'ORD-2026-000002', status: 'confirmed', total: '120.00' }),
    ])
    const { user } = await open('/orders', 'Orders')

    const row = (await screen.findByText('ORD-2026-000002')).closest('tr')!
    expect(within(row).getByText('Confirmed')).toBeInTheDocument()
    expect(within(row).getByText('$120.00')).toBeInTheDocument()

    await user.selectOptions(screen.getByRole('combobox', { name: 'Status' }), 'pending')
    await waitFor(() => expect(screen.queryByText('ORD-2026-000002')).not.toBeInTheDocument())
    expect(screen.getByText('ORD-2026-000001')).toBeInTheDocument()
  }, 20_000)

  it('starts empty with a way to enter the first Order', async () => {
    orderStore([])
    await open('/orders', 'Orders')

    expect(await screen.findByText('No Orders yet')).toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: 'New Order' }).length).toBeGreaterThan(0)
  })

  it('shows an error with a retry when the list fails', async () => {
    orderStore([])
    server.use(http.get('*/api/v1/orders', () => apiError(500, 'server_error', 'Boom')))
    await open('/orders', 'Orders')

    expect(await screen.findByRole('button', { name: /try again/i })).toBeInTheDocument()
  })

  it('offers no New Order button to a Role that only looks', async () => {
    orderStore([anOrder()])
    await open('/orders', 'Orders', viewer)

    await screen.findByText('Rana Haddad')
    expect(screen.queryByRole('link', { name: 'New Order' })).not.toBeInTheDocument()
  })
})

describe('one Order', () => {
  it('confirms a Pending Order with an Idempotency-Key', async () => {
    const order = anOrder({ status: 'pending' })
    const { requests } = orderStore([order])
    const { user } = await open(`/orders/${order.id}`, order.number)

    expect(screen.getByText('TEE-RED-M')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Confirm Order' }))

    expect(await screen.findByText('Moved to Confirmed')).toBeInTheDocument()
    expect(requests.at(-1)).toMatchObject({ path: 'confirm' })
    expect(requests.at(-1)?.key).toBeTruthy()
    expect(screen.queryByRole('button', { name: 'Confirm Order' })).not.toBeInTheDocument()
  })

  it('names each line that lacks stock when confirming is refused', async () => {
    const order = anOrder()
    orderStore([order], {
      shortages: [{ variant_id: 11, sku: 'TEE-RED-M', requested: 3, available: 1 }],
    })
    const { user } = await open(`/orders/${order.id}`, order.number)

    await user.click(screen.getByRole('button', { name: 'Confirm Order' }))

    const alert = await screen.findByRole('alert')
    expect(within(alert).getByText('The Order could not be confirmed')).toBeInTheDocument()
    expect(within(alert).getByText(/3 requested, only 1 available/)).toBeInTheDocument()
  })

  it('cancels with a reason', async () => {
    const order = anOrder({ status: 'confirmed' })
    const { requests } = orderStore([order])
    const { user } = await open(`/orders/${order.id}`, order.number)

    await user.click(screen.getByRole('button', { name: 'Cancel Order' }))
    const dialog = await screen.findByRole('alertdialog')
    expect(within(dialog).getByText(/Reserved stock becomes Available again/)).toBeInTheDocument()
    await user.type(within(dialog).getByRole('textbox', { name: 'Reason (optional)' }), 'Duplicate')
    await user.click(within(dialog).getByRole('button', { name: 'Cancel Order' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(requests.at(-1)).toMatchObject({ path: 'cancel', body: { note: 'Duplicate' } })
    expect(await screen.findByText('Duplicate')).toBeInTheDocument()
  })

  it('offers no status actions to a Role that only looks', async () => {
    const order = anOrder()
    orderStore([order])
    await open(`/orders/${order.id}`, order.number, viewer)

    expect(screen.queryByRole('button', { name: 'Confirm Order' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Cancel Order' })).not.toBeInTheDocument()
  })

  it('says so when the Order does not exist', async () => {
    orderStore([])
    signedInAs(salesUser)
    renderApp('/orders/404')

    expect(
      await screen.findByText('This Order does not exist, or it was removed.'),
    ).toBeInTheDocument()
  })
})

describe('entering an Order', () => {
  it('quotes as it is filled in, pre-selects a Warehouse with stock and creates the Order once', async () => {
    customerStore([aCustomer({ id: 1, name: 'Rana Haddad', addresses: [homeAddress] })])
    server.use(
      http.get('*/api/v1/shipping/quote', () =>
        HttpResponse.json({
          data: {
            zone: { id: 1, name: 'Lebanon' },
            methods: [
              {
                id: 3,
                name: 'Standard',
                price: '5.00',
                min_delivery_days: 2,
                max_delivery_days: 4,
              },
            ],
            message: null,
          },
        }),
      ),
    )
    const tee = aSellableVariant({ id: 11, sku: 'TEE-RED-M', price: '19.99' })
    const { requests } = orderStore([], { variants: [tee] })
    const { user } = await open('/orders/new', 'New Order')

    await user.type(screen.getByRole('searchbox', { name: 'Find a Customer' }), 'Rana')
    await user.click(await screen.findByRole('button', { name: 'Choose Rana Haddad' }))
    await user.type(
      screen.getByRole('searchbox', { name: 'Find a Variant by SKU or Product' }),
      'TEE',
    )
    await user.click(await screen.findByRole('button', { name: 'Add TEE-RED-M' }))
    const quantity = screen.getByRole('textbox', { name: 'Quantity' })
    await user.clear(quantity)
    await user.type(quantity, '3')

    // 3 × 19.99 = 59.97 + VAT 3.00 + shipping 5.00.
    await waitFor(() => expect(screen.getByTestId('order-total')).toHaveTextContent('$67.97'))
    expect(screen.getByRole('combobox', { name: 'Fulfilling warehouse' })).toHaveValue('1')
    expect(screen.getByRole('combobox', { name: 'Shipping method' })).toHaveValue('3')
    expect(screen.getByRole('combobox', { name: 'Shipping address' })).toHaveValue('31')

    await user.click(screen.getByRole('button', { name: 'Create Order' }))

    expect(await screen.findByRole('heading', { name: /ORD-2026-/, level: 1 })).toBeInTheDocument()
    expect(requests.filter((request) => request.path === 'create')).toHaveLength(1)
    expect(requests[0]).toMatchObject({
      body: {
        customer_id: 1,
        billing_address_id: 31,
        shipping_address_id: 31,
        warehouse_id: 1,
        shipping_method_id: 3,
        lines: [{ variant_id: 11, quantity: 3 }],
      },
    })
    expect(requests[0]?.key).toBeTruthy()
  }, 20_000)

  it('asks for a Customer and at least one Variant before sending anything', async () => {
    customerStore([])
    const { requests } = orderStore([])
    const { user } = await open('/orders/new', 'New Order')

    await user.click(screen.getByRole('button', { name: 'Create Order' }))

    expect(await screen.findByText('Choose the Customer.')).toBeInTheDocument()
    expect(screen.getByText('Add at least one Variant.')).toBeInTheDocument()
    expect(requests).toHaveLength(0)
  })
})

it('shows a Customer their Order history on their profile', async () => {
  customerStore([aCustomer({ id: 1, name: 'Rana Haddad', addresses: [homeAddress] })])
  orderStore([
    anOrder({
      number: 'ORD-2026-000042',
      customer: { id: 1, name: 'Rana Haddad', email: null, phone: null },
    }),
  ])
  await open('/customers/1', 'Rana Haddad')

  expect(await screen.findByText('Order history')).toBeInTheDocument()
  expect(await screen.findByText('ORD-2026-000042')).toBeInTheDocument()
})
