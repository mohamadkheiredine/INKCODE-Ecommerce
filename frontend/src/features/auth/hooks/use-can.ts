import { useCallback } from 'react'
import type { Permission } from '@/lib/permissions'
import { useCurrentUser } from './use-auth'

/** Whether the signed-in User's Role holds a permission. False while no User is loaded. */
export function useCan(): (permission: Permission) => boolean {
  const { data: user } = useCurrentUser()
  return useCallback(
    (permission: Permission) => user?.permissions.includes(permission) ?? false,
    [user],
  )
}
