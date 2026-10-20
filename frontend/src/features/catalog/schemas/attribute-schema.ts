import { z } from 'zod'
import type { Attribute } from '../types'
import { CODE_PATTERN } from './money'

const valueSchema = z.object({
  id: z.number().nullable(),
  name: z
    .string()
    .trim()
    .min(1, 'catalog:validation.valueNameRequired')
    .max(60, 'catalog:validation.valueNameTooLong'),
  code: z
    .string()
    .trim()
    .min(1, 'catalog:validation.codeRequired')
    .max(20, 'catalog:validation.codeTooLong')
    .refine((code) => code === '' || CODE_PATTERN.test(code), 'catalog:validation.codeFormat'),
})

/** Mirrors StoreAttributeRequest: a name, and values unique by name and by code. */
export const attributeSchema = z
  .object({
    name: z
      .string()
      .trim()
      .min(1, 'catalog:validation.nameRequired')
      .max(60, 'catalog:validation.attributeNameTooLong'),
    values: z.array(valueSchema).max(100, 'catalog:validation.tooManyValues'),
  })
  .superRefine(({ values }, context) => {
    const seen = { name: new Set<string>(), code: new Set<string>() }
    values.forEach((value, index) => {
      for (const field of ['name', 'code'] as const) {
        const key = value[field].trim().toLowerCase()
        if (key && seen[field].has(key)) {
          context.addIssue({
            code: 'custom',
            path: ['values', index, field],
            message: 'catalog:validation.valueRepeated',
          })
        }
        seen[field].add(key)
      }
    })
  })

export type AttributeFormValues = z.infer<typeof attributeSchema>

export function attributeFormValues(attribute: Attribute | null): AttributeFormValues {
  return {
    name: attribute?.name ?? '',
    values: attribute?.values.map(({ id, name, code }) => ({ id, name, code })) ?? [],
  }
}
