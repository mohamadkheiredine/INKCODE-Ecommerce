import { screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { aCustomer, customerStore } from '@/test/msw/customers'
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
