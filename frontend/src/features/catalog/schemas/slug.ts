import { z } from 'zod'

/** The API's slug rule (App\Support\Slug): lowercase letters and digits joined by single dashes. */
export const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/

/** An optional slug: empty means "generate it from the name". */
export const optionalSlug = z
  .string()
  .trim()
  .max(140, 'catalog:validation.slugTooLong')
  .refine((value) => value === '' || SLUG_PATTERN.test(value), 'catalog:validation.slugFormat')

export const requiredName = z
  .string()
  .trim()
  .min(1, 'catalog:validation.nameRequired')
  .max(120, 'catalog:validation.nameTooLong')
