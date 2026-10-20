import { screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { anAuditEntry, auditStore } from '@/test/msw/audit'
import { adminUser } from '@/test/msw/handlers'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'

const admin: CurrentUser = { ...adminUser, permissions: ['dashboard.view', 'audit.view'] }

async function open(path = '/audit-log') {
  signedInAs(admin)
  const view = renderApp(path)
  await screen.findByRole('heading', { level: 1, name: 'Audit log' }, { timeout: 10_000 })
  return view
}

describe('the audit log', () => {
  it('lists entries with translated labels, links each record and filters by record type in the URL', async () => {
    auditStore([
      anAuditEntry({ record_label: 'Atlas Outdoor' }),
      anAuditEntry({
        record_type: 'customer',
        record_id: 8,
        record_label: 'Rana Haddad',
        action: 'created',
      }),
    ])
    const { user, router } = await open()

    const rana = (await screen.findByRole('link', { name: 'Rana Haddad' })).closest('tr')!
    expect(within(rana).getByText('Customer')).toBeInTheDocument()
    expect(within(rana).getByText('Created')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Rana Haddad' })).toHaveAttribute(
      'href',
      '/customers/8',
    )

    await user.selectOptions(screen.getByRole('combobox', { name: 'Record type' }), 'Brand')

    await waitFor(() => expect(screen.queryByText('Rana Haddad')).not.toBeInTheDocument())
    expect(router.state.location.search).toBe('?filter%5Brecord_type%5D=brand')
  }, 20_000)

  it('shows the values before and after a change side by side', async () => {
    auditStore([
      anAuditEntry({
        before: { name: 'Atlas', is_active: true, description: null },
        after: { name: 'Atlas Outdoor', is_active: false, description: 'Trail gear' },
      }),
    ])
    const { user } = await open()

    await user.click(await screen.findByRole('button', { name: 'View changes to Atlas Outdoor' }))
    const dialog = await screen.findByRole('dialog', { name: 'Updated · Atlas Outdoor' })
    const rows = within(dialog)
      .getAllByRole('row')
      .slice(1)
      .map((row) => row.textContent)
    expect(rows).toEqual(['NameAtlasAtlas Outdoor', 'ActiveYesNo', 'Description—Trail gear'])
  }, 20_000)

  it('explains an empty log and offers a next step', async () => {
    auditStore([])
    await open()
    expect(await screen.findByText('No changes recorded yet')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Go to Products' })).toBeInTheDocument()
  })

  it('offers to try again when the log cannot load', async () => {
    auditStore([], { fail: true })
    await open()
    expect(await screen.findByRole('button', { name: 'Try again' })).toBeInTheDocument()
  })
})
