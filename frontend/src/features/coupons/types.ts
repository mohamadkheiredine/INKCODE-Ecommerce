import type { CouponType } from '@/features/orders/types'
import type { OrderStatus } from '@/features/orders/types'
import type { Amount } from '@/lib/format'

export type { CouponType }

/** A Coupon's state today: it starts later, it can be used, or it has ended. */
export const COUPON_STATES = ['active', 'scheduled', 'expired'] as const
export type CouponState = (typeof COUPON_STATES)[number]

/**
 * A Coupon as the API sends it. Amounts are decimal strings in the Store currency, dates
 * `YYYY-MM-DD`. One Coupon also has its eligible `products` and `categories`.
 */
export type Coupon = {
  id: number
  code: string
  type: CouponType
  value: Amount
  starts_on: string
  ends_on: string | null
  state: CouponState
  usage_limit: number | null
  per_customer_limit: number | null
  minimum_order_amount: Amount
  /** Uses not released (each Confirmed Order not fully cancelled). */
  times_used?: number
  products_count?: number
  categories_count?: number
  products?: { id: number; name: string }[]
  categories?: { id: number; name: string }[]
  created_at: string
  updated_at: string
}

export type CouponInput = {
  code: string
  type: CouponType
  value: string
  starts_on: string
  ends_on: string | null
  usage_limit: number | null
  per_customer_limit: number | null
  minimum_order_amount: string
  product_ids: number[]
  category_ids: number[]
}

/** One use of a Coupon: the Order, its Customer, the discount, and whether it still counts. */
export type CouponRedemption = {
  id: number
  order: { id: number; number: string; status: OrderStatus; total: Amount; currency_code: string }
  customer: { id: number; name: string; email: string | null }
  discount_amount: Amount
  redeemed_at: string
  released_at: string | null
}
