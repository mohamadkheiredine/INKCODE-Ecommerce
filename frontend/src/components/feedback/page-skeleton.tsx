import { useTranslation } from 'react-i18next'
import { Skeleton } from '@/components/ui/skeleton'

/** Shown while the signed-in User is being loaded, shaped like the page that follows. */
export function PageSkeleton() {
  const { t } = useTranslation()

  return (
    <div className="min-h-svh bg-background" role="status" aria-live="polite">
      <span className="sr-only">{t('states.loading')}</span>
      <div className="flex h-16 items-center justify-between border-b px-4 sm:px-8">
        <Skeleton className="h-9 w-36 rounded-lg" />
        <div className="flex gap-2">
          <Skeleton className="size-9 rounded-full" />
          <Skeleton className="size-9 rounded-full" />
        </div>
      </div>
      <div className="mx-auto grid max-w-5xl gap-6 p-4 sm:p-8">
        <Skeleton className="h-8 w-64" />
        <Skeleton className="h-4 w-80 max-w-full" />
        <div className="grid gap-4 sm:grid-cols-3">
          <Skeleton className="h-28 rounded-2xl" />
          <Skeleton className="h-28 rounded-2xl" />
          <Skeleton className="h-28 rounded-2xl" />
        </div>
      </div>
    </div>
  )
}
