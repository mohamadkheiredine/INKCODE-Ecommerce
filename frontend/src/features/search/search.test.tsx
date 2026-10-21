import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import type { Permission } from '@/lib/permissions'
import { customerStore } from '@/test/msw/customers'
import { adminUser, apiError } from '@/test/msw/handlers'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'
import type { SearchResults } from './types'

const results: SearchResults = {
  orders: [
    {
      id: 41,
      number: 'ORD-000041',
      status: 'confirmed',
      total: '120.50',
      customer_name: 'Rana Haddad',
      placed_at: '2026-10-20T10:00:00+00:00',
    },
  ],
  products: [
    {
      id: 5,
      name: 'Linen shirt',
      sku: 'LIN-RED-M',
      status: 'active',
      main_image_url: null,
    },
  ],
  customers: [{ id: 9, name: 'Rana Haddad', email: 'rana@example.com', phone: null }],
}

function userWith(...permissions: Permission[]): CurrentUser {
  return { ...adminUser, permissions: ['dashboard.view', ...permissions] }
}

/** The search endpoint answering every query the same way; records what was asked. */
function searchReturns(response: () => Response | Promise<Response>) {
  const queries: string[] = []
  server.use(
    http.get('*/api/v1/search', ({ request }) => {
      queries.push(new URL(request.url).searchParams.get('q') ?? '')
      return response()
    }),
  )
  return queries
}

async function openWithShortcut(user: ReturnType<typeof renderApp>['user']) {
  await screen.findByRole('heading', { name: 'Hello, Ahmed Ali' })
  await user.keyboard('{Control>}k{/Control}')
  return screen.findByRole('dialog', { name: 'Search' })
}

describe('Global search', () => {
  it('finds Orders, Products and Customers from Ctrl+K and opens one with the keyboard', async () => {
    signedInAs(userWith('orders.view', 'catalog.view', 'customers.view'))
    const queries = searchReturns(() => HttpResponse.json({ data: results }))
    const { user, router } = renderApp('/')

    const dialog = await openWithShortcut(user)
    await user.type(within(dialog).getByRole('combobox'), 'rana')

    const order = await within(dialog).findByRole('option', { name: /ORD-000041/ })
    expect(order).toHaveTextContent('Rana Haddad')
    expect(within(dialog).getByRole('group', { name: 'Orders' })).toBeInTheDocument()
    expect(within(dialog).getByRole('group', { name: 'Products' })).toHaveTextContent('LIN-RED-M')
    expect(within(dialog).getByRole('group', { name: 'Customers' })).toHaveTextContent(
      'rana@example.com',
    )
    expect(queries.at(-1)).toBe('rana')

    expect(order).toHaveAttribute('aria-selected', 'true')
    await user.keyboard('{ArrowDown}')
    expect(within(dialog).getByRole('option', { name: /Linen shirt/ })).toHaveAttribute(
      'aria-selected',
      'true',
    )
    await user.keyboard('{Enter}')

    await waitFor(() => expect(router.state.location.pathname).toBe('/catalog/products/5'))
    expect(screen.queryByRole('dialog', { name: 'Search' })).not.toBeInTheDocument()
  }, 20_000)

  it('waits for two characters and offers the quick actions meanwhile', async () => {
    signedInAs(userWith('orders.view', 'orders.create', 'customers.view'))
    const queries = searchReturns(() => HttpResponse.json({ data: results }))
    const { user } = renderApp('/')

    const dialog = await openWithShortcut(user)
    await user.type(within(dialog).getByRole('combobox'), 'r')

    expect(within(dialog).getByText(/Type at least 2 characters/)).toBeInTheDocument()
    const actions = within(dialog).getByRole('group', { name: 'Quick actions' })
    expect(
      within(actions)
        .getAllByRole('option')
        .map((option) => option.textContent),
    ).toEqual(['New order'])
    expect(queries).toEqual([])
  })

  it('says when nothing matches and offers the quick actions instead', async () => {
    signedInAs(userWith('orders.view', 'inventory.receive'))
    searchReturns(() => HttpResponse.json({ data: { orders: [], products: [] } }))
    const { user, router } = renderApp('/')

    const dialog = await openWithShortcut(user)
    await user.type(within(dialog).getByRole('combobox'), 'zzz')

    expect(await within(dialog).findByText('No matches')).toBeInTheDocument()
    expect(within(dialog).getByText(/Nothing matches “zzz”/)).toBeInTheDocument()
    await user.click(within(dialog).getByRole('option', { name: 'Receive stock' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/inventory/receipts/new'))
  })

  it('shows a failed search with a retry', async () => {
    signedInAs(userWith('orders.view'))
    let fail = true
    searchReturns(() =>
      fail
        ? apiError(500, 'server_error', 'Server error.')
        : HttpResponse.json({ data: { orders: results.orders } }),
    )
    const { user } = renderApp('/')

    const dialog = await openWithShortcut(user)
    await user.type(within(dialog).getByRole('combobox'), 'ord')

    expect(await within(dialog).findByText('The search failed')).toBeInTheDocument()
    fail = false
    await user.click(within(dialog).getByRole('button', { name: 'Try again' }))

    expect(await within(dialog).findByRole('option', { name: /ORD-000041/ })).toBeInTheDocument()
  })

  it('mirrors the palette in Arabic', async () => {
    signedInAs({
      ...userWith('orders.view'),
      preferences: { locale: 'ar', theme: 'light' },
    })
    const { user } = renderApp('/')

    await screen.findByRole('heading', { name: 'أهلاً، Ahmed Ali' })
    await user.click(screen.getByRole('button', { name: 'بحث' }))

    const dialog = await screen.findByRole('dialog', { name: 'بحث' })
    expect(dialog.querySelector('[cmdk-root]')).toHaveAttribute('dir', 'rtl')
  })
})

describe('Quick actions', () => {
  it('lists only the forms the Role may open', async () => {
    signedInAs(userWith('orders.view', 'orders.create', 'customers.view', 'customers.manage'))
    const { user } = renderApp('/')

    await user.click(await screen.findByRole('button', { name: 'Quick actions' }))

    const menu = await screen.findByRole('menu')
    expect(
      within(menu)
        .getAllByRole('menuitem')
        .map((item) => item.textContent),
    ).toEqual(['New order', 'New customer'])
  })

  it('is hidden from a Role that may open none of them', async () => {
    signedInAs(userWith('orders.view', 'catalog.view'))
    renderApp('/')

    await screen.findByRole('heading', { name: 'Hello, Ahmed Ali' })
    expect(screen.queryByRole('button', { name: 'Quick actions' })).not.toBeInTheDocument()
  })

  it('opens the New Customer form', async () => {
    signedInAs(userWith('customers.view', 'customers.manage'))
    customerStore()
    const { user, router } = renderApp('/')

    await user.click(await screen.findByRole('button', { name: 'Quick actions' }))
    await user.click(await screen.findByRole('menuitem', { name: 'New customer' }))

    expect(await screen.findByRole('dialog', { name: 'New Customer' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/customers')
    expect(router.state.location.search).toBe('?new=1')
  })
})
