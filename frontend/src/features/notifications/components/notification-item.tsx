import { BellIcon, CheckIcon, ClipboardListIcon, type LucideIcon, SparklesIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { useFormatters } from '@/lib/format'
import { cn } from '@/lib/utils'
import type { AppNotification } from '../types'

/** The icon for each notification type; a type added by a later module gets the bell. */
const TYPE_ICONS: Record<string, LucideIcon> = {
  welcome: SparklesIcon,
  order_assigned: ClipboardListIcon,
}

/** Only paths inside the app are followed; anything else shows as plain text. */
function isAppPath(link: string): boolean {
  return link.startsWith('/') && !link.startsWith('//')
}

type NotificationItemProps = {
  notification: AppNotification
  /** The User followed the link to the record. */
  onOpen: (notification: AppNotification) => void
  onMarkAsRead: (notification: AppNotification) => void
  markingAsRead: boolean
}

/**
 * One row in the bell: what happened, when, and a link to the record. An unread row is
 * stronger and ends in a dot that marks it as read without leaving the page.
 */
export function NotificationItem({
  notification,
  onOpen,
  onMarkAsRead,
  markingAsRead,
}: NotificationItemProps) {
  const { t } = useTranslation('notifications')
  const format = useFormatters()
  const unread = notification.read_at === null
  const Icon = TYPE_ICONS[notification.type] ?? BellIcon
  const message = (
    <span
      className={cn(
        'line-clamp-3 text-sm leading-snug',
        unread ? 'font-medium text-foreground' : 'text-muted-foreground',
      )}
    >
      {notification.message}
    </span>
  )

  return (
    <li className="relative flex items-start gap-3 px-4 py-3 transition-colors focus-within:bg-muted/60 hover:bg-muted/60">
      <span
        className={cn(
          'flex size-9 shrink-0 items-center justify-center rounded-full',
          unread ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground',
        )}
      >
        <Icon className="size-4" aria-hidden="true" />
      </span>
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        {isAppPath(notification.link) ? (
          <Link
            to={notification.link}
            onClick={() => onOpen(notification)}
            className="rounded-sm outline-none after:absolute after:inset-0 focus-visible:ring-2 focus-visible:ring-ring/50"
          >
            {message}
          </Link>
        ) : (
          message
        )}
        <time dateTime={notification.created_at} className="text-xs text-muted-foreground">
          {format.relativeTime(notification.created_at)}
        </time>
      </div>
      {unread && (
        <>
          <span className="sr-only">{t('unread')}</span>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="group/mark relative z-10 -me-1.5 size-7 shrink-0 rounded-full"
            onClick={() => onMarkAsRead(notification)}
            disabled={markingAsRead}
            aria-label={t('markAsRead')}
          >
            <span
              className="size-2 rounded-full bg-primary group-hover/mark:hidden group-focus-visible/mark:hidden"
              aria-hidden="true"
            />
            <CheckIcon
              className="hidden size-4 text-primary group-hover/mark:block group-focus-visible/mark:block"
              aria-hidden="true"
            />
          </Button>
        </>
      )}
    </li>
  )
}
