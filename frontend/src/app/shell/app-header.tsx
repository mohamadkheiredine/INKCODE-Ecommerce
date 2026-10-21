import { PanelLeftIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { LanguageSwitcher } from '@/components/preferences/language-switcher'
import { ThemeToggle } from '@/components/preferences/theme-toggle'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { useSidebar } from '@/components/ui/sidebar'
import type { CurrentUser } from '@/features/auth/types'
import { NotificationBell } from '@/features/notifications/components/notification-bell'
import { GlobalSearch } from '@/features/search/components/global-search'
import { QuickActionsMenu } from '@/features/search/components/quick-actions-menu'
import { useSavePreferences } from '@/features/profile/hooks/use-profile'
import { Breadcrumbs } from './breadcrumbs'
import { UserMenu } from './user-menu'

/**
 * Sidebar toggle and breadcrumbs, then search, quick actions, the language and theme
 * switches, the notifications bell and the user menu.
 */
export function AppHeader({ user }: { user: CurrentUser }) {
  const { t } = useTranslation()
  const { toggleSidebar } = useSidebar()
  const savePreferences = useSavePreferences()

  return (
    <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2 border-b bg-background/80 px-3 backdrop-blur-md sm:px-5">
      <Button
        type="button"
        variant="ghost"
        size="icon"
        className="size-9 shrink-0 rounded-full"
        onClick={toggleSidebar}
        aria-label={t('shell.toggleSidebar')}
      >
        <PanelLeftIcon className="size-4 rtl:rotate-180" aria-hidden="true" />
      </Button>
      <Separator
        orientation="vertical"
        className="me-1 data-[orientation=vertical]:h-5 data-[orientation=vertical]:self-center"
      />
      <Breadcrumbs />
      <div className="ms-auto flex shrink-0 items-center gap-1">
        <GlobalSearch />
        <QuickActionsMenu />
        {/* The switch applies at once; keeping it on the profile follows. */}
        <LanguageSwitcher onChange={(locale) => savePreferences({ locale })} />
        <ThemeToggle onChange={(theme) => savePreferences({ theme })} />
        <NotificationBell />
        <Separator
          orientation="vertical"
          className="mx-1 data-[orientation=vertical]:h-5 data-[orientation=vertical]:self-center"
        />
        <UserMenu user={user} />
      </div>
    </header>
  )
}
