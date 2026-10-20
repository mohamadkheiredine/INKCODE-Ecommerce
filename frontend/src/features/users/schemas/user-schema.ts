import { z } from 'zod'
import { ROLES } from '@/features/auth/types'
import {
  PASSWORD_MAX_LENGTH,
  PASSWORD_RULES,
} from '@/features/profile/schemas/change-password-schema'
import type { User } from '../types'

const details = {
  name: z
    .string()
    .trim()
    .min(1, 'users:validation.nameRequired')
    .max(120, 'users:validation.nameTooLong'),
  email: z
    .string()
    .trim()
    .min(1, 'users:validation.emailRequired')
    .max(255, 'users:validation.emailFormat')
    .refine((value) => z.email().safeParse(value).success, 'users:validation.emailFormat'),
  role: z.enum(ROLES, 'users:validation.roleRequired'),
}

/** Editing a User: name, email and Role. Messages are i18n keys, translated when shown. */
export const editUserSchema = z.object(details)

/** A new User also gets a temporary password, as strong as the API requires. */
export const newUserSchema = z
  .object({
    ...details,
    password: z
      .string()
      .min(1, 'users:validation.passwordRequired')
      .max(PASSWORD_MAX_LENGTH, 'users:validation.passwordTooWeak')
      .refine(
        (value) => Object.values(PASSWORD_RULES).every((rule) => rule(value)),
        'users:validation.passwordTooWeak',
      ),
    password_confirmation: z.string(),
  })
  .refine((values) => values.password === values.password_confirmation, {
    path: ['password_confirmation'],
    message: 'users:validation.passwordMismatch',
  })

export type UserFormValues = z.infer<typeof editUserSchema> & {
  password?: string
  password_confirmation?: string
}

export function userFormValues(user: User | null): UserFormValues {
  return user
    ? { name: user.name, email: user.email, role: user.role ?? 'viewer' }
    : { name: '', email: '', role: 'sales', password: '', password_confirmation: '' }
}
