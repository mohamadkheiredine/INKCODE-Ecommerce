import { CalendarDaysIcon, KeyRoundIcon, MailIcon } from 'lucide-react'
import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { ROLE_ICONS } from '@/features/auth/components/role-icon'
import { useCurrentUser } from '@/features/auth/hooks/use-auth'
import { UserAvatar } from '@/features/auth/components/user-avatar'
import { useFormatters } from '@/lib/format'
import { useEntranceAnimation } from '@/lib/motion'

/** The landing page until the dashboard arrives: who is signed in, with which Role. */
export function HomePage() {
  const { t } = useTranslation('home')
  const { data: user } = useCurrentUser()
  const format = useFormatters()
  const container = useRef<HTMLDivElement>(null)
  const [today] = useState(() => new Date())

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.07 }])

  if (!user) return null

  const RoleIcon = user.role ? ROLE_ICONS[user.role] : KeyRoundIcon
  const roleName = user.role ? t(`common:roles.${user.role}`) : t('common:states.noRole')

  return (
    <div ref={container} className="flex flex-col gap-6">
      <section className="js-reveal relative isolate overflow-hidden rounded-3xl border bg-card p-6 shadow-sm sm:p-10">
        <div
          aria-hidden="true"
          className="bg-brand-gradient absolute -end-24 -top-24 -z-10 size-72 rounded-full opacity-15 blur-3xl"
        />
        <div className="flex flex-col gap-6 sm:flex-row sm:items-center">
          <UserAvatar
            name={user.name}
            className="size-16 rounded-2xl text-lg"
            fallbackClassName="rounded-2xl"
          />
          <div className="flex flex-col gap-1.5">
            <span className="text-xs font-semibold tracking-wide text-primary uppercase">
              {t('eyebrow')}
            </span>
            <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
              {t('title', { name: user.name })}
            </h1>
            <p className="text-muted-foreground">{t('subtitle')}</p>
            <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
              <CalendarDaysIcon className="size-4" aria-hidden="true" />
              <span data-testid="today">
                {t('today', { date: format.date(today, { dateStyle: 'full' }) })}
              </span>
            </p>
          </div>
        </div>
      </section>

      <section className="grid gap-4 sm:grid-cols-3">
        <InfoCard icon={<RoleIcon className="size-5" />} label={t('role')}>
          <div className="flex flex-col gap-1">
            <Badge className="w-fit rounded-full px-2.5" data-testid="current-role">
              {roleName}
            </Badge>
            {user.role && (
              <span className="text-xs text-muted-foreground">
                {t(`common:roleDescriptions.${user.role}`)}
              </span>
            )}
          </div>
        </InfoCard>
        <InfoCard icon={<MailIcon className="size-5" />} label={t('email')}>
          <span className="w-fit max-w-full truncate font-medium" dir="ltr">
            {user.email}
          </span>
        </InfoCard>
        <InfoCard icon={<KeyRoundIcon className="size-5" />} label={t('access')}>
          <span className="font-medium">
            {t('permissionCount', { count: user.permissions.length })}
          </span>
        </InfoCard>
      </section>
    </div>
  )
}

function InfoCard({
  icon,
  label,
  children,
}: {
  icon: React.ReactNode
  label: string
  children: React.ReactNode
}) {
  return (
    <div className="js-reveal flex flex-col gap-4 rounded-2xl border bg-card p-5 shadow-xs">
      <div className="flex items-center gap-3">
        <span className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
          {icon}
        </span>
        <span className="text-sm font-medium text-muted-foreground">{label}</span>
      </div>
      {children}
    </div>
  )
}
