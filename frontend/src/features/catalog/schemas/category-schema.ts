import { z } from 'zod'
import type { Category, CategoryInput } from '../types'
import { optionalSlug, requiredName } from './slug'

/** The Category form. The parent is the select's value: '' for "none (top level)". */
export const categorySchema = z.object({
  name: requiredName,
  slug: optionalSlug,
  parent_id: z.string(),
  description: z.string().trim().max(2000, 'catalog:validation.descriptionTooLong'),
})

export type CategoryFormValues = z.infer<typeof categorySchema>

export function categoryFormValues(category: Category | null): CategoryFormValues {
  return {
    name: category?.name ?? '',
    slug: category?.slug ?? '',
    parent_id: category?.parent ? String(category.parent.id) : '',
    description: category?.description ?? '',
  }
}

export function toCategoryInput(values: CategoryFormValues): CategoryInput {
  return {
    name: values.name,
    slug: values.slug,
    parent_id: values.parent_id === '' ? null : Number(values.parent_id),
    description: values.description,
  }
}
