import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { adminUser, apiError } from '@/test/msw/handlers'
import { aNotification, notificationInbox } from '@/test/msw/notifications'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'

const bell = (name: string | RegExp = /^Notifications/) => screen.findByRole('button', { name })

const setTabVisibility = (state: DocumentVisibilityState) => {
  Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => state })
  // Browsers fire it on the document and it bubbles to window, where TanStack Query listens.
  document.dispatchEvent(new Event('visibilitychange', { bubbles: true }))
}

describe('The notifications bell', () => {
  afterEach(() => {
    vi.useRealTimers()
    // Back to jsdom's own (always visible) state, quietly: an event here would refetch.
    Reflect.deleteProperty(document, 'visibilityState')
  })

  it('shows how many notifications are unread and lists them newest first', async () => {
    signedInAs(adminUser)
    notificationInbox([
      aNotification({ message: 'Older news', created_at: '2026-10-18T09:00:00Z' }),
      aNotification({ message: 'Latest news', created_at: '2026-10-19T09:00:00Z' }),
      aNotification({
        message: 'Already read',
        created_at: '2026-10-17T09:00:00Z',
        read_at: '2026-10-17T10:00:00Z',
      }),
    ])
    const { user } = renderApp('/')

    await user.click(await bell('Notifications, 2 unread'))

    const panel = await screen.findByRole('dialog', { name: 'Notifications' })
    const items = await within(panel).findAllByRole('listitem')
    const [latest, , alreadyRead] = items as [HTMLElement, HTMLElement, HTMLElement]
    expect(items.map((item) => item.textContent)).toEqual([
      expect.stringContaining('Latest news'),
      expect.stringContaining('Older news'),
      expect.stringContaining('Already read'),
    ])
    expect(within(latest).getByText('Unread')).toBeInTheDocument()
    expect(within(alreadyRead).queryByText('Unread')).not.toBeInTheDocument()
  })

  it('shows a skeleton while the notifications load', async () => {
    signedInAs(adminUser)
    server.use(http.get('*/api/v1/notifications', () => new Promise<never>(() => {})))
    const { user } = renderApp('/')

    await user.click(await bell())

    expect(
      await screen.findByRole('status', { name: 'Loading notifications…' }),
    ).toBeInTheDocument()
  })

  it('says when there is nothing new, and can check again', async () => {
    signedInAs(adminUser)
    const inbox = notificationInbox()
    const { user } = renderApp('/')

    await user.click(await bell('Notifications'))
    expect(await screen.findByText("You're all caught up")).toBeInTheDocument()

    inbox.receive(aNotification({ message: 'Fresh news' }))
    await user.click(screen.getByRole('button', { name: 'Check again' }))

    expect(await screen.findByText('Fresh news')).toBeInTheDocument()
    expect(await bell('Notifications, 1 unread')).toBeInTheDocument()
  })

  it('offers a retry when the notifications cannot be loaded', async () => {
    signedInAs(adminUser)
    let failing = true
    server.use(
      http.get('*/api/v1/notifications', () =>
        failing
          ? apiError(500, 'server_error', 'Server error')
          : HttpResponse.json({
              data: [aNotification({ message: 'Back again' })],
              meta: { current_page: 1, last_page: 1, per_page: 10, total: 1, unread_count: 1 },
            }),
      ),
    )
    const { user } = renderApp('/')

    await user.click(await bell())
    expect(await screen.findByText("Couldn't load your notifications")).toBeInTheDocument()
    failing = false
    await user.click(screen.getByRole('button', { name: 'Try again' }))

    expect(await screen.findByText('Back again')).toBeInTheDocument()
  })

  it('opens the record a notification is about and marks it as read', async () => {
    signedInAs(adminUser)
    const welcome = aNotification({ message: 'Welcome aboard', link: '/profile' })
    const inbox = notificationInbox([welcome, aNotification({ message: 'Other news' })])
    const { user, router } = renderApp('/')

    await user.click(await bell('Notifications, 2 unread'))
    await user.click(await screen.findByRole('link', { name: /Welcome aboard/ }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/profile'))
    expect(await bell('Notifications, 1 unread')).toBeInTheDocument()
    expect(inbox.requests.some((r) => r.url.pathname.endsWith(`/${welcome.id}/read`))).toBe(true)
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('marks one notification as read without leaving the page', async () => {
    signedInAs(adminUser)
    notificationInbox([aNotification({ message: 'Read me later' })])
    const { user, router } = renderApp('/')

    await user.click(await bell('Notifications, 1 unread'))
    await user.click(await screen.findByRole('button', { name: 'Mark as read' }))

    expect(await bell('Notifications')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Mark as read' })).not.toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
  })

  it('marks every notification as read at once and confirms it', async () => {
    signedInAs(adminUser)
    notificationInbox([aNotification(), aNotification(), aNotification()])
    const { user } = renderApp('/')

    await user.click(await bell('Notifications, 3 unread'))
    await user.click(await screen.findByRole('button', { name: 'Mark all as read' }))

    expect(await screen.findByText('All notifications marked as read.')).toBeInTheDocument()
    expect(await bell('Notifications')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Mark all as read' })).toBeDisabled()
  })

  it('says so when marking as read fails', async () => {
    signedInAs(adminUser)
    notificationInbox([aNotification()])
    server.use(
      http.post('*/api/v1/notifications/read-all', () =>
        apiError(500, 'server_error', 'Server error'),
      ),
    )
    const { user } = renderApp('/')

    await user.click(await bell('Notifications, 1 unread'))
    await user.click(await screen.findByRole('button', { name: 'Mark all as read' }))

    expect(
      await screen.findByText("Couldn't mark your notifications as read. Try again."),
    ).toBeInTheDocument()
    expect(await bell('Notifications, 1 unread')).toBeInTheDocument()
  })

  it('shows older notifications on request', async () => {
    signedInAs(adminUser)
    notificationInbox(
      Array.from({ length: 12 }, (_, i) =>
        aNotification({
          message: `News ${i + 1}`,
          created_at: new Date(Date.UTC(2026, 9, 1 + i)).toISOString(),
        }),
      ),
    )
    const { user } = renderApp('/')

    await user.click(await bell('Notifications, 12 unread'))
    const panel = await screen.findByRole('dialog', { name: 'Notifications' })
    expect(await within(panel).findAllByRole('listitem')).toHaveLength(10)
    await user.click(within(panel).getByRole('button', { name: 'Show older' }))

    await waitFor(() => expect(within(panel).getAllByRole('listitem')).toHaveLength(12))
    expect(within(panel).getByText('News 1')).toBeInTheDocument()
    expect(within(panel).queryByRole('button', { name: 'Show older' })).not.toBeInTheDocument()
  })

  it('caps the badge at 9+', async () => {
    signedInAs(adminUser)
    notificationInbox(Array.from({ length: 12 }, () => aNotification()))
    renderApp('/')

    expect(within(await bell('Notifications, 12 unread')).getByText('9+')).toBeInTheDocument()
  })

  it('checks for new notifications every 30 seconds', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    signedInAs(adminUser)
    const inbox = notificationInbox()
    renderApp('/')
    await waitFor(() => expect(inbox.listRequests()).toBe(1))

    inbox.receive(aNotification({ message: 'Low stock somewhere' }))
    await vi.advanceTimersByTimeAsync(25_000)
    expect(screen.getByRole('button', { name: 'Notifications' })).toBeInTheDocument()

    await vi.advanceTimersByTimeAsync(5_000)
    expect(await bell('Notifications, 1 unread')).toBeInTheDocument()
  })

  it('stops checking while the tab is hidden and catches up when it comes back', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    signedInAs(adminUser)
    const inbox = notificationInbox()
    renderApp('/')
    await waitFor(() => expect(inbox.listRequests()).toBe(1))
    const before = inbox.listRequests()

    setTabVisibility('hidden')
    inbox.receive(aNotification())
    await vi.advanceTimersByTimeAsync(95_000)
    expect(inbox.listRequests()).toBe(before)

    setTabVisibility('visible')
    expect(await bell('Notifications, 1 unread')).toBeInTheDocument()
  })

  it('reads the notifications in Arabic after the language switches', async () => {
    signedInAs(adminUser)
    const inbox = notificationInbox([aNotification()])
    const { user } = renderApp('/')
    await bell('Notifications, 1 unread')

    await user.click(screen.getByRole('button', { name: 'Switch to العربية' }))

    expect(await bell('الإشعارات، 1 غير مقروء')).toBeInTheDocument()
    await waitFor(() => expect(inbox.requests.at(-1)?.language).toBe('ar'))
  })
})
