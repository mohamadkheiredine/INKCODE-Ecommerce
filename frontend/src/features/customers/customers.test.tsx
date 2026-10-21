import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { aCustomer, customerStore } from '@/test/msw/customers'
import { apiError } from '@/test/msw/handlers'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'

const supportUser: CurrentUser = {
  id: 5,
  name: 'Maya Support',
  email: 'support@inkcode.test',
  role: 'support',
  permissions: ['dashboard.view', 'customers.view', 'customers.manage'],
  preferences: { locale: 'en', theme: 'light' },
}

/** Finance may look at Customers but not change them. */
const financeUser: CurrentUser = {
  ...supportUser,
  id: 6,
  role: 'finance',
  permissions: ['dashboard.view', 'customers.view'],
}

const wholesale = { id: 1, name: 'wholesale' }

async function open(path: string, user: CurrentUser = supportUser) {
  signedInAs(user)
  const view = renderApp(path)
  // The first visit loads the page's code, which can take a moment on a busy machine.
  await screen.findByRole('heading', { level: 1 }, { timeout: 10_000 })
  return view
}

describe('the Customers list', () => {
  it('shows each Customer with their Tags and filters by Tag in the URL', async () => {
    customerStore(
      [aCustomer({ name: 'Rana Haddad', tags: [wholesale] }), aCustomer({ name: 'Omar Khalil' })],
      [wholesale],
    )
    const { user, router } = await open('/customers')

    const rana = (await screen.findByRole('link', { name: 'Rana Haddad' })).closest('tr')!
    expect(within(rana).getByText('wholesale')).toBeInTheDocument()

    await user.selectOptions(screen.getByRole('combobox', { name: 'Tag' }), 'wholesale')

    await waitFor(() => expect(screen.queryByText('Omar Khalil')).not.toBeInTheDocument())
    expect(router.state.location.search).toBe('?filter%5Btag%5D=1')
  }, 20_000)

  it('offers to add the first Customer when there are none', async () => {
    customerStore([])
    await open('/customers')
    expect(await screen.findByText('No Customers yet')).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'New Customer' })).toHaveLength(2)
  })

  it('hides the New Customer button from a Role that may only look', async () => {
    customerStore([aCustomer({ name: 'Rana Haddad' })])
    await open('/customers', financeUser)
    await screen.findByText('Rana Haddad')
    expect(screen.queryByRole('button', { name: 'New Customer' })).not.toBeInTheDocument()
  })
})

describe('Lifetime value and Segments', { timeout: 20_000 }, () => {
  it("shows each Customer's Lifetime value and Segment and filters by Segment in the URL", async () => {
    customerStore([
      aCustomer({
        name: 'Victor Vip',
        lifetime_value: '1500.00',
        segment: 'vip',
        last_order_at: '2026-08-22T12:00:00+00:00',
      }),
      aCustomer({
        name: 'Rami Risk',
        lifetime_value: '300.00',
        segment: 'at_risk',
        last_order_at: '2026-07-13T12:00:00+00:00',
      }),
      aCustomer({ name: 'Zane None' }),
    ])
    const { user, router } = await open('/customers')

    const victor = (await screen.findByRole('link', { name: 'Victor Vip' })).closest('tr')!
    expect(within(victor).getByText('VIP')).toBeInTheDocument()
    expect(within(victor).getByText(/1,500\.00/)).toBeInTheDocument()
    const zane = screen.getByRole('link', { name: 'Zane None' }).closest('tr')!
    expect(within(zane).getByText('No Orders yet')).toBeInTheDocument()
    expect(within(zane).getByText('Regular')).toBeInTheDocument()

    await user.selectOptions(screen.getByRole('combobox', { name: 'Segment' }), 'At risk')

    await waitFor(() => expect(screen.queryByText('Victor Vip')).not.toBeInTheDocument())
    expect(screen.getByText('Rami Risk')).toBeInTheDocument()
    expect(router.state.location.search).toBe('?filter%5Bsegment%5D=at_risk')
  })

  it('applies a Lifetime value bound from the URL field', async () => {
    customerStore([aCustomer({ name: 'Victor Vip', lifetime_value: '1500.00', segment: 'vip' })])
    const { user, router } = await open('/customers')
    await screen.findByText('Victor Vip')

    await user.type(screen.getByRole('textbox', { name: 'Min. Lifetime value' }), '1000{Enter}')

    await waitFor(() =>
      expect(router.state.location.search).toBe('?filter%5Blifetime_value_min%5D=1000'),
    )
  })

  it('offers to try again when the list fails to load', async () => {
    let calls = 0
    server.use(
      http.get('*/api/v1/customers', () => {
        calls += 1
        return calls === 1
          ? apiError(500, 'server_error', 'Server error')
          : HttpResponse.json({
              data: [],
              meta: { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null },
              links: { first: null, last: null, prev: null, next: null },
            })
      }),
      http.get('*/api/v1/customer-tags', () => HttpResponse.json({ data: [], meta: { total: 0 } })),
    )
    const { user } = await open('/customers')

    await user.click(await screen.findByRole('button', { name: /try again/i }))
    expect(await screen.findByText('No Customers yet')).toBeInTheDocument()
  })

  it('shows the Lifetime value, Segment and last Order on the profile', async () => {
    customerStore([
      aCustomer({
        id: 7,
        name: 'Victor Vip',
        lifetime_value: '1500.00',
        segment: 'vip',
        last_order_at: '2026-08-22T12:00:00+00:00',
      }),
    ])
    await open('/customers/7')

    expect(await screen.findByText(/1,500\.00/)).toBeInTheDocument()
    expect(screen.getByText('VIP')).toBeInTheDocument()
    expect(screen.getByText('Last Order')).toBeInTheDocument()
  })
})

describe('adding a Customer', () => {
  it('needs an email or a phone, then saves typed and suggested Tags and opens the profile', async () => {
    const store = customerStore([], [wholesale])
    const { user, router } = await open('/customers')
    await user.click((await screen.findAllByRole('button', { name: 'New Customer' }))[0]!)
    const dialog = await screen.findByRole('dialog', { name: 'New Customer' })

    await user.type(within(dialog).getByLabelText('Name'), 'Rana Haddad')
    await user.click(within(dialog).getByRole('button', { name: 'Add Customer' }))
    expect(
      await within(dialog).findByText(
        'Enter an email or a phone number so the Customer can be reached.',
      ),
    ).toBeInTheDocument()
    expect(store.bodies).toHaveLength(0)

    await user.type(within(dialog).getByLabelText('Phone'), '+961 3 111 222')
    await user.type(within(dialog).getByLabelText('Tags'), 'repeat buyer{Enter}')
    await user.click(within(dialog).getByRole('button', { name: 'wholesale' }))
    await user.click(within(dialog).getByRole('button', { name: 'Add Customer' }))

    await waitFor(() => expect(router.state.location.pathname).toMatch(/^\/customers\/\d+$/))
    expect(store.bodies[0]).toEqual({
      name: 'Rana Haddad',
      email: null,
      phone: '+961 3 111 222',
      tags: ['repeat buyer', 'wholesale'],
    })
  })
})

// The profile is its own route module, loaded cold by these tests.
describe('a Customer profile', { timeout: 20_000 }, () => {
  it('shows the default addresses and adds a note', async () => {
    const customer = aCustomer({
      name: 'Rana Haddad',
      addresses: [
        {
          id: 9,
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
        },
      ],
    })
    customerStore([customer])
    const { user } = await open(`/customers/${customer.id}`)

    const home = screen.getByRole('article', { name: 'Home' })
    expect(within(home).getByText('Default billing')).toBeInTheDocument()
    expect(within(home).getByText('Lebanon')).toBeInTheDocument()

    await user.type(await screen.findByLabelText('New note'), 'Prefers WhatsApp')
    await user.click(screen.getByRole('button', { name: 'Add note' }))
    expect(await screen.findByText('Prefers WhatsApp')).toBeInTheDocument()
  })

  it('says when the Customer does not exist', async () => {
    customerStore([])
    signedInAs(supportUser)
    renderApp('/customers/999')
    expect(
      await screen.findByText('Customer not found', {}, { timeout: 10_000 }),
    ).toBeInTheDocument()
  })
})
