import { HouseIcon, ShieldXIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { EmptyState } from '@/components/feedback/empty-state'
import { Button } from '@/components/ui/button'
import { useCan } from '@/features/auth/hooks/use-can'
import type { Permission } from '@/lib/permissions'

/**
 * Shows its page only to Roles holding `permission`. Anyone else (for example someone
 * following a shared link) is told so instead of seeing a broken page. This only shapes
 * the interface: the API refuses every request the Role may not make. Routes use it
 * through `withPermission` (./with-permission.ts).
 */
export function RequirePermission({
  permission,
  children,
}: {
  permission: Permission
  children: ReactNode
}) {
  const { t } = useTranslation()
  const can = useCan()

  if (can(permission)) return children

  return (
    <EmptyState
      icon={ShieldXIcon}
      title={t('states.forbiddenTitle')}
      description={t('states.forbiddenDescription')}
      className="mt-6"
      action={
        <Button asChild variant="outline" className="gap-2">
          <Link to="/">
            <HouseIcon className="size-4" aria-hidden="true" />
            {t('actions.goHome')}
          </Link>
        </Button>
      }
    />
  )
}
