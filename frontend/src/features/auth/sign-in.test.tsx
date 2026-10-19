import { act, screen, waitFor, within } from '@testing-library/react'
import { focusManager } from '@tanstack/react-query'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import { adminUser, apiError, demoAccounts, viewerUser } from '@/test/msw/handlers'
import { server } from '@/test/msw/server'
import { renderApp } from '@/test/render-app'
import type { CurrentUser } from './types'

const accounts: Record<string, { password: string; user: CurrentUser }> = {
  'admin@inkcode.test': { password: 'password', user: adminUser },
  'viewer@inkcode.test': { password: 'password', user: viewerUser },
}

/** A small stand-in for the API's sign-in rules: right password → the User, else 422. */
function acceptSignIns() {
  let signedIn: CurrentUser | null = null
  server.use(
    http.post('*/api/v1/auth/login', async ({ request }) => {
      const body = (await request.json()) as { email: string; password: string }
      const account = accounts[body.email]
      if (!account || account.password !== body.password) {
        return apiError(422, 'validation_failed', 'These credentials do not match our records.', {
          email: ['These credentials do not match our records.'],
        })
      }
      signedIn = account.user
      return HttpResponse.json({ data: account.user })
    }),
    http.get('*/api/v1/auth/me', () =>
      signedIn
        ? HttpResponse.json({ data: signedIn })
        : apiError(401, 'unauthenticated', 'Unauthenticated.'),
    ),
    http.post('*/api/v1/auth/logout', () => {
      signedIn = null
      return new HttpResponse(null, { status: 204 })
    }),
  )
}

async function signInWithForm(
  user: ReturnType<typeof renderApp>['user'],
  email: string,
  password: string,
) {
  await user.type(await screen.findByLabelText('Email'), email)
  await user.type(screen.getByLabelText('Password'), password)
  await user.click(screen.getByRole('button', { name: 'Sign in' }))
}

describe('Signing in', () => {
  it('shows the signed-in User their name and Role', async () => {
    acceptSignIns()
    const { user } = renderApp('/sign-in')

    await signInWithForm(user, 'admin@inkcode.test', 'password')

    expect(await screen.findByRole('heading', { name: 'Hello, Ahmed Ali' })).toBeInTheDocument()
    expect(screen.getByTestId('current-role')).toHaveTextContent('Admin')
  })

  it('sends a signed-out visitor to sign-in and back to the page they asked for', async () => {
    acceptSignIns()
    const { user, router } = renderApp('/orders?status=pending')

    await screen.findByRole('heading', { name: 'Welcome back' })
    expect(router.state.location.pathname).toBe('/sign-in')

    await signInWithForm(user, 'admin@inkcode.test', 'password')

    await waitFor(() => expect(router.state.location.pathname).toBe('/orders'))
    expect(router.state.location.search).toBe('?status=pending')
  })

  it('never sends a User to another site after signing in', async () => {
    acceptSignIns()
    const { user, router } = renderApp('/sign-in?redirect=//evil.example/steal')

    await signInWithForm(user, 'admin@inkcode.test', 'password')

    await screen.findByRole('heading', { name: 'Hello, Ahmed Ali' })
    expect(router.state.location.pathname).toBe('/')
  })

  it('checks the email and password before calling the API', async () => {
    const { user } = renderApp('/sign-in')

    await user.click(await screen.findByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('Enter your email address.')).toBeInTheDocument()
    expect(screen.getByText('Enter your password.')).toBeInTheDocument()

    await user.type(screen.getByLabelText('Email'), 'not-an-email')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('Enter a valid email address.')).toBeInTheDocument()
  })

  it('shows the API message under the email field when the credentials are wrong', async () => {
    acceptSignIns()
    const { user } = renderApp('/sign-in')

    await signInWithForm(user, 'admin@inkcode.test', 'wrong-password')

    expect(
      await screen.findByText('These credentials do not match our records.'),
    ).toBeInTheDocument()
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true')
  })

  it('explains the lockout when sign-in is rate-limited', async () => {
    server.use(
      http.post('*/api/v1/auth/login', () =>
        apiError(
          429,
          'too_many_requests',
          'Too many sign-in attempts. Please try again in 58 seconds.',
        ),
      ),
    )
    const { user } = renderApp('/sign-in')

    await signInWithForm(user, 'admin@inkcode.test', 'password')

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Too many sign-in attempts. Please try again in 58 seconds.',
    )
  })

  it('tells the User when the server cannot be reached', async () => {
    server.use(http.post('*/api/v1/auth/login', () => HttpResponse.error()))
    const { user } = renderApp('/sign-in')

    await signInWithForm(user, 'admin@inkcode.test', 'password')

    expect(await screen.findByRole('alert')).toHaveTextContent("Can't reach the server.")
  })

  it('locks the form and shows progress while signing in', async () => {
    server.use(
      http.post('*/api/v1/auth/login', async () => {
        await new Promise(() => {}) // never settles: the request stays in flight
        return HttpResponse.json({ data: adminUser })
      }),
    )
    const { user } = renderApp('/sign-in')

    await signInWithForm(user, 'admin@inkcode.test', 'password')

    const button = await screen.findByRole('button', { name: 'Signing in…' })
    expect(button).toBeDisabled()
    expect(screen.getByLabelText('Email')).toBeDisabled()
  })
})

describe('Demo accounts', () => {
  it('offers a one-click sign-in for each Role in demo mode', async () => {
    acceptSignIns()
    server.use(
      http.get('*/api/v1/auth/demo-accounts', () => HttpResponse.json({ data: demoAccounts })),
    )
    const { user } = renderApp('/sign-in')

    const demo = await screen.findByRole('region', { name: 'Demo accounts' })
    expect(within(demo).getAllByRole('button')).toHaveLength(7)

    await user.click(within(demo).getByRole('button', { name: 'Sign in as Viewer' }))

    expect(
      await screen.findByRole('heading', { name: 'Hello, Youssef Nasser' }),
    ).toBeInTheDocument()
    expect(screen.getByTestId('current-role')).toHaveTextContent('Viewer')
  })

  it('shows no demo accounts outside demo mode', async () => {
    renderApp('/sign-in')

    await screen.findByRole('heading', { name: 'Welcome back' })
    await waitFor(() =>
      expect(screen.queryByRole('region', { name: 'Demo accounts' })).not.toBeInTheDocument(),
    )
  })
})

describe('The guarded app', () => {
  it('shows a loading skeleton while it checks the session', async () => {
    server.use(
      http.get('*/api/v1/auth/me', async () => {
        await new Promise(() => {}) // never settles: the request stays in flight
        return HttpResponse.json({ data: adminUser })
      }),
    )
    renderApp('/')

    expect(await screen.findByRole('status')).toHaveTextContent('Loading…')
  })

  it('offers a retry when the session check fails, and recovers', async () => {
    server.use(http.get('*/api/v1/auth/me', () => apiError(500, 'server_error', 'Server error')))
    const { user } = renderApp('/')

    expect(await screen.findByText('Something went wrong')).toBeInTheDocument()

    server.use(http.get('*/api/v1/auth/me', () => HttpResponse.json({ data: adminUser })))
    await user.click(screen.getByRole('button', { name: 'Try again' }))

    expect(await screen.findByRole('heading', { name: 'Hello, Ahmed Ali' })).toBeInTheDocument()
  })

  it('returns to sign-in when the session ends while the User is away from the tab', async () => {
    acceptSignIns()
    const { user, router } = renderApp('/sign-in')
    await signInWithForm(user, 'admin@inkcode.test', 'password')
    await screen.findByRole('heading', { name: 'Hello, Ahmed Ali' })

    // The session expires (or the User signs out in another tab)…
    server.use(
      http.get('*/api/v1/auth/me', () => apiError(401, 'unauthenticated', 'Unauthenticated.')),
    )
    // …and the User comes back to this tab.
    act(() => {
      focusManager.setFocused(false)
      focusManager.setFocused(true)
    })

    expect(await screen.findByRole('heading', { name: 'Welcome back' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/sign-in')
  })

  it('treats signing out of an already-expired session as signed out', async () => {
    acceptSignIns()
    const { user } = renderApp('/sign-in')
    await signInWithForm(user, 'admin@inkcode.test', 'password')
    await screen.findByRole('heading', { name: 'Hello, Ahmed Ali' })
    server.use(
      http.post('*/api/v1/auth/logout', () => apiError(401, 'unauthenticated', 'Unauthenticated.')),
      http.get('*/api/v1/auth/me', () => apiError(401, 'unauthenticated', 'Unauthenticated.')),
    )

    await user.click(screen.getByRole('button', { name: 'Sign out' }))

    expect(await screen.findByRole('heading', { name: 'Welcome back' })).toBeInTheDocument()
  })

  it('keeps the User signed in and says so when signing out fails', async () => {
    acceptSignIns()
    const { user, router } = renderApp('/sign-in')
    await signInWithForm(user, 'admin@inkcode.test', 'password')
    await screen.findByRole('heading', { name: 'Hello, Ahmed Ali' })
    server.use(
      http.post('*/api/v1/auth/logout', () => apiError(500, 'server_error', 'Server error')),
    )

    await user.click(screen.getByRole('button', { name: 'Sign out' }))

    expect(
      await screen.findByText("We couldn't sign you out. Please try again."),
    ).toBeInTheDocument()
    expect(screen.queryByText("You've been signed out.")).not.toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
    expect(screen.getByRole('heading', { name: 'Hello, Ahmed Ali' })).toBeInTheDocument()
  })

  it('signs the User out and returns to sign-in', async () => {
    acceptSignIns()
    const { user, router } = renderApp('/sign-in')
    await signInWithForm(user, 'admin@inkcode.test', 'password')
    await screen.findByRole('heading', { name: 'Hello, Ahmed Ali' })

    await user.click(screen.getByRole('button', { name: 'Sign out' }))

    expect(await screen.findByRole('heading', { name: 'Welcome back' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/sign-in')
  })
})

describe('Language and theme', () => {
  it('switches the whole interface to Arabic, right to left', async () => {
    const { user } = renderApp('/sign-in')

    await user.click(await screen.findByRole('button', { name: 'Switch to العربية' }))

    expect(await screen.findByRole('heading', { name: 'مرحباً بعودتك' })).toBeInTheDocument()
    expect(document.documentElement).toHaveAttribute('dir', 'rtl')
    expect(document.documentElement).toHaveAttribute('lang', 'ar')
  })

  it('asks the API for Arabic messages once the interface is in Arabic', async () => {
    server.use(
      http.post('*/api/v1/auth/login', ({ request }) =>
        request.headers.get('accept-language') === 'ar'
          ? apiError(422, 'validation_failed', 'بيانات الدخول هذه لا تطابق سجلاتنا.', {
              email: ['بيانات الدخول هذه لا تطابق سجلاتنا.'],
            })
          : apiError(422, 'validation_failed', 'These credentials do not match our records.', {
              email: ['These credentials do not match our records.'],
            }),
      ),
    )
    const { user } = renderApp('/sign-in')
    await user.click(await screen.findByRole('button', { name: 'Switch to العربية' }))

    await user.type(await screen.findByLabelText('البريد الإلكتروني'), 'admin@inkcode.test')
    await user.type(screen.getByLabelText('كلمة المرور'), 'wrong')
    await user.click(screen.getByRole('button', { name: 'تسجيل الدخول' }))

    expect(await screen.findByText('بيانات الدخول هذه لا تطابق سجلاتنا.')).toBeInTheDocument()
  })

  it('switches between light and dark themes and remembers the choice', async () => {
    const { user } = renderApp('/sign-in')

    await user.click(await screen.findByRole('button', { name: 'Switch to dark theme' }))

    expect(document.documentElement).toHaveClass('dark')
    expect(localStorage.getItem('inkcode.theme')).toBe('dark')

    await user.click(screen.getByRole('button', { name: 'Switch to light theme' }))
    expect(document.documentElement).not.toHaveClass('dark')
  })
})
