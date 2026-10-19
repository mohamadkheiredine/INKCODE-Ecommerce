import { z } from 'zod'

/** Messages are i18n keys in the `auth` namespace, translated when shown. */
export const signInSchema = z.object({
  email: z
    .string()
    .trim()
    .min(1, 'validation.emailRequired')
    .pipe(z.email('validation.emailInvalid')),
  password: z.string().min(1, 'validation.passwordRequired'),
  remember: z.boolean(),
})

export type SignInValues = z.infer<typeof signInSchema>
