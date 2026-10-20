import { z } from 'zod'
import { isLogoSize, isLogoType } from '@/lib/logo-file'
import type { Brand, BrandInput, LogoChange } from '../types'
import { optionalSlug, requiredName } from './slug'

export const brandSchema = z.object({
  name: requiredName,
  slug: optionalSlug,
  /** A newly chosen logo; null keeps the current one (unless `removeLogo`). */
  logo: z
    .instanceof(File)
    .nullable()
    .refine((file) => file === null || isLogoType(file), 'catalog:validation.logoType')
    .refine((file) => file === null || isLogoSize(file), 'catalog:validation.logoTooLarge'),
  removeLogo: z.boolean(),
})

export type BrandFormValues = z.infer<typeof brandSchema>

export function brandFormValues(brand: Brand | null): BrandFormValues {
  return { name: brand?.name ?? '', slug: brand?.slug ?? '', logo: null, removeLogo: false }
}

export function toBrandInput(values: BrandFormValues): BrandInput {
  return { name: values.name, slug: values.slug }
}

export function toLogoChange(values: BrandFormValues, brand: Brand | null): LogoChange {
  if (values.logo) return { kind: 'replace', file: values.logo }
  if (values.removeLogo && brand?.logo_url) return { kind: 'remove' }
  return { kind: 'keep' }
}
