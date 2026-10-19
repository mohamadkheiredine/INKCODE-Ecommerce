import { useTranslation } from 'react-i18next'
import { ErrorState } from '@/components/feedback/error-state'
import { PageHeader } from '@/components/layout/page-header'
import { Skeleton } from '@/components/ui/skeleton'
import { SettingsForm } from '../components/settings-form'
import { useStoreSettings } from '../hooks/use-settings'

/**
 * The Admin's settings. A store always has settings (the API creates them with their
 * defaults), so there is no empty state: only loading, error and the form.
 */
export function SettingsPage() {
  const { t } = useTranslation()
  const settings = useStoreSettings()

  return (
    <div className="flex flex-col gap-8">
      <PageHeader title={t('pages.settings')} description={t('settings:description')} />
      {settings.isPending ? (
        <SettingsSkeleton />
      ) : settings.isError ? (
        <ErrorState onRetry={() => void settings.refetch()} retrying={settings.isFetching} />
      ) : (
        <SettingsForm settings={settings.data} />
      )}
    </div>
  )
}

/** The form's shape: a heading beside a card, for each section. */
function SettingsSkeleton() {
  const { t } = useTranslation()

  return (
    <div role="status" aria-label={t('states.loading')} className="flex flex-col gap-8">
      {[0, 1, 2].map((section) => (
        <div
          key={section}
          className="grid gap-4 lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] lg:gap-10"
        >
          <div className="flex flex-col gap-2">
            <Skeleton className="h-6 w-40" />
            <Skeleton className="h-4 w-56 max-w-full" />
          </div>
          <Skeleton className="h-56 rounded-2xl" />
        </div>
      ))}
    </div>
  )
}
