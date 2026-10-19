import { z } from 'zod'

/**
 * The minimum strength the API enforces (`Password::defaults()`), checked here first so
 * the User sees each rule turn green while typing. The API stays the real check.
 */
export const PASSWORD_RULES = {
  length: (value: string) => value.length >= 10,
  case: (value: string) => /\p{Ll}/u.test(value) && /\p{Lu}/u.test(value),
  number: (value: string) => /\p{N}/u.test(value),
} as const

export type PasswordRule = keyof typeof PASSWORD_RULES

export const PASSWORD_MAX_LENGTH = 72

function meetsEveryRule(value: string): boolean {
  return Object.values(PASSWORD_RULES).every((rule) => rule(value))
}

/** Messages are i18n keys in the `profile` namespace, translated when shown. */
export const changePasswordSchema = z
  .object({
    current_password: z.string().min(1, 'password.validation.currentRequired'),
    password: z
      .string()
      .min(1, 'password.validation.newRequired')
      .max(PASSWORD_MAX_LENGTH, 'password.validation.tooLong')
      .refine(meetsEveryRule, 'password.validation.tooWeak'),
    password_confirmation: z.string().min(1, 'password.validation.confirmRequired'),
  })
  .superRefine((values, context) => {
    if (values.password !== '' && values.password === values.current_password) {
      context.addIssue({
        code: 'custom',
        path: ['password'],
        message: 'password.validation.sameAsCurrent',
      })
    }
    if (values.password_confirmation !== '' && values.password !== values.password_confirmation) {
      context.addIssue({
        code: 'custom',
        path: ['password_confirmation'],
        message: 'password.validation.confirmMismatch',
      })
    }
  })

export type ChangePasswordValues = z.infer<typeof changePasswordSchema>
