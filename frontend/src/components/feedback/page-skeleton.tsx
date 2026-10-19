import { useTranslation } from 'react-i18next'
import { Skeleton } from '@/components/ui/skeleton'

/** Shown while the signed-in User is loading, shaped like the app shell that follows. */
export function PageSkeleton() {
  const { t } = useTranslation()

  return (
    <div className="flex min-h-svh bg-background" role="status" aria-live="polite">
      <span className="sr-only">{t('states.loading')}</span>
      <div className="hidden w-64 shrink-0 flex-col gap-6 border-e bg-sidebar p-4 md:flex">
        <Skeleton className="h-9 w-36 rounded-lg" />
        <div className="flex flex-col gap-2">
          <Skeleton className="h-8 w-full rounded-lg" />
          <Skeleton className="h-8 w-5/6 rounded-lg" />
          <Skeleton className="h-8 w-4/6 rounded-lg" />
        </div>
      </div>
      <div className="flex flex-1 flex-col">
        <div className="flex h-16 items-center justify-between border-b px-4 sm:px-6">
          <Skeleton className="h-5 w-40" />
          <div className="flex gap-2">
            <Skeleton className="size-9 rounded-full" />
            <Skeleton className="size-9 rounded-full" />
            <Skeleton className="size-9 rounded-full" />
          </div>
        </div>
        <ContentSkeleton />
      </div>
    </div>
  )
}

/** Shown inside the shell while a page's data loads: a header and a few cards. */
export function ContentSkeleton() {
  return (
    <div className="mx-auto grid w-full max-w-7xl gap-6 p-4 sm:p-6 lg:p-8">
      <div className="flex flex-col gap-2">
        <Skeleton className="h-8 w-64 max-w-full" />
        <Skeleton className="h-4 w-80 max-w-full" />
      </div>
      <div className="grid gap-4 sm:grid-cols-3">
        <Skeleton className="h-28 rounded-2xl" />
        <Skeleton className="h-28 rounded-2xl" />
        <Skeleton className="h-28 rounded-2xl" />
      </div>
      <Skeleton className="h-64 rounded-2xl" />
    </div>
  )
}
