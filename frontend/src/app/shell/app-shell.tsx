import { useTranslation } from 'react-i18next'
import { Outlet, ScrollRestoration, useNavigation } from 'react-router'
import { SidebarProvider } from '@/components/ui/sidebar'
import { useCurrentUser } from '@/features/auth/hooks/use-auth'
import type { CurrentUser } from '@/features/auth/types'
import { useApplySavedPreferences } from '@/features/profile/hooks/use-profile'
import { AppHeader } from './app-header'
import { AppSidebar } from './app-sidebar'

/**
 * The sidebar remembers the User's last choice (shadcn keeps it in this cookie). Without
 * one, tablets start with the icon rail so the page keeps its room.
 */
function sidebarStartsOpen(): boolean {
  const saved = document.cookie
    .split('; ')
    .find((cookie) => cookie.startsWith('sidebar_state='))
    ?.split('=')[1]
  return saved === undefined ? window.innerWidth >= 1024 : saved === 'true'
}

/** Every signed-in page lives inside this frame. RequireAuth guarantees the User. */
export function AppShell() {
  const { data: user } = useCurrentUser()
  return user ? <Shell user={user} /> : null
}

function Shell({ user }: { user: CurrentUser }) {
  const { t } = useTranslation()
  useApplySavedPreferences(user)

  return (
    <SidebarProvider defaultOpen={sidebarStartsOpen()}>
      <a
        href="#main-content"
        className="sr-only z-50 rounded-lg bg-primary px-4 py-2 text-primary-foreground focus:not-sr-only focus:fixed focus:start-4 focus:top-3"
      >
        {t('shell.skipToContent')}
      </a>
      <AppSidebar />
      <div className="relative flex min-w-0 flex-1 flex-col bg-background">
        <AppHeader user={user} />
        <RouteProgress />
        <main id="main-content" tabIndex={-1} className="flex-1">
          <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <Outlet />
          </div>
        </main>
      </div>
      <ScrollRestoration />
    </SidebarProvider>
  )
}

/** A thin bar under the header while the next page's code loads. */
function RouteProgress() {
  const { t } = useTranslation()
  const navigation = useNavigation()
  if (navigation.state === 'idle') return null

  return (
    <div role="status" className="absolute inset-x-0 top-16 z-20 h-0.5 overflow-hidden">
      <span className="sr-only">{t('shell.loadingPage')}</span>
      <div className="bg-brand-gradient route-progress h-full w-1/3" />
    </div>
  )
}
