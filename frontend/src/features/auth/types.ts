import type { Preferences } from '@/components/preferences/use-apply-preferences'
import type { Permission } from '@/lib/permissions'

export const ROLES = [
  'admin',
  'operations',
  'inventory',
  'sales',
  'support',
  'finance',
  'viewer',
] as const
export type RoleKey = (typeof ROLES)[number]

/** The signed-in User, as returned by GET /auth/me. */
export type CurrentUser = {
  id: number
  name: string
  email: string
  role: RoleKey | null
  /** Only used to hide UI; the API checks every request itself. */
  permissions: Permission[]
  preferences: Preferences
}

export type DemoAccount = {
  role: RoleKey
  name: string
  email: string
}

export type SignInInput = {
  email: string
  password: string
  remember: boolean
}
