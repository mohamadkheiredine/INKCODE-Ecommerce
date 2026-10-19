import { useTranslation } from 'react-i18next'
import { LogoTile } from '@/components/media/logo-tile'
import { useStoreProfile } from '../hooks/use-settings'

/** The store this dashboard runs: its logo, name and Store currency, for every User. */
export function StoreBadge() {
  const { t } = useTranslation()
  const { data: store } = useStoreProfile()
  if (!store) return null

  return (
    <div
      data-testid="store-badge"
      className="flex items-center gap-2.5 rounded-xl border bg-background/60 p-2 group-data-[collapsible=icon]:border-0 group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:p-0"
    >
      <LogoTile
        name={store.name}
        src={store.logo_url}
        alt={t('settings:logo.alt', { name: store.name })}
        className="size-8 rounded-lg"
      />
      <div className="flex min-w-0 flex-col group-data-[collapsible=icon]:hidden">
        <span dir="auto" className="truncate text-sm font-medium">
          {store.name}
        </span>
        <span className="text-xs text-muted-foreground">
          {t('shell.storeCurrency', { code: store.currency.code })}
        </span>
      </div>
    </div>
  )
}
