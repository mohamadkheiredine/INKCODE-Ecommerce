import { z } from 'zod'
import type { Brand, BrandInput } from '../types'
import type { LogoChange } from '../hooks/use-brands'
import { optionalSlug, requiredName } from './slug'

/** What the API accepts as a logo (StoreBrandLogoRequest): PNG, JPEG or WebP, up to 1 MB. */
export const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp'] as const
export const LOGO_MAX_BYTES = 1024 * 1024

export const brandSchema = z.object({
  name: requiredName,
  slug: optionalSlug,
  /** A newly chosen logo; null keeps the current one (unless `removeLogo`). */
  logo: z
    .instanceof(File)
    .nullable()
    .refine(
      (file) => file === null || (LOGO_TYPES as readonly string[]).includes(file.type),
      'catalog:validation.logoType',
    )
    .refine(
      (file) => file === null || file.size <= LOGO_MAX_BYTES,
      'catalog:validation.logoTooLarge',
    ),
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
