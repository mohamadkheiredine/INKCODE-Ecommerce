import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/layout/page-header'
import { useCurrentUser } from '@/features/auth/hooks/use-auth'
import { useEntranceAnimation } from '@/lib/motion'
import { IdentityCard } from '../components/identity-card'
import { ChangePasswordCard } from '../components/change-password-card'
import { PreferencesCard } from '../components/preferences-card'

/**
 * The signed-in User's own profile. Its data is the current User, which the route guard
 * has already loaded (with its own skeleton and retry), so the page has no loading state.
 */
export function ProfilePage() {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  if (!user) return null

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader title={t('pages.profile')} description={t('profile:description')} />
      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
        <IdentityCard user={user} />
        <div className="flex min-w-0 flex-col gap-6">
          <PreferencesCard />
          <ChangePasswordCard />
        </div>
      </div>
    </div>
  )
}
