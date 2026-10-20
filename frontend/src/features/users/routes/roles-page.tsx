import { CheckIcon, MinusIcon } from 'lucide-react'
import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { ErrorState } from '@/components/feedback/error-state'
import { PageHeader } from '@/components/layout/page-header'
import { Skeleton } from '@/components/ui/skeleton'
import { PERMISSIONS } from '@/lib/permissions'
import { useEntranceAnimation } from '@/lib/motion'
import { useRoles } from '../hooks/use-users'

/** The read-only Roles & permissions matrix, as the API enforces it. */
export function RolesPage() {
  const { t } = useTranslation('users')
  const roles = useRoles()
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader title={t('common:pages.roles')} description={t('matrix.description')} />
      {roles.isPending ? (
        <div className="js-reveal flex flex-col gap-2" role="status" aria-live="polite">
          <span className="sr-only">{t('matrix.loading')}</span>
          {Array.from({ length: 8 }, (_, index) => (
            <Skeleton key={index} className="h-9 w-full" />
          ))}
        </div>
      ) : roles.isError ? (
        <ErrorState onRetry={() => void roles.refetch()} retrying={roles.isFetching} />
      ) : (
        <div
          className="js-reveal overflow-x-auto rounded-xl border bg-card shadow-xs"
          data-lenis-prevent
        >
          <table className="w-full min-w-[44rem] text-sm">
            <caption className="sr-only">{t('common:pages.roles')}</caption>
            <thead className="bg-muted/50">
              <tr>
                <th scope="col" className="sticky start-0 bg-muted px-4 py-3 text-start font-medium">
                  {t('matrix.permission')}
                </th>
                {roles.data.map((role) => (
                  <th key={role.key} scope="col" className="px-3 py-3 text-center font-medium">
                    {t(`common:roles.${role.key}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {PERMISSIONS.map((permission) => (
                <tr key={permission} className="border-t">
                  <th
                    scope="row"
                    className="sticky start-0 bg-card px-4 py-2.5 text-start font-normal"
                  >
                    <span className="block">{t(`permissions.${permission}`)}</span>
                    <code className="text-xs text-muted-foreground" dir="ltr">
                      {permission}
                    </code>
                  </th>
                  {roles.data.map((role) => {
                    const granted = role.permissions.includes(permission)
                    const label = t(granted ? 'matrix.granted' : 'matrix.notGranted', {
                      role: t(`common:roles.${role.key}`),
                    })
                    return (
                      <td key={role.key} className="px-3 py-2.5 text-center">
                        {granted ? (
                          <CheckIcon className="mx-auto size-4 text-primary" aria-label={label} />
                        ) : (
                          <MinusIcon
                            className="mx-auto size-4 text-muted-foreground/50"
                            aria-label={label}
                          />
                        )}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
