import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { notificationKeys, notificationsApi } from '../api/notifications-api'

/** How often the bell asks for news while the tab is visible. */
export const NOTIFICATIONS_POLL_INTERVAL = 30_000

/**
 * The signed-in User's notifications, newest first, page by page. They refresh every 30
 * seconds while the tab is visible (TanStack Query pauses the interval while it is
 * hidden) and as soon as the User comes back to the tab.
 */
export function useNotifications() {
  const { i18n } = useTranslation()

  return useInfiniteQuery({
    queryKey: notificationKeys.list(i18n.resolvedLanguage ?? 'en'),
    queryFn: ({ pageParam }) => notificationsApi.list(pageParam),
    initialPageParam: 1,
    getNextPageParam: ({ meta }) =>
      meta.current_page < meta.last_page ? meta.current_page + 1 : undefined,
    refetchInterval: NOTIFICATIONS_POLL_INTERVAL,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: true,
    staleTime: 0,
  })
}

export function useMarkNotificationAsRead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => notificationsApi.markAsRead(id),
    onSettled: () => queryClient.invalidateQueries({ queryKey: notificationKeys.all }),
  })
}

export function useMarkAllNotificationsAsRead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => notificationsApi.markAllAsRead(),
    onSettled: () => queryClient.invalidateQueries({ queryKey: notificationKeys.all }),
  })
}
