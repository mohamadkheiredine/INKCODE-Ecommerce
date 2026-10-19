import { KeyRoundIcon, LogOutIcon, MailIcon } from 'lucide-react'
import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { toast } from 'sonner'
import { Logo } from '@/components/brand/logo'
import { LanguageSwitcher } from '@/components/preferences/language-switcher'
import { ThemeToggle } from '@/components/preferences/theme-toggle'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { useEntranceAnimation } from '@/lib/motion'
import { ROLE_ICONS } from '../components/role-icon'
import { useCurrentUser, useSignOut } from '../hooks/use-auth'
import { SIGN_IN_PATH } from '../redirect'

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

/** The guarded landing page: who is signed in, with which Role, and a way out. */
export function HomePage() {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const signOut = useSignOut()
  const navigate = useNavigate()
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.07 }])

  if (!user) return null

  const RoleIcon = user.role ? ROLE_ICONS[user.role] : KeyRoundIcon
  const roleName = user.role ? t(`roles.${user.role}`) : '—'

  const handleSignOut = () => {
    signOut.mutate(undefined, {
      onSuccess: () => {
        toast.success(t('auth:signOut.done'))
        void navigate(SIGN_IN_PATH, { replace: true })
      },
      onError: () => toast.error(t('auth:signOut.failed')),
    })
  }

  return (
    <div ref={container} className="min-h-svh bg-background">
      <header className="sticky top-0 z-10 border-b bg-background/80 backdrop-blur">
        <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-3 px-4 sm:px-8">
          <Logo />
          <div className="flex items-center gap-1">
            <LanguageSwitcher />
            <ThemeToggle />
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="ms-1 h-9 gap-2 rounded-full px-3"
              onClick={handleSignOut}
              disabled={signOut.isPending}
            >
              {signOut.isPending ? (
                <InlineSpinner className="size-4" />
              ) : (
                <LogOutIcon className="size-4 rtl:rotate-180" aria-hidden="true" />
              )}
              <span className="sr-only sm:not-sr-only">{t('actions.signOut')}</span>
            </Button>
          </div>
        </div>
      </header>

      <main className="mx-auto flex max-w-5xl flex-col gap-8 px-4 py-10 sm:px-8 sm:py-14">
        <section className="js-reveal relative isolate overflow-hidden rounded-3xl border bg-card p-6 shadow-sm sm:p-10">
          <div
            aria-hidden="true"
            className="bg-brand-gradient absolute -end-24 -top-24 -z-10 size-72 rounded-full opacity-15 blur-3xl"
          />
          <div className="flex flex-col gap-6 sm:flex-row sm:items-center">
            <Avatar className="size-16 rounded-2xl text-lg">
              <AvatarFallback className="bg-brand-gradient rounded-2xl font-semibold text-brand-foreground">
                {initials(user.name)}
              </AvatarFallback>
            </Avatar>
            <div className="flex flex-col gap-1.5">
              <span className="text-xs font-semibold tracking-wide text-primary uppercase">
                {t('auth:home.eyebrow')}
              </span>
              <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                {t('auth:home.title', { name: user.name })}
              </h1>
              <p className="text-muted-foreground">{t('auth:home.subtitle')}</p>
            </div>
          </div>
        </section>

        <section className="grid gap-4 sm:grid-cols-3">
          <InfoCard icon={<RoleIcon className="size-5" />} label={t('auth:home.role')}>
            <div className="flex flex-col gap-1">
              <Badge className="w-fit rounded-full px-2.5" data-testid="current-role">
                {roleName}
              </Badge>
              {user.role && (
                <span className="text-xs text-muted-foreground">
                  {t(`roleDescriptions.${user.role}`)}
                </span>
              )}
            </div>
          </InfoCard>
          <InfoCard icon={<MailIcon className="size-5" />} label={t('auth:home.email')}>
            <span className="w-fit max-w-full truncate font-medium" dir="ltr">
              {user.email}
            </span>
          </InfoCard>
          <InfoCard icon={<KeyRoundIcon className="size-5" />} label={t('auth:home.access')}>
            <span className="font-medium">
              {t('auth:home.permissionCount', { count: user.permissions.length })}
            </span>
          </InfoCard>
        </section>
      </main>
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
