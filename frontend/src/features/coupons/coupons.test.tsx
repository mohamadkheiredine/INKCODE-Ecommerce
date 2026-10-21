import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { aCustomer, customerStore } from '@/test/msw/customers'
import { aCoupon, aRedemption, couponStore } from '@/test/msw/coupons'
import { apiError } from '@/test/msw/handlers'
import { aWarehouse, warehouseStore } from '@/test/msw/inventory'
import { aSellableVariant, orderStore } from '@/test/msw/orders'
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
    'customers.view',
    'orders.view',
    'orders.create',
    'coupons.view',
    'coupons.manage',
  ],
  preferences: { locale: 'en', theme: 'light' },
}

const financeUser: CurrentUser = {
  ...salesUser,
  id: 6,
  role: 'finance',
  permissions: ['dashboard.view', 'orders.view', 'coupons.view'],
}

async function open(path: string, heading: string, user: CurrentUser = salesUser) {
  signedInAs(user)
  server.use(http.get('*/api/v1/categories', () => HttpResponse.json(emptyPage())))
  const view = renderApp(path)
  await screen.findByRole('heading', { name: heading, level: 1 }, { timeout: 10_000 })
  return view
}

function emptyPage() {
  return {
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 100, total: 0, from: null, to: null },
    links: { first: null, last: null, prev: null, next: null },
  }
}

describe('the Coupons list', () => {
  it('shows each Coupon with its discount, state and uses, and filters by state', async () => {
    couponStore([
      aCoupon({ code: 'SAVE10', times_used: 3, usage_limit: 100, created_at: '2026-10-03' }),
      aCoupon({
        code: 'WELCOME5',
        type: 'fixed',
        value: '5.00',
        state: 'expired',
        minimum_order_amount: '25.00',
        created_at: '2026-10-02',
      }),
    ])
    const { user } = await open('/coupons', 'Coupons')

    const save = (await screen.findByRole('link', { name: 'SAVE10' })).closest('tr')!
    expect(within(save).getByText('10%')).toBeInTheDocument()
    expect(within(save).getByText('Active')).toBeInTheDocument()
    expect(within(save).getByText('3 of 100')).toBeInTheDocument()
    const welcome = screen.getByRole('link', { name: 'WELCOME5' }).closest('tr')!
    expect(within(welcome).getByText('Orders from $25.00')).toBeInTheDocument()

    await user.selectOptions(screen.getByRole('combobox', { name: 'State' }), 'expired')
    await waitFor(() => expect(screen.queryByRole('link', { name: 'SAVE10' })).toBeNull())
    expect(screen.getByRole('link', { name: 'WELCOME5' })).toBeInTheDocument()
  })

  it('starts empty with a way to create the first Coupon', async () => {
    couponStore([])
    await open('/coupons', 'Coupons')

    expect(await screen.findByText('No Coupons yet')).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'New Coupon' }).length).toBeGreaterThan(0)
  })

  it('shows an error with a retry when the list fails', async () => {
    let calls = 0
    server.use(
      http.get('*/api/v1/coupons', () => {
        calls += 1
        return calls === 1
          ? apiError(500, 'server_error', 'Server error')
          : HttpResponse.json(emptyPage())
      }),
    )
    signedInAs(salesUser)
    const { user } = renderApp('/coupons')

    await user.click(await screen.findByRole('button', { name: /try again/i }, { timeout: 10_000 }))
    expect(await screen.findByText('No Coupons yet')).toBeInTheDocument()
  })

  it('offers no New Coupon button to a Role that only looks', async () => {
    couponStore([aCoupon({ code: 'SAVE10' })])
    await open('/coupons', 'Coupons', financeUser)

    expect(await screen.findByRole('link', { name: 'SAVE10' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'New Coupon' })).toBeNull()
  })
})

describe('creating a Coupon', () => {
  it('checks the form, shows a taken code under its field and creates the Coupon', async () => {
    const store = couponStore([aCoupon({ code: 'SAVE10' })])
    const { user } = await open('/coupons', 'Coupons')

    await user.click(screen.getByRole('button', { name: 'New Coupon' }))
    const dialog = await screen.findByRole('dialog', { name: 'New Coupon' })
    const code = within(dialog).getByRole('textbox', { name: 'Code' })
    const value = within(dialog).getByRole('textbox', { name: 'Discount' })

    await user.type(value, '150')
    await user.click(within(dialog).getByRole('button', { name: 'Create Coupon' }))
    expect(await within(dialog).findByText('Use 3 to 40 characters.')).toBeInTheDocument()
    expect(within(dialog).getByText('A percentage is at most 100.')).toBeInTheDocument()
    expect(store.bodies).toHaveLength(0)

    await user.type(code, 'save10')
    await user.clear(value)
    await user.type(value, '15')
    await user.click(within(dialog).getByRole('button', { name: 'Create Coupon' }))
    expect(await within(dialog).findByText('The code has already been taken.')).toBeInTheDocument()

    await user.clear(code)
    await user.type(code, 'spring15')
    await user.type(within(dialog).getByRole('textbox', { name: 'Total uses' }), '50')
    await user.click(within(dialog).getByRole('button', { name: 'Create Coupon' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull())
    expect(store.bodies.at(-1)).toMatchObject({
      code: 'SPRING15',
      type: 'percentage',
      value: '15',
      usage_limit: 50,
      per_customer_limit: null,
      minimum_order_amount: '0',
      product_ids: [],
      category_ids: [],
    })
    expect(await screen.findByRole('link', { name: 'SPRING15' })).toBeInTheDocument()
  }, 20_000)
})

describe('a Coupon’s usage', () => {
  it('lists the Orders and Customers that used it', async () => {
    couponStore(
      [aCoupon({ id: 5, code: 'SAVE10', times_used: 1, usage_limit: 10 })],
      [aRedemption({ customer: { id: 1, name: 'Rana Haddad', email: null } })],
    )
    await open('/coupons/5', 'SAVE10')

    const row = (await screen.findByRole('link', { name: 'Rana Haddad' })).closest('tr')!
    expect(within(row).getByRole('link', { name: /ORD-2026-/ })).toBeInTheDocument()
    expect(within(row).getByText('$7.00')).toBeInTheDocument()
    expect(screen.getByText('1 of 10')).toBeInTheDocument()
    expect(screen.getByText('Every item')).toBeInTheDocument()
  })

  it('says so when nobody used it yet', async () => {
    couponStore([aCoupon({ id: 6, code: 'FRESH' })])
    await open('/coupons/6', 'FRESH')

    expect(await screen.findByText('Not used yet')).toBeInTheDocument()
  })
})

describe('applying a Coupon to an Order', () => {
  it('shows whether the code applies and prices the quote with it', async () => {
    customerStore([aCustomer({ id: 1, name: 'Rana Haddad', addresses: [] })])
    warehouseStore([aWarehouse({ id: 1, name: 'Beirut Central', code: 'BEY-01' })])
    orderStore([], { variants: [aSellableVariant({ id: 11, sku: 'TEE-RED-M', price: '20.00' })] })
    const { user } = await open('/orders/new', 'New Order')

    await user.type(
      screen.getByRole('searchbox', { name: 'Find a Variant by SKU or Product' }),
      'TEE',
    )
    await user.click(await screen.findByRole('button', { name: 'Add TEE-RED-M' }))
    await waitFor(() => expect(screen.getByTestId('order-total')).toHaveTextContent('$21.00'))

    const coupon = screen.getByRole('textbox', { name: 'Coupon code' })
    await user.type(coupon, 'nope')
    await user.click(screen.getByRole('button', { name: 'Apply' }))
    expect(await screen.findByText('There is no Coupon with the code NOPE.')).toBeInTheDocument()

    await user.clear(coupon)
    await user.type(coupon, 'save10')
    await user.click(screen.getByRole('button', { name: 'Apply' }))
    expect(
      await screen.findByText('SAVE10 applied: 10% off the eligible items.'),
    ).toBeInTheDocument()
    // 20.00 − 2.00 = 18.00, VAT 0.90.
    await waitFor(() => expect(screen.getByTestId('order-total')).toHaveTextContent('$18.90'))
  }, 20_000)
})
