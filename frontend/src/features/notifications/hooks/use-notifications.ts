import {
  type InfiniteData,
  type QueryClient,
  useInfiniteQuery,
  useMutation,
  useQueryClient,
} from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { asLanguage } from '@/lib/i18n'
import { notificationKeys, notificationsApi } from '../api/notifications-api'
import type { NotificationPage } from '../types'

/** How often the bell asks for news while the tab is visible. */
export const NOTIFICATIONS_POLL_INTERVAL = 30_000

/** The list in the interface language: the API sends each message already translated. */
function useNotificationsKey() {
  const { i18n } = useTranslation()
  return notificationKeys.list(asLanguage(i18n.resolvedLanguage))
}

/**
 * The signed-in User's notifications, newest first, page by page. They refresh every 30
 * seconds while the tab is visible (TanStack Query pauses the interval while it is
 * hidden) and as soon as the User comes back to the tab.
 */
export function useNotifications() {
  return useInfiniteQuery({
    queryKey: useNotificationsKey(),
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

/**
 * Forgets the older pages "Show older" loaded, so every later check asks for the latest
 * page only (an infinite query refetches each page it holds).
 */
export function useForgetOlderNotifications(): () => void {
  const queryClient = useQueryClient()
  const queryKey = useNotificationsKey()

  return () =>
    queryClient.setQueryData<InfiniteData<NotificationPage, number>>(queryKey, (data) =>
      data && data.pages.length > 1
        ? { pages: data.pages.slice(0, 1), pageParams: data.pageParams.slice(0, 1) }
        : data,
    )
}

/** After marking, the badge and the list come from the server again. */
const refreshNotifications = (queryClient: QueryClient) =>
  queryClient.invalidateQueries({ queryKey: notificationKeys.all })

export function useMarkNotificationAsRead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => notificationsApi.markAsRead(id),
    onSettled: () => refreshNotifications(queryClient),
  })
}

export function useMarkAllNotificationsAsRead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => notificationsApi.markAllAsRead(),
    onSettled: () => refreshNotifications(queryClient),
  })
}
