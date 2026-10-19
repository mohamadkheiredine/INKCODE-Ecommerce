import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate, useNavigate, useSearchParams } from 'react-router'
import { toast } from 'sonner'
import { Logo } from '@/components/brand/logo'
import { LanguageSwitcher } from '@/components/preferences/language-switcher'
import { ThemeToggle } from '@/components/preferences/theme-toggle'
import { BrandPanel } from '../components/brand-panel'
import { SignInPanel } from '../components/sign-in-panel'
import { useCurrentUser } from '../hooks/use-auth'
import { safeRedirectTarget } from '../redirect'
import type { CurrentUser } from '../types'
import { useEntranceAnimation } from '@/lib/motion'

export function SignInPage() {
  const { t } = useTranslation('auth')
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const currentUser = useCurrentUser()
  const container = useRef<HTMLDivElement>(null)
  const target = safeRedirectTarget(searchParams.get('redirect'))

  useEntranceAnimation(container, [
    { targets: '.js-card', y: 24, duration: 0.6 },
    { targets: '.js-brand-item', y: 18, stagger: 0.07, delay: 0.15, duration: 0.55 },
    { targets: '.js-form-item', y: 14, stagger: 0.08, delay: 0.2 },
  ])

  if (currentUser.isSuccess) return <Navigate to={target} replace />

  const handleSignedIn = (user: CurrentUser) => {
    toast.success(t('signIn.welcome', { name: user.name }))
    void navigate(target, { replace: true })
  }

  return (
    <div ref={container} className="relative isolate min-h-svh overflow-hidden bg-background">
      <div
        aria-hidden="true"
        className="pointer-events-none absolute -start-40 -top-40 -z-10 size-[34rem] rounded-full bg-[var(--brand-from)] opacity-[0.13] blur-3xl dark:opacity-[0.16]"
      />
      <div
        aria-hidden="true"
        className="pointer-events-none absolute -end-40 -bottom-48 -z-10 size-[36rem] rounded-full bg-[var(--brand-to)] opacity-[0.12] blur-3xl dark:opacity-[0.16]"
      />

      <header className="absolute inset-x-0 top-0 z-20 flex items-center justify-between gap-2 p-4 sm:p-6 lg:justify-end">
        <Logo className="lg:hidden" showTagline={false} />
        <div className="flex items-center gap-1 rounded-full border bg-card/80 p-1 shadow-xs backdrop-blur">
          <LanguageSwitcher />
          <ThemeToggle />
        </div>
      </header>

      <main className="mx-auto flex min-h-svh w-full max-w-6xl items-center px-4 pt-20 pb-10 sm:px-6 lg:pt-24">
        <div className="js-card mx-auto grid w-full max-w-xl overflow-hidden rounded-[1.75rem] border bg-card shadow-2xl shadow-primary/10 lg:max-w-none lg:grid-cols-[1.05fr_1fr] dark:shadow-background">
          <BrandPanel />

          <section className="flex flex-col justify-center gap-8 p-6 sm:p-10 xl:p-12">
            <div className="js-form-item flex flex-col gap-2">
              <h1 className="text-2xl font-semibold tracking-tight sm:text-[1.75rem]">
                {t('signIn.title')}
              </h1>
              <p className="text-sm text-muted-foreground">{t('signIn.subtitle')}</p>
            </div>
            <SignInPanel onSignedIn={handleSignedIn} />
          </section>
        </div>
      </main>
    </div>
  )
}
