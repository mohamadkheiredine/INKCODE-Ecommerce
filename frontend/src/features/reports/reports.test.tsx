import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { delay } from 'msw'
import { HttpResponse, http } from 'msw/http'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { apiError } from '@/test/msw/handlers'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'
import type { Report } from './types'

const finance: CurrentUser = {
  id: 6,
  name: 'Nadia Farouk',
  email: 'finance@inkcode.test',
  role: 'finance',
  permissions: ['dashboard.view', 'reports.view', 'reports.export'],
  preferences: { locale: 'en', theme: 'light' },
}

const viewer: CurrentUser = {
  ...finance,
  id: 7,
  email: 'viewer@inkcode.test',
  role: 'viewer',
  permissions: ['dashboard.view', 'reports.view'],
}

const range = {
  from: '2026-10-10',
  to: '2026-10-15',
  previous_from: '2026-10-04',
  previous_to: '2026-10-09',
}

function aSalesReport(): Report {
  return {
    report: 'sales',
    range,
    columns: [
      { key: 'period', type: 'date' },
      { key: 'orders', type: 'integer' },
      { key: 'revenue', type: 'money' },
    ],
    summary: [
      { key: 'revenue', type: 'money', value: '99.50', previous: '50.00', change: 99 },
      { key: 'orders', type: 'integer', value: 2, previous: 0, change: null },
    ],
    rows: [
      { period: '2026-10-10', orders: 1, revenue: '45.00' },
      { period: '2026-10-11', orders: 1, revenue: '54.50' },
    ],
  }
}

function aReturnsReport(): Report {
  return {
    report: 'returns',
    range,
    columns: [
      { key: 'reason', type: 'enum' },
      { key: 'returns', type: 'integer' },
      { key: 'share', type: 'percent' },
    ],
    summary: [
      { key: 'return_rate', type: 'percent', value: '50.0', previous: '0.0', change: null },
    ],
    rows: [{ reason: 'damaged', returns: 2, share: '100.0' }],
  }
}

/** Answers each report request and records what the screen asked for. */
function reportsServer(respond: (name: string) => Response | Promise<Response>) {
  const requests: URL[] = []
  server.use(
    http.get('*/api/v1/reports/:report', ({ request, params }) => {
      requests.push(new URL(request.url))
      return respond(String(params.report))
    }),
  )
  return requests
}

afterEach(() => vi.restoreAllMocks())

describe('reports', () => {
  it('shows the Sales report: summary against the previous period and the rows', async () => {
    signedInAs(finance)
    const requests = reportsServer(() => HttpResponse.json({ data: aSalesReport() }))
    renderApp('/reports?range=custom&from=2026-10-10&to=2026-10-15')

    expect(await screen.findByTestId('figure-revenue-value')).toHaveTextContent('$99.50')
    expect(screen.getByTestId('figure-revenue')).toHaveTextContent('+99%')
    expect(screen.getByTestId('figure-orders')).toHaveTextContent(/nothing in the previous period/i)
    const table = within(screen.getByTestId('report-table'))
    expect(table.getByRole('columnheader', { name: 'Revenue' })).toBeVisible()
    expect(table.getByText('$54.50')).toBeVisible()
    expect(table.getByText('Oct 10, 2026')).toBeVisible()
    expect(requests[0]?.pathname).toMatch(/\/reports\/sales$/)
    expect(requests[0]?.searchParams.get('from')).toBe('2026-10-10')
    expect(requests[0]?.searchParams.get('interval')).toBe('day')
  })

  it('switches report and Sales interval, asking the API for each', async () => {
    signedInAs(finance)
    const requests = reportsServer((name) =>
      HttpResponse.json({ data: name === 'returns' ? aReturnsReport() : aSalesReport() }),
    )
    const user = userEvent.setup()
    renderApp('/reports')

    await screen.findByTestId('report-table')
    await user.click(screen.getByRole('radio', { name: 'Month' }))
    await waitFor(() => expect(requests.at(-1)?.searchParams.get('interval')).toBe('month'))

    await user.click(screen.getByRole('tab', { name: 'Returns' }))
    expect(await screen.findByText('Damaged')).toBeVisible()
    expect(screen.getByTestId('figure-return_rate-value')).toHaveTextContent('50%')
    expect(requests.at(-1)?.pathname).toMatch(/\/reports\/returns$/)
    expect(requests.at(-1)?.searchParams.has('interval')).toBe(false)
  })

  it('shows a skeleton while loading, then an empty state with an action', async () => {
    signedInAs(finance)
    reportsServer(async () => {
      await delay(50)
      return HttpResponse.json({ data: { ...aSalesReport(), rows: [] } })
    })
    renderApp('/reports')

    expect(await screen.findByTestId('report-skeleton')).toBeInTheDocument()
    expect(await screen.findByText(/nothing to report for these days/i)).toBeVisible()
    expect(screen.getByRole('button', { name: /show this month/i })).toBeVisible()
  })

  it('shows an error with retry', async () => {
    signedInAs(finance)
    let calls = 0
    reportsServer(() => {
      calls += 1
      return calls === 1
        ? apiError(500, 'server_error', 'Server error')
        : HttpResponse.json({ data: aSalesReport() })
    })
    const user = userEvent.setup()
    renderApp('/reports')

    await user.click(await screen.findByRole('button', { name: /try again/i }))
    expect(await screen.findByTestId('report-table')).toBeVisible()
  })

  it('downloads the report as Excel for a Role with reports.export', async () => {
    signedInAs(finance)
    reportsServer(() => HttpResponse.json({ data: aSalesReport() }))
    const exports: URL[] = []
    server.use(
      http.get('*/api/v1/reports/:report/export', ({ request }) => {
        exports.push(new URL(request.url))
        return new HttpResponse('xlsx', {
          headers: {
            'Content-Type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition':
              'attachment; filename="sales-report-2026-10-10-to-2026-10-15.xlsx"',
          },
        })
      }),
    )
    URL.createObjectURL = vi.fn(() => 'blob:report')
    URL.revokeObjectURL = vi.fn()
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
    const user = userEvent.setup()
    renderApp('/reports')

    await screen.findByTestId('report-table')
    await user.click(screen.getByRole('button', { name: 'Export Excel' }))

    expect(await screen.findByText(/your report has downloaded/i)).toBeVisible()
    expect(exports[0]?.searchParams.get('format')).toBe('xlsx')
    expect(click).toHaveBeenCalledOnce()
  })

  it('hides the export buttons from a Role without reports.export', async () => {
    signedInAs(viewer)
    reportsServer(() => HttpResponse.json({ data: aSalesReport() }))
    renderApp('/reports')

    await screen.findByTestId('report-table')
    expect(screen.queryByRole('button', { name: /export/i })).not.toBeInTheDocument()
  })
})
