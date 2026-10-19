import { BellIcon, BellOffIcon, CheckCheckIcon } from 'lucide-react'
import { useId, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { EmptyState } from '@/components/feedback/empty-state'
import { ErrorState } from '@/components/feedback/error-state'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useDirection } from '@/components/ui/direction'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Skeleton } from '@/components/ui/skeleton'
import {
  useMarkAllNotificationsAsRead,
  useMarkNotificationAsRead,
  useNotifications,
} from '../hooks/use-notifications'
import type { AppNotification } from '../types'
import { NotificationItem } from './notification-item'

/** The badge never grows wider than two characters. */
const BADGE_LIMIT = 9

/**
 * The header's bell: how many notifications are unread, and a panel listing them newest
 * first, where the User opens a record or marks one or all as read.
 */
export function NotificationBell() {
  const { t, i18n } = useTranslation('notifications')
  const dir = useDirection()
  const titleId = useId()
  const [open, setOpen] = useState(false)
  const notifications = useNotifications()
  const markAsRead = useMarkNotificationAsRead()
  const markAllAsRead = useMarkAllNotificationsAsRead()

  const unread = notifications.data?.pages[0]?.meta.unread_count ?? 0
  const items = notifications.data?.pages.flatMap((page) => page.data) ?? []

  // i18n.t, not t: the language may switch while the request is on its way.
  const reportFailure = () => toast.error(i18n.t('notifications:markAsReadFailed'))

  const handleMarkAsRead = (notification: AppNotification) =>
    markAsRead.mutate(notification.id, { onError: reportFailure })

  const handleOpen = (notification: AppNotification) => {
    if (notification.read_at === null) handleMarkAsRead(notification)
    setOpen(false)
  }

  const handleMarkAllAsRead = () =>
    markAllAsRead.mutate(undefined, {
      onSuccess: () => toast.success(i18n.t('notifications:allMarkedAsRead')),
      onError: reportFailure,
    })

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="relative size-9 rounded-full"
          aria-label={unread > 0 ? t('bellWithUnread', { count: unread }) : t('bell')}
        >
          <BellIcon className="size-4" aria-hidden="true" />
          {unread > 0 && (
            <span
              className="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[0.625rem] leading-none font-semibold text-primary-foreground tabular-nums ring-2 ring-background"
              aria-hidden="true"
            >
              {unread > BADGE_LIMIT ? `${BADGE_LIMIT}+` : unread}
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent
        align="end"
        dir={dir}
        collisionPadding={12}
        aria-labelledby={titleId}
        className="w-[min(24rem,calc(100vw-1.5rem))] gap-0 overflow-hidden p-0"
      >
        <div className="flex items-center justify-between gap-3 border-b px-4 py-3">
          <div className="flex min-w-0 items-center gap-2">
            <h2 id={titleId} className="text-sm font-semibold">
              {t('title')}
            </h2>
            {unread > 0 && (
              <Badge variant="secondary" className="rounded-full tabular-nums">
                {t('unreadCount', { count: unread })}
              </Badge>
            )}
          </div>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-8 shrink-0 gap-1.5 px-2 text-xs text-primary"
            onClick={handleMarkAllAsRead}
            disabled={unread === 0 || markAllAsRead.isPending}
          >
            {markAllAsRead.isPending ? (
              <InlineSpinner className="size-3.5" />
            ) : (
              <CheckCheckIcon className="size-3.5" aria-hidden="true" />
            )}
            {t('markAllAsRead')}
          </Button>
        </div>

        {notifications.isPending ? (
          <NotificationsSkeleton label={t('loading')} />
        ) : notifications.isError && items.length === 0 ? (
          <ErrorState
            title={t('error.title')}
            description={t('error.description')}
            onRetry={() => void notifications.refetch()}
            retrying={notifications.isFetching}
            className="p-6"
          />
        ) : items.length === 0 ? (
          <EmptyState
            icon={BellOffIcon}
            title={t('empty.title')}
            description={t('empty.description')}
            className="m-3 p-6"
            action={
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => void notifications.refetch()}
                disabled={notifications.isFetching}
                className="gap-2"
              >
                {notifications.isFetching && <InlineSpinner className="size-3.5" />}
                {t('empty.action')}
              </Button>
            }
          />
        ) : (
          <div
            className="max-h-[min(26rem,60svh)] overflow-y-auto overscroll-contain"
            data-lenis-prevent
          >
            <ul className="divide-y">
              {items.map((notification) => (
                <NotificationItem
                  key={notification.id}
                  notification={notification}
                  onOpen={handleOpen}
                  onMarkAsRead={handleMarkAsRead}
                  markingAsRead={markAsRead.isPending && markAsRead.variables === notification.id}
                />
              ))}
            </ul>
            {notifications.hasNextPage && (
              <div className="border-t p-2">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  className="w-full gap-2 text-muted-foreground"
                  onClick={() => void notifications.fetchNextPage()}
                  disabled={notifications.isFetchingNextPage}
                >
                  {notifications.isFetchingNextPage && <InlineSpinner className="size-3.5" />}
                  {notifications.isFetchingNextPage ? t('loadingOlder') : t('showOlder')}
                </Button>
              </div>
            )}
          </div>
        )}
      </PopoverContent>
    </Popover>
  )
}

/** Shaped like the rows that follow: an icon chip, two lines of text and a time. */
function NotificationsSkeleton({ label }: { label: string }) {
  return (
    <div role="status" aria-label={label} className="divide-y">
      {[0, 1, 2].map((row) => (
        <div key={row} className="flex items-start gap-3 px-4 py-3">
          <Skeleton className="size-9 shrink-0 rounded-full" />
          <div className="flex flex-1 flex-col gap-2 pt-0.5">
            <Skeleton className="h-3.5 w-full" />
            <Skeleton className="h-3.5 w-3/4" />
            <Skeleton className="h-3 w-16" />
          </div>
        </div>
      ))}
    </div>
  )
}
