import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { adminUser, apiError } from '@/test/msw/handlers'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { aUser, rolesMatrix, userStore } from '@/test/msw/users'
import { renderApp } from '@/test/render-app'

async function open(path: string, user: CurrentUser = adminUser) {
  signedInAs(user)
  const view = renderApp(path)
  // The first visit loads the page's code, which can take a moment on a busy machine.
  await screen.findByRole('heading', { level: 1 }, { timeout: 10_000 })
  return view
}

describe('the Users list', () => {
  it('shows each User with their Role and status and filters by status in the URL', async () => {
    userStore([
      aUser({ id: adminUser.id, name: adminUser.name, role: 'admin' }),
      aUser({ name: 'Lina Saleh', role: 'sales' }),
      aUser({ name: 'Karim Nassar', role: 'support', is_active: false }),
    ])
    const { user, router } = await open('/users')

    const karim = (await screen.findByText('Karim Nassar')).closest('tr')!
    // The Role shows in its column, and under the name on phones.
    expect(within(karim).getAllByText('Support')).not.toHaveLength(0)
    expect(within(karim).getByText('Deactivated')).toBeInTheDocument()
    // The Admin can't deactivate themselves, so their own row offers no such button.
    const me = screen.getByText('(you)').closest('tr')!
    expect(within(me).queryByRole('button', { name: /deactivate/i })).not.toBeInTheDocument()

    await user.selectOptions(screen.getByRole('combobox', { name: 'Status' }), 'Active')

    await waitFor(() => expect(screen.queryByText('Karim Nassar')).not.toBeInTheDocument())
    expect(router.state.location.search).toBe('?filter%5Bstatus%5D=active')
  }, 20_000)

  it('offers to add a User when none match', async () => {
    userStore([])
    await open('/users')
    expect(await screen.findByText('No Users found')).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'New User' })).toHaveLength(2)
  })

  it('shows an error with a retry when the list fails to load', async () => {
    let fail = true
    server.use(
      http.get('*/api/v1/users', () =>
        fail
          ? apiError(500, 'server_error', 'Something went wrong.')
          : HttpResponse.json({
              data: [aUser({ name: 'Lina Saleh' })],
              meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
            }),
      ),
    )
    const { user } = await open('/users')

    const retry = await screen.findByRole('button', { name: 'Try again' })
    fail = false
    await user.click(retry)

    expect(await screen.findByText('Lina Saleh')).toBeInTheDocument()
  })

  it('deactivates a User', async () => {
    userStore([aUser({ name: 'Lina Saleh' })])
    const { user } = await open('/users')

    await user.click(await screen.findByRole('button', { name: 'Deactivate Lina Saleh' }))

    const reactivate = await screen.findByRole('button', { name: 'Reactivate Lina Saleh' })
    expect(within(reactivate.closest('tr')!).getByText('Deactivated')).toBeInTheDocument()
  })
})

describe('adding a User', () => {
  it('checks the details and the password strength, then shows the API refusal on its field', async () => {
    const store = userStore([aUser({ email: 'taken@inkcode.test' })])
    const { user } = await open('/users')
    await user.click((await screen.findAllByRole('button', { name: 'New User' }))[0]!)
    const dialog = await screen.findByRole('dialog', { name: 'New User' })

    await user.type(within(dialog).getByLabelText('Name'), 'Lina Saleh')
    await user.type(within(dialog).getByLabelText('Email'), 'taken@inkcode.test')
    await user.type(within(dialog).getByLabelText('Temporary password'), 'short')
    await user.click(within(dialog).getByRole('button', { name: 'Add User' }))
    expect(
      await within(dialog).findByText(
        'Use at least 10 characters with upper and lower case letters and a number.',
      ),
    ).toBeInTheDocument()
    expect(store.bodies).toHaveLength(0)

    await user.clear(within(dialog).getByLabelText('Temporary password'))
    await user.type(within(dialog).getByLabelText('Temporary password'), 'Temporary-pass-1')
    await user.type(within(dialog).getByLabelText('Confirm password'), 'Temporary-pass-1')
    await user.selectOptions(within(dialog).getByLabelText('Role'), 'Finance')
    await user.click(within(dialog).getByRole('button', { name: 'Add User' }))

    expect(await within(dialog).findByText('The email has already been taken.')).toBeInTheDocument()
    expect(store.bodies[0]).toMatchObject({ name: 'Lina Saleh', role: 'finance' })
  }, 20_000)
})

describe('the Roles & permissions matrix', () => {
  it('lists every permission per Role, as the API returns them', async () => {
    rolesMatrix([
      { key: 'admin', permissions: ['catalog.view', 'users.manage'] },
      { key: 'viewer', permissions: ['catalog.view'] },
    ])
    await open('/roles')

    await screen.findByText('Manage Users')
    const row = screen.getByText('Manage Users').closest('tr')!
    expect(within(row).getByLabelText('Admin: allowed')).toBeInTheDocument()
    expect(within(row).getByLabelText('Viewer: not allowed')).toBeInTheDocument()
  })
})
