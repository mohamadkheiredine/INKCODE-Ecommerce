import { RefreshCwIcon, TriangleAlertIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from '@/components/ui/empty'
import { cn } from '@/lib/utils'

type ErrorStateProps = {
  onRetry: () => void
  retrying?: boolean
  title?: string
  description?: string
  className?: string
}

/** What every query-driven screen shows when the API call fails: what happened, and a way out. */
export function ErrorState({
  onRetry,
  retrying = false,
  title,
  description,
  className,
}: ErrorStateProps) {
  const { t } = useTranslation()

  return (
    <Empty className={cn('border-0', className)}>
      <EmptyHeader>
        <EmptyMedia variant="icon" className="bg-destructive/10 text-destructive">
          <TriangleAlertIcon aria-hidden="true" />
        </EmptyMedia>
        <EmptyTitle>{title ?? t('states.errorTitle')}</EmptyTitle>
        <EmptyDescription>{description ?? t('states.errorDescription')}</EmptyDescription>
      </EmptyHeader>
      <EmptyContent>
        <Button type="button" onClick={onRetry} disabled={retrying} className="gap-2">
          <RefreshCwIcon className={cn('size-4', retrying && 'animate-spin')} aria-hidden="true" />
          {t('actions.retry')}
        </Button>
      </EmptyContent>
    </Empty>
  )
}
