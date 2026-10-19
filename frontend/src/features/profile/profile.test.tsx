import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import { adminUser, apiError } from '@/test/msw/handlers'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'
import type { ChangePasswordValues } from './schemas/change-password-schema'

type User = ReturnType<typeof renderApp>['user']

/** The API's rules for changing a password: the right current one, else 422. */
function acceptPasswordChanges(currentPassword = 'Temporary-pass-1') {
  const sent: ChangePasswordValues[] = []
  server.use(
    http.put('*/api/v1/auth/me/password', async ({ request }) => {
      const body = (await request.json()) as ChangePasswordValues
      sent.push(body)
      if (body.current_password !== currentPassword) {
        return apiError(422, 'validation_failed', 'The password is incorrect.', {
          current_password: ['The password is incorrect.'],
        })
      }
      return new HttpResponse(null, { status: 204 })
    }),
  )
  return { sent }
}

async function openProfile() {
  signedInAs(adminUser)
  const view = renderApp('/profile')
  await screen.findByRole('heading', { name: 'Profile', level: 1 })
  return view
}

async function fillPasswordForm(user: User, current: string, next: string, confirmation = next) {
  if (current) await user.type(screen.getByLabelText('Current password'), current)
  if (next) await user.type(screen.getByLabelText('New password'), next)
  if (confirmation) await user.type(screen.getByLabelText('Confirm new password'), confirmation)
  await user.click(screen.getByRole('button', { name: 'Change password' }))
}

describe('Changing the password', () => {
  it('replaces the password once the current one is confirmed', async () => {
    const { sent } = acceptPasswordChanges()
    const { user } = await openProfile()

    await fillPasswordForm(user, 'Temporary-pass-1', 'My-new-Secret-42')

    expect(
      await screen.findByText("Password changed. You've been signed out on your other devices."),
    ).toBeInTheDocument()
    expect(sent).toEqual([
      {
        current_password: 'Temporary-pass-1',
        password: 'My-new-Secret-42',
        password_confirmation: 'My-new-Secret-42',
      },
    ])
    await waitFor(() => expect(screen.getByLabelText('Current password')).toHaveValue(''))
  })

  it('shows each strength rule turning green while the User types', async () => {
    const { user } = await openProfile()
    const rules = () => within(screen.getByText('Your new password needs').parentElement!)

    await user.type(screen.getByLabelText('New password'), 'short')
    expect(rules().getByText('At least 10 characters').parentElement).toHaveTextContent(
      'Not met yet',
    )

    await user.type(screen.getByLabelText('New password'), '-Enough-42')
    expect(rules().getByText('At least 10 characters').parentElement).toHaveTextContent('(Met)')
    expect(rules().getByText('Upper- and lower-case letters').parentElement).toHaveTextContent(
      '(Met)',
    )
    expect(rules().getByText('At least one number').parentElement).toHaveTextContent('(Met)')
  })

  it('checks the form before calling the API', async () => {
    const { sent } = acceptPasswordChanges()
    const { user } = await openProfile()

    await user.click(screen.getByRole('button', { name: 'Change password' }))
    expect(await screen.findByText('Enter your current password.')).toBeInTheDocument()
    expect(screen.getByText('Enter a new password.')).toBeInTheDocument()
    expect(screen.getByText('Enter the new password again.')).toBeInTheDocument()

    await fillPasswordForm(user, 'Temporary-pass-1', 'all-lowercase-42', 'Something-else-42')
    expect(await screen.findByText('Choose a password that meets every rule.')).toBeInTheDocument()
    expect(screen.getByText("The passwords don't match.")).toBeInTheDocument()
    expect(sent).toEqual([])
  })

  it('refuses keeping the current password as the new one', async () => {
    const { sent } = acceptPasswordChanges()
    const { user } = await openProfile()

    await fillPasswordForm(user, 'Temporary-pass-1', 'Temporary-pass-1')

    expect(
      await screen.findByText('Choose a password different from your current one.'),
    ).toBeInTheDocument()
    expect(sent).toEqual([])
  })

  it('shows the API answer under the current password when it is wrong', async () => {
    acceptPasswordChanges()
    const { user } = await openProfile()

    await fillPasswordForm(user, 'not-my-password', 'My-new-Secret-42')

    expect(await screen.findByText('The password is incorrect.')).toBeInTheDocument()
    expect(screen.getByLabelText('Current password')).toHaveAttribute('aria-invalid', 'true')
  })

  it('explains when too many attempts were made', async () => {
    server.use(
      http.put('*/api/v1/auth/me/password', () =>
        apiError(429, 'too_many_requests', 'Too Many Attempts.'),
      ),
    )
    const { user } = await openProfile()

    await fillPasswordForm(user, 'Temporary-pass-1', 'My-new-Secret-42')

    expect(await screen.findByRole('alert')).toHaveTextContent('Too Many Attempts.')
  })
})

describe('Profile preferences', () => {
  it('applies a chosen language and theme at once and keeps them on the profile', async () => {
    const { savedPreferences } = signedInAs(adminUser)
    const { user } = renderApp('/profile')

    await user.click(await screen.findByRole('radio', { name: 'Dark' }))
    expect(await screen.findByText('Preferences saved.')).toBeInTheDocument()
    expect(document.documentElement).toHaveClass('dark')

    await user.click(screen.getByRole('radio', { name: 'العربية' }))
    expect(await screen.findByText('تم حفظ التفضيلات.')).toBeInTheDocument()
    expect(document.documentElement).toHaveAttribute('dir', 'rtl')

    expect(savedPreferences).toEqual([{ theme: 'dark' }, { locale: 'ar' }])
  })

  it('tells the User when the preferences could not be saved', async () => {
    signedInAs(adminUser)
    server.use(
      http.patch('*/api/v1/auth/me/preferences', () =>
        apiError(500, 'server_error', 'Server error'),
      ),
    )
    const { user } = renderApp('/profile')

    await user.click(await screen.findByRole('radio', { name: 'Dark' }))

    expect(
      await screen.findByText("Your choice applies here, but we couldn't save it to your profile."),
    ).toBeInTheDocument()
  })
})
