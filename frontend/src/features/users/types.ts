import type { RoleKey } from '@/features/auth/types'

/** A User as the Admin manages them (UserResource). */
export type User = {
  id: number
  name: string
  email: string
  role: RoleKey | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

/** What creating a User sends; editing leaves out the password. */
export type UserInput = {
  name: string
  email: string
  role: RoleKey
  password?: string
  password_confirmation?: string
}

/** One row of the read-only Role → permission matrix (RoleResource). */
export type RolePermissions = {
  key: RoleKey
  permissions: string[]
}
