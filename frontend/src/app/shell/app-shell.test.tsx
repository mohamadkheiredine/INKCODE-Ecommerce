import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { adminUser, apiError } from '@/test/msw/handlers'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'

const mainNavigation = () => screen.findByRole('navigation', { name: 'Main navigation' })

describe('The app shell', () => {
  afterEach(() => {
    vi.useRealTimers()
    window.innerWidth = 1024
  })

  it('shows the signed-in User the menu entries their Role may use', async () => {
    signedInAs(adminUser)
    renderApp('/')

    const home = within(await mainNavigation()).getByRole('link', { name: 'Home' })
    expect(home).toHaveAttribute('aria-current', 'page')
    expect(await screen.findByRole('heading', { name: 'Hello, Ahmed Ali' })).toBeInTheDocument()
  })

  it('builds breadcrumbs and the tab title from the route', async () => {
    signedInAs(adminUser)
    renderApp('/profile')

    const breadcrumbs = await screen.findByRole('navigation', { name: 'Breadcrumb' })
    expect(within(breadcrumbs).getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/')
    expect(within(breadcrumbs).getByText('Profile')).toHaveAttribute('aria-current', 'page')
    await waitFor(() => expect(document.title).toBe('Profile · Inkcode Commerce'))
  })

  it('opens the profile from the user menu', async () => {
    signedInAs(adminUser)
    const { user, router } = renderApp('/')

    await user.click(await screen.findByRole('button', { name: 'Open the user menu' }))
    expect(await screen.findByRole('menu')).toHaveTextContent('admin@inkcode.test')
    await user.click(screen.getByRole('menuitem', { name: 'Profile' }))

    expect(await screen.findByRole('heading', { name: 'Profile', level: 1 })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/profile')
  })

  it('mirrors the whole layout in Arabic and keeps the choice on the profile', async () => {
    const { savedPreferences } = signedInAs(adminUser)
    const { user } = renderApp('/')

    await user.click(await screen.findByRole('button', { name: 'Switch to العربية' }))

    expect(await screen.findByRole('navigation', { name: 'التنقل الرئيسي' })).toBeInTheDocument()
    expect(document.documentElement).toHaveAttribute('dir', 'rtl')
    expect(document.documentElement).toHaveAttribute('lang', 'ar')
    await waitFor(() => expect(savedPreferences).toEqual([{ locale: 'ar' }]))
  })

  it('keeps the theme on the profile and confirms it when the User switches it', async () => {
    const { savedPreferences } = signedInAs(adminUser)
    const { user } = renderApp('/')

    await user.click(await screen.findByRole('button', { name: 'Switch to dark theme' }))

    expect(document.documentElement).toHaveClass('dark')
    await waitFor(() => expect(savedPreferences).toEqual([{ theme: 'dark' }]))
    expect(await screen.findByText('Preferences saved.')).toBeInTheDocument()
  })

  it('applies the language and theme saved on the profile when the session starts', async () => {
    signedInAs({ ...adminUser, preferences: { locale: 'ar', theme: 'dark' } })
    renderApp('/')

    expect(await screen.findByRole('heading', { name: 'أهلاً، Ahmed Ali' })).toBeInTheDocument()
    expect(document.documentElement).toHaveAttribute('dir', 'rtl')
    expect(document.documentElement).toHaveClass('dark')
  })

  it('says so when a switched preference could not be kept on the profile', async () => {
    signedInAs(adminUser)
    server.use(
      http.patch('*/api/v1/auth/me/preferences', () =>
        apiError(500, 'server_error', 'Server error'),
      ),
    )
    const { user } = renderApp('/')

    await user.click(await screen.findByRole('button', { name: 'Switch to dark theme' }))

    expect(
      await screen.findByText("Your choice applies here, but we couldn't save it to your profile."),
    ).toBeInTheDocument()
    expect(document.documentElement).toHaveClass('dark')
  })

  it('writes dates with Arabic month names and Western digits', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-10-19T09:00:00'))
    signedInAs({ ...adminUser, preferences: { locale: 'ar', theme: 'light' } })
    renderApp('/')

    expect(await screen.findByTestId('today')).toHaveTextContent('اليوم الاثنين، 19 أكتوبر 2026')
  })

  it('turns the sidebar into a drawer on phones', async () => {
    window.innerWidth = 375
    signedInAs(adminUser)
    const { user } = renderApp('/profile')
    await screen.findByRole('heading', { name: 'Profile', level: 1 })

    expect(screen.queryByRole('navigation', { name: 'Main navigation' })).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Toggle sidebar' }))

    const drawer = await screen.findByRole('dialog')
    await user.click(within(drawer).getByRole('link', { name: 'Home' }))

    expect(await screen.findByRole('heading', { name: 'Hello, Ahmed Ali' })).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('offers a way home from a page that does not exist', async () => {
    signedInAs(adminUser)
    const { user, router } = renderApp('/no-such-page')

    expect(
      await screen.findByText('Page not found', { selector: '[data-slot=empty-title]' }),
    ).toBeInTheDocument()
    await user.click(screen.getByRole('link', { name: 'Go to the home page' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/'))
  })

  it('offers a retry when the signed-in User cannot be loaded', async () => {
    let failing = true
    server.use(
      http.get('*/api/v1/auth/me', () =>
        failing
          ? apiError(500, 'server_error', 'Server error')
          : HttpResponse.json({ data: adminUser }),
      ),
    )
    const { user } = renderApp('/')

    await user.click(await screen.findByRole('button', { name: 'Try again' }))
    failing = false
    await user.click(await screen.findByRole('button', { name: 'Try again' }))

    expect(await screen.findByRole('heading', { name: 'Hello, Ahmed Ali' })).toBeInTheDocument()
  })
})
