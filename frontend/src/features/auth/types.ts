import type { Theme } from '@/components/theme/theme-context'
import type { Language } from '@/lib/i18n'

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
  permissions: string[]
  preferences: { locale: Language; theme: Theme }
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
