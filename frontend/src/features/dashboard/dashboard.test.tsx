import { screen, waitFor, within } from '@testing-library/react'
import { delay } from 'msw'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { aDashboard, anEmptyDashboard } from '@/test/msw/dashboard'
import { apiError } from '@/test/msw/handlers'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'
import { resolveRange } from './date-range'

const finance: CurrentUser = {
  id: 6,
  name: 'Nadia Farouk',
  email: 'finance@inkcode.test',
  role: 'finance',
  permissions: ['dashboard.view', 'orders.view', 'inventory.view'],
  preferences: { locale: 'en', theme: 'light' },
}

/** Answers with this dashboard and records every range the screen asked for. */
function dashboardServer(respond: () => Response | Promise<Response>) {
  const ranges: { from: string | null; to: string | null }[] = []
  server.use(
    http.get('*/api/v1/dashboard', ({ request }) => {
      const url = new URL(request.url)
      ranges.push({ from: url.searchParams.get('from'), to: url.searchParams.get('to') })
      return respond()
    }),
  )
  return ranges
}

describe('executive dashboard', () => {
  it('shows the KPIs, comparison, chart sections, top Products, recent Orders and alerts', async () => {
    signedInAs(finance)
    dashboardServer(() => HttpResponse.json({ data: aDashboard() }))
    renderApp('/')

    expect(await screen.findByTestId('kpi-revenue-value')).toHaveTextContent('$99.50')
    expect(screen.getByTestId('kpi-net_sales-value')).toHaveTextContent('$70.00')
    expect(screen.getByTestId('kpi-average_order_value-value')).toHaveTextContent('$99.50')
    expect(screen.getByTestId('kpi-low_stock-value')).toHaveTextContent('3')
    expect(screen.getByTestId('kpi-revenue')).toHaveTextContent('+99%')
    expect(screen.getByTestId('kpi-orders')).toHaveTextContent('-50%')
    expect(screen.getByTestId('kpi-net_sales')).toHaveTextContent(/nothing in the previous period/i)
    expect(screen.getByTestId('kpi-low_stock')).toHaveTextContent(/right now/i)
    expect(screen.queryByText(/conversion/i)).not.toBeInTheDocument()

    expect(
      within(await screen.findByTestId('status-breakdown', {}, { timeout: 8000 })).getByText(
        'Delivered',
      ),
    ).toBeVisible()
    expect(within(screen.getByTestId('top-products')).getByText('Classic Tee')).toBeVisible()
    const recent = within(screen.getByTestId('recent-orders'))
    expect(recent.getByRole('link', { name: /ORD-2026-00012/ })).toHaveAttribute(
      'href',
      '/orders/12',
    )
    const alerts = within(screen.getByTestId('alerts'))
    expect(alerts.getByRole('link', { name: /3 items are at or below/ })).toHaveAttribute(
      'href',
      '/inventory/stock?filter[low_stock]=1',
    )
    expect(alerts.getByRole('link', { name: /1 Pending Order awaits/ })).toBeVisible()
  })

  it('keeps the date preset in the URL and asks the API for its days', async () => {
    signedInAs(finance)
    const ranges = dashboardServer(() => HttpResponse.json({ data: aDashboard() }))
    const { user, router } = renderApp('/?range=7d')

    await screen.findByTestId('kpi-revenue-value')
    const today = new Date()
    expect(ranges.at(-1)).toEqual(resolveRange('7d', today))
    expect(screen.getByRole('radio', { name: '7 days' })).toHaveAttribute('aria-checked', 'true')

    await user.click(screen.getByRole('radio', { name: 'This month' }))
    await waitFor(() => expect(router.state.location.search).toBe('?range=month'))
    await waitFor(() => expect(ranges.at(-1)).toEqual(resolveRange('month', today)))

    await user.click(screen.getByRole('radio', { name: 'Custom' }))
    await waitFor(() => expect(router.state.location.search).toContain('range=custom'))
    expect(router.state.location.search).toContain(`from=${resolveRange('month', today).from}`)
    expect(screen.getByLabelText('From')).toHaveValue(resolveRange('month', today).from)
  })

  it('reads a custom range from the URL', async () => {
    signedInAs(finance)
    const ranges = dashboardServer(() => HttpResponse.json({ data: aDashboard() }))
    renderApp('/?range=custom&from=2026-08-01&to=2026-08-31')

    await screen.findByTestId('kpi-revenue-value')
    expect(ranges.at(-1)).toEqual({ from: '2026-08-01', to: '2026-08-31' })
    expect(screen.getByLabelText('To')).toHaveValue('2026-08-31')
  })

  it('shows a skeleton while loading', async () => {
    signedInAs(finance)
    dashboardServer(async () => {
      await delay('infinite')
      return HttpResponse.json({ data: aDashboard() })
    })
    renderApp('/')

    expect(await screen.findByTestId('dashboard-skeleton')).toBeInTheDocument()
  })

  it('shows an empty state with an action when the range has no Counted orders', async () => {
    signedInAs({ ...finance, permissions: [...finance.permissions, 'orders.create'] })
    dashboardServer(() => HttpResponse.json({ data: anEmptyDashboard() }))
    renderApp('/')

    expect(await screen.findByText('No Counted orders in this period')).toBeVisible()
    expect(screen.getByRole('link', { name: 'Create Order' })).toHaveAttribute(
      'href',
      '/orders/new',
    )
    expect(screen.getByText('All clear: nothing needs attention.')).toBeVisible()
  })

  it('shows an error with a retry that loads the dashboard', async () => {
    signedInAs(finance)
    let fail = true
    dashboardServer(() =>
      fail
        ? apiError(500, 'server_error', 'Server error.')
        : HttpResponse.json({ data: aDashboard() }),
    )
    const { user } = renderApp('/')

    const retry = await screen.findByRole('button', { name: /try again/i })
    fail = false
    await user.click(retry)

    expect(await screen.findByTestId('kpi-revenue-value')).toHaveTextContent('$99.50')
  })

  it('is not shown to a User without dashboard.view', async () => {
    signedInAs({ ...finance, permissions: ['orders.view'] })
    const ranges = dashboardServer(() => HttpResponse.json({ data: aDashboard() }))
    renderApp('/')

    expect(await screen.findByTestId('current-role')).toHaveTextContent('Finance')
    expect(screen.queryByText('Executive dashboard')).not.toBeInTheDocument()
    expect(ranges).toHaveLength(0)
  })
})
