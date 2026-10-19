import { useTranslation } from 'react-i18next'
import { Link, matchPath, useLocation } from 'react-router'
import { Logo, LogoMark } from '@/components/brand/logo'
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
  useSidebar,
} from '@/components/ui/sidebar'
import { useDirection } from '@/components/ui/direction'
import { useCan } from '@/features/auth/hooks/use-can'
import { type NavEntry, visibleNavigation } from '@/lib/navigation'
import { NAV_ENTRIES } from '../navigation'

/**
 * The module menu: only the entries the User's Role may use, in their groups. Collapses
 * to an icon rail on desktop and becomes a drawer on phones; it sits on the reading side.
 */
export function AppSidebar() {
  const { t } = useTranslation()
  const can = useCan()
  const dir = useDirection()
  const groups = visibleNavigation(NAV_ENTRIES, can)
  const tooltipSide = dir === 'rtl' ? 'left' : 'right'

  return (
    <Sidebar side={dir === 'rtl' ? 'right' : 'left'} dir={dir} collapsible="icon">
      <SidebarHeader className="h-16 justify-center px-3 group-data-[collapsible=icon]:px-2">
        <Link
          to="/"
          aria-label={t('appName')}
          className="w-fit rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
        >
          <Logo className="group-data-[collapsible=icon]:hidden" />
          <LogoMark className="hidden size-8 group-data-[collapsible=icon]:block" />
        </Link>
      </SidebarHeader>

      <SidebarContent data-lenis-prevent>
        <nav aria-label={t('shell.mainNavigation')}>
          {groups.map(({ group, entries }) => (
            <SidebarGroup key={group}>
              {group !== 'dashboard' && (
                <SidebarGroupLabel className="text-[0.7rem] font-semibold tracking-[0.08em] uppercase">
                  {t(`navGroups.${group}`)}
                </SidebarGroupLabel>
              )}
              <SidebarGroupContent>
                <SidebarMenu>
                  {entries.map((entry) => (
                    <NavItem key={entry.to} entry={entry} tooltipSide={tooltipSide} />
                  ))}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          ))}
        </nav>
      </SidebarContent>
      <SidebarRail aria-label={t('shell.toggleSidebar')} />
    </Sidebar>
  )
}

function NavItem({ entry, tooltipSide }: { entry: NavEntry; tooltipSide: 'left' | 'right' }) {
  const { t } = useTranslation()
  const { pathname } = useLocation()
  const { isMobile, setOpenMobile } = useSidebar()
  const label = t(`pages.${entry.page}`)
  const isActive = matchPath({ path: entry.to, end: entry.to === '/' }, pathname) !== null
  const Icon = entry.icon

  return (
    <SidebarMenuItem>
      <SidebarMenuButton
        asChild
        isActive={isActive}
        tooltip={{ children: label, side: tooltipSide }}
        className="h-9 gap-3 rounded-lg font-medium data-active:text-primary [&>svg]:size-[1.15rem]"
      >
        <Link
          to={entry.to}
          aria-current={isActive ? 'page' : undefined}
          onClick={() => {
            if (isMobile) setOpenMobile(false)
          }}
        >
          <Icon aria-hidden="true" />
          <span>{label}</span>
        </Link>
      </SidebarMenuButton>
    </SidebarMenuItem>
  )
}
