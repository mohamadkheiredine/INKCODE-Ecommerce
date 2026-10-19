import { HttpResponse, http } from 'msw/http'
import type { AppNotification } from '@/features/notifications/types'
import { server } from './server'

/** A notification as the API sends it; unread, a minute old, unless the test says otherwise. */
export function aNotification(overrides: Partial<AppNotification> = {}): AppNotification {
  return {
    id: crypto.randomUUID(),
    type: 'welcome',
    message:
      'Welcome to Inkcode Commerce, Ahmed Ali. Your language and theme are kept on your profile.',
    link: '/profile',
    read_at: null,
    created_at: new Date(Date.now() - 60_000).toISOString(),
    ...overrides,
  }
}

/**
 * The signed-in User's notifications, kept and paged like the API keeps them (newest
 * first, marking one or all as read). Tests can add notifications later, as if another
 * part of the store had sent them, and read which requests arrived.
 */
export function notificationInbox(initial: AppNotification[] = []) {
  let notifications = [...initial]
  const requests: { method: string; url: URL; language: string | null }[] = []

  const record = (request: Request) =>
    requests.push({
      method: request.method,
      url: new URL(request.url),
      language: request.headers.get('Accept-Language'),
    })

  server.use(
    http.get('*/api/v1/notifications', ({ request }) => {
      record(request)
      const url = new URL(request.url)
      const perPage = Number(url.searchParams.get('per_page') ?? 15)
      const page = Number(url.searchParams.get('page') ?? 1)
      const sorted = [...notifications].sort((a, b) => b.created_at.localeCompare(a.created_at))

      return HttpResponse.json({
        data: sorted.slice((page - 1) * perPage, page * perPage),
        meta: {
          current_page: page,
          last_page: Math.max(1, Math.ceil(sorted.length / perPage)),
          per_page: perPage,
          total: sorted.length,
          unread_count: sorted.filter((notification) => notification.read_at === null).length,
        },
      })
    }),
    http.post('*/api/v1/notifications/read-all', ({ request }) => {
      record(request)
      const now = new Date().toISOString()
      notifications = notifications.map((n) => ({ ...n, read_at: n.read_at ?? now }))
      return new HttpResponse(null, { status: 204 })
    }),
    http.post('*/api/v1/notifications/:id/read', ({ request, params }) => {
      record(request)
      const found = notifications.find((notification) => notification.id === params.id)
      if (!found) {
        return HttpResponse.json(
          { message: 'The requested resource was not found.', code: 'not_found' },
          { status: 404 },
        )
      }
      found.read_at ??= new Date().toISOString()
      return HttpResponse.json({ data: found })
    }),
  )

  return {
    requests,
    /** How many times the bell has asked for the list so far. */
    listRequests: () => requests.filter((r) => r.method === 'GET').length,
    receive: (notification: AppNotification) => notifications.push(notification),
  }
}
