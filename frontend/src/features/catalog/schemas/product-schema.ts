import { z } from 'zod'
import { PRODUCT_STATUSES, type Product, type ProductInput } from '../types'
import { amount } from './money'
import { SLUG_PATTERN } from './slug'

const optionalText = (max: number, message: string) => z.string().trim().max(max, message)

/** Mirrors StoreProductRequest. Selects hold ids as strings; tags are typed comma-separated. */
export const productSchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, 'catalog:validation.nameRequired')
    .max(160, 'catalog:validation.productNameTooLong'),
  slug: z
    .string()
    .trim()
    .max(180, 'catalog:validation.slugTooLong')
    .refine((value) => value === '' || SLUG_PATTERN.test(value), 'catalog:validation.slugFormat'),
  description: optionalText(5000, 'catalog:validation.productDescriptionTooLong'),
  category_id: z.string().min(1, 'catalog:validation.categoryRequired'),
  brand_id: z.string(),
  base_price: amount(2, 'catalog:validation.priceFormat'),
  taxable: z.boolean(),
  tags: z
    .string()
    .refine((value) => splitTags(value).length <= 20, 'catalog:validation.tooManyTags')
    .refine(
      (value) => splitTags(value).every((tag) => tag.length <= 40),
      'catalog:validation.tagTooLong',
    ),
  seo_title: optionalText(70, 'catalog:validation.seoTitleTooLong'),
  seo_description: optionalText(160, 'catalog:validation.seoDescriptionTooLong'),
  status: z.enum(PRODUCT_STATUSES),
})

export type ProductFormValues = z.infer<typeof productSchema>

/** "cotton, summer, cotton" → ["cotton", "summer"]: trimmed, without blanks or repeats. */
export function splitTags(value: string): string[] {
  const tags = value
    .split(',')
    .map((tag) => tag.trim())
    .filter(Boolean)
  return tags.filter(
    (tag, index) => tags.findIndex((other) => other.toLowerCase() === tag.toLowerCase()) === index,
  )
}

export function productFormValues(product: Product | null): ProductFormValues {
  return {
    name: product?.name ?? '',
    slug: product?.slug ?? '',
    description: product?.description ?? '',
    category_id: product ? String(product.category.id) : '',
    brand_id: product?.brand ? String(product.brand.id) : '',
    base_price: product?.base_price ?? '',
    taxable: product?.taxable ?? true,
    tags: product?.tags.join(', ') ?? '',
    seo_title: product?.seo_title ?? '',
    seo_description: product?.seo_description ?? '',
    status: product?.status ?? 'draft',
  }
}

export function toProductInput(values: ProductFormValues): ProductInput {
  return {
    ...values,
    category_id: Number(values.category_id),
    brand_id: values.brand_id ? Number(values.brand_id) : null,
    tags: splitTags(values.tags),
  }
}
