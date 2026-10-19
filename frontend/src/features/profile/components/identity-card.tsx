import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { UserAvatar } from '@/features/auth/components/user-avatar'
import type { CurrentUser } from '@/features/auth/types'

/** Who the User is: name, email and Role. Only an Admin can change these. */
export function IdentityCard({ user }: { user: CurrentUser }) {
  const { t } = useTranslation('profile')

  return (
    <Card className="js-reveal h-fit overflow-hidden rounded-2xl py-0">
      <div className="bg-brand-gradient h-20" aria-hidden="true" />
      <CardContent className="-mt-10 flex flex-col gap-5 pb-6">
        <UserAvatar
          name={user.name}
          className="size-20 rounded-2xl border-4 border-card text-xl shadow-sm"
          fallbackClassName="rounded-xl"
        />
        <div className="flex flex-col gap-1">
          <h2 className="text-lg font-semibold">{user.name}</h2>
          <span className="w-fit max-w-full truncate text-sm text-muted-foreground" dir="ltr">
            {user.email}
          </span>
        </div>
        <Separator />
        <dl className="grid gap-3 text-sm">
          <div className="flex flex-col gap-1">
            <dt className="text-muted-foreground">{t('identity.role')}</dt>
            <dd className="flex flex-col gap-1">
              {user.role ? (
                <>
                  <Badge className="w-fit rounded-full px-2.5">
                    {t(`common:roles.${user.role}`)}
                  </Badge>
                  <span className="text-xs text-muted-foreground">
                    {t(`common:roleDescriptions.${user.role}`)}
                  </span>
                </>
              ) : (
                t('common:states.noRole')
              )}
            </dd>
          </div>
        </dl>
      </CardContent>
    </Card>
  )
}
