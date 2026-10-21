import { z } from 'zod'
import { isAmount } from '@/lib/list-query'
import type { Coupon, CouponInput } from '../types'

const LIMIT = /^$|^[1-9]\d{0,6}$/
const DATE = /^\d{4}-\d{2}-\d{2}$/

/** As StoreCouponRequest: code, type and value, dates, limits, minimum, eligible items. */
export const couponSchema = z
  .object({
    code: z
      .string()
      .trim()
      .min(3, 'coupons:validation.codeLength')
      .max(40, 'coupons:validation.codeLength')
      .regex(/^[A-Za-z0-9_-]+$/, 'coupons:validation.codeFormat'),
    type: z.enum(['percentage', 'fixed']),
    value: z.string().trim().refine(isAmount, 'coupons:validation.value'),
    starts_on: z.string().regex(DATE, 'coupons:validation.startRequired'),
    ends_on: z.string(),
    usage_limit: z.string().trim().regex(LIMIT, 'coupons:validation.limit'),
    per_customer_limit: z.string().trim().regex(LIMIT, 'coupons:validation.limit'),
    minimum_order_amount: z
      .string()
      .trim()
      .refine((value) => value === '' || isAmount(value), 'coupons:validation.amount'),
    products: z.array(z.object({ id: z.number(), name: z.string() })),
    category_ids: z.array(z.number()),
  })
  .superRefine((values, context) => {
    const value = Number(values.value)
    if (isAmount(values.value) && value <= 0) {
      context.addIssue({ code: 'custom', path: ['value'], message: 'coupons:validation.value' })
    }
    if (values.type === 'percentage' && value > 100) {
      context.addIssue({ code: 'custom', path: ['value'], message: 'coupons:validation.percent' })
    }
    if (values.ends_on !== '' && values.ends_on < values.starts_on) {
      context.addIssue({
        code: 'custom',
        path: ['ends_on'],
        message: 'coupons:validation.endBeforeStart',
      })
    }
  })

export type CouponFormValues = z.infer<typeof couponSchema>

export function couponFormValues(coupon: Coupon | null, today: string): CouponFormValues {
  return {
    code: coupon?.code ?? '',
    type: coupon?.type ?? 'percentage',
    value: coupon ? String(coupon.value) : '',
    starts_on: coupon?.starts_on ?? today,
    ends_on: coupon?.ends_on ?? '',
    usage_limit: coupon?.usage_limit ? String(coupon.usage_limit) : '',
    per_customer_limit: coupon?.per_customer_limit ? String(coupon.per_customer_limit) : '',
    minimum_order_amount:
      coupon && Number(coupon.minimum_order_amount) > 0 ? String(coupon.minimum_order_amount) : '',
    products: coupon?.products ?? [],
    category_ids: coupon?.categories?.map((category) => category.id) ?? [],
  }
}

export function toCouponInput(values: CouponFormValues): CouponInput {
  return {
    code: values.code.trim().toUpperCase(),
    type: values.type,
    value: values.value.trim(),
    starts_on: values.starts_on,
    ends_on: values.ends_on === '' ? null : values.ends_on,
    usage_limit: values.usage_limit === '' ? null : Number(values.usage_limit),
    per_customer_limit: values.per_customer_limit === '' ? null : Number(values.per_customer_limit),
    minimum_order_amount: values.minimum_order_amount === '' ? '0' : values.minimum_order_amount,
    product_ids: values.products.map((product) => product.id),
    category_ids: values.category_ids,
  }
}
