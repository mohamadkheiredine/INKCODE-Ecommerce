import { z } from 'zod'
import type { Variant, VariantInput } from '../types'
import { CODE_PATTERN, amount } from './money'

/** Mirrors UpdateVariantsRequest. The threshold is typed as text; empty follows the store default. */
export const variantsSchema = z
  .object({
    variants: z.array(
      z.object({
        id: z.number(),
        sku: z
          .string()
          .trim()
          .min(1, 'catalog:validation.skuRequired')
          .max(64, 'catalog:validation.skuTooLong')
          .refine((sku) => sku === '' || CODE_PATTERN.test(sku), 'catalog:validation.codeFormat'),
        price: amount(2, 'catalog:validation.priceFormat'),
        average_cost: amount(4, 'catalog:validation.costFormat'),
        low_stock_threshold: z
          .string()
          .trim()
          .refine((value) => /^\d{0,7}$/.test(value), 'catalog:validation.thresholdFormat'),
      }),
    ),
  })
  .superRefine(({ variants }, context) => {
    const seen = new Set<string>()
    variants.forEach(({ sku }, index) => {
      if (seen.has(sku)) {
        context.addIssue({
          code: 'custom',
          path: ['variants', index, 'sku'],
          message: 'catalog:validation.skuRepeated',
        })
      }
      seen.add(sku)
    })
  })

export type VariantsFormValues = z.infer<typeof variantsSchema>

export function variantsFormValues(variants: Variant[]): VariantsFormValues {
  return {
    variants: variants.map((variant) => ({
      id: variant.id,
      sku: variant.sku,
      price: variant.price,
      average_cost: variant.average_cost,
      low_stock_threshold: variant.low_stock_threshold?.toString() ?? '',
    })),
  }
}

export function toVariantInputs(values: VariantsFormValues): VariantInput[] {
  return values.variants.map((variant) => ({
    ...variant,
    low_stock_threshold:
      variant.low_stock_threshold === '' ? null : Number(variant.low_stock_threshold),
  }))
}
