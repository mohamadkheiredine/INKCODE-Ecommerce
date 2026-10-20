import { z } from 'zod'
import type { Customer, CustomerInput } from '../types'

/** The API's phone rule (StoreCustomerRequest::PHONE_PATTERN): digits and the usual separators. */
export const PHONE_PATTERN = /^\+?[0-9][0-9 ().-]{5,29}$/

/** The API's limits: a Tag name is at most 50 characters and a Customer carries at most 20. */
export const TAG_MAX_LENGTH = 50
export const MAX_TAGS = 20

export const optionalPhone = z
  .string()
  .trim()
  .refine((value) => value === '' || PHONE_PATTERN.test(value), 'customers:validation.phoneFormat')

export const customerSchema = z
  .object({
    name: z
      .string()
      .trim()
      .min(1, 'customers:validation.nameRequired')
      .max(120, 'customers:validation.nameTooLong'),
    email: z
      .string()
      .trim()
      .max(255, 'customers:validation.emailFormat')
      .refine((value) => value === '' || z.email().safeParse(value).success, {
        message: 'customers:validation.emailFormat',
      }),
    phone: optionalPhone,
    tags: z.array(z.string()).max(MAX_TAGS, 'customers:validation.tooManyTags'),
  })
  .refine((values) => values.email !== '' || values.phone !== '', {
    message: 'customers:validation.emailOrPhone',
    path: ['phone'],
  })

export type CustomerFormValues = z.infer<typeof customerSchema>

export function customerFormValues(customer: Customer | null): CustomerFormValues {
  return {
    name: customer?.name ?? '',
    email: customer?.email ?? '',
    phone: customer?.phone ?? '',
    tags: customer?.tags.map((tag) => tag.name) ?? [],
  }
}

export function toCustomerInput(values: CustomerFormValues): CustomerInput {
  return {
    name: values.name,
    email: values.email || null,
    phone: values.phone || null,
    tags: values.tags,
  }
}
