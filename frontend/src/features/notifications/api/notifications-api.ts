import { http } from '@/lib/api/client'
import type { AppNotification, NotificationPage } from '../types'

/** How many notifications the bell loads at a time. */
export const NOTIFICATIONS_PER_PAGE = 10

export const notificationKeys = {
  all: ['notifications'] as const,
  /** Messages arrive already translated, so each language has its own copy. */
  list: (language: string) => [...notificationKeys.all, 'list', language] as const,
}

export const notificationsApi = {
  /** One page of the signed-in User's notifications, newest first. */
  async list(page: number): Promise<NotificationPage> {
    const { data } = await http.get<NotificationPage>('/notifications', {
      params: { page, per_page: NOTIFICATIONS_PER_PAGE },
    })
    return data
  },

  async markAsRead(id: string): Promise<AppNotification> {
    const { data } = await http.post<{ data: AppNotification }>(
      `/notifications/${encodeURIComponent(id)}/read`,
    )
    return data.data
  },

  async markAllAsRead(): Promise<void> {
    await http.post('/notifications/read-all')
  },
}
