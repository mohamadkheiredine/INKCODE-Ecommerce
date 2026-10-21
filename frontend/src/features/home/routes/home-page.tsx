import { CalendarDaysIcon, KeyRoundIcon } from 'lucide-react'
import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { ROLE_ICONS } from '@/features/auth/components/role-icon'
import { useCan } from '@/features/auth/hooks/use-can'
import { useCurrentUser } from '@/features/auth/hooks/use-auth'
import { UserAvatar } from '@/features/auth/components/user-avatar'
import { DashboardPanel } from '@/features/dashboard/components/dashboard-panel'
import { useFormatters } from '@/lib/format'
import { useEntranceAnimation } from '@/lib/motion'

/** The landing page: who is signed in, then the executive dashboard for Roles that see it. */
export function HomePage() {
  const { t } = useTranslation('home')
  const { data: user } = useCurrentUser()
  const can = useCan()
  const format = useFormatters()
  const container = useRef<HTMLDivElement>(null)
  const [today] = useState(() => new Date())

  useEntranceAnimation(container, [{ targets: '.js-greeting', stagger: 0.07 }])

  if (!user) return null

  const RoleIcon = user.role ? ROLE_ICONS[user.role] : KeyRoundIcon
  const roleName = user.role ? t(`common:roles.${user.role}`) : t('common:states.noRole')

  return (
    <div ref={container} className="flex flex-col gap-8">
      <section className="js-greeting relative isolate overflow-hidden rounded-3xl border bg-card p-6 shadow-sm sm:p-8">
        <div
          aria-hidden="true"
          className="bg-brand-gradient absolute -end-24 -top-24 -z-10 size-72 rounded-full opacity-15 blur-3xl"
        />
        <div className="flex flex-col gap-5 sm:flex-row sm:items-center">
          <UserAvatar
            name={user.name}
            className="size-14 rounded-2xl text-lg"
            fallbackClassName="rounded-2xl"
          />
          <div className="flex min-w-0 flex-col gap-1.5">
            <span className="text-xs font-semibold tracking-wide text-primary uppercase">
              {t('eyebrow')}
            </span>
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
              {t('title', { name: user.name })}
            </h1>
            <p className="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm text-muted-foreground">
              <Badge className="gap-1.5 rounded-full px-2.5" data-testid="current-role">
                <RoleIcon className="size-3.5" aria-hidden="true" />
                {roleName}
              </Badge>
              <span className="flex items-center gap-1.5">
                <CalendarDaysIcon className="size-4" aria-hidden="true" />
                <span data-testid="today">
                  {t('today', { date: format.date(today, { dateStyle: 'full' }) })}
                </span>
              </span>
            </p>
            {!can('dashboard.view') && <p className="text-muted-foreground">{t('subtitle')}</p>}
          </div>
        </div>
      </section>

      {can('dashboard.view') && <DashboardPanel />}
    </div>
  )
}
