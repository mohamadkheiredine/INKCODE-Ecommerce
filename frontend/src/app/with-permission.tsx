import type { ComponentType } from 'react'
import type { Permission } from '@/lib/permissions'
import { RequirePermission } from './require-permission'

/** A lazy route's page, shown only to Roles holding `permission` (see RequirePermission). */
export function withPermission(permission: Permission, Page: ComponentType): ComponentType {
  const PermittedPage = () => (
    <RequirePermission permission={permission}>
      <Page />
    </RequirePermission>
  )
  PermittedPage.displayName = `withPermission(${Page.displayName ?? Page.name})`
  return PermittedPage
}
