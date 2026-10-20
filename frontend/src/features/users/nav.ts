import { ShieldCheckIcon, UserCogIcon } from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/** Only the Admin manages Users (users.manage). */
export const usersNav: NavEntry = {
  page: 'users',
  group: 'admin',
  to: '/users',
  icon: UserCogIcon,
  permission: 'users.manage',
}

/** The read-only Roles & permissions matrix, for the Admin. */
export const rolesNav: NavEntry = {
  page: 'roles',
  group: 'admin',
  to: '/roles',
  icon: ShieldCheckIcon,
  permission: 'users.manage',
}
