import { HttpResponse, http } from 'msw/http'
import type { Coupon, CouponInput, CouponRedemption } from '@/features/coupons/types'
import { apiError } from './handlers'
import { listResponse } from './lists'
import { server } from './server'

let nextId = 700
const STAMP = '2026-10-01T09:00:00+00:00'

export function aCoupon(overrides: Partial<Coupon> = {}): Coupon {
  const id = overrides.id ?? nextId++
  return {
    id,
    code: `CODE${id}`,
    type: 'percentage',
    value: '10.00',
    starts_on: '2026-10-01',
    ends_on: null,
    state: 'active',
    usage_limit: null,
    per_customer_limit: null,
    minimum_order_amount: '0.00',
    times_used: 0,
    products: [],
    categories: [],
    created_at: STAMP,
    updated_at: STAMP,
    ...overrides,
  }
}

export function aRedemption(overrides: Partial<CouponRedemption> = {}): CouponRedemption {
  const id = overrides.id ?? nextId++
  return {
    id,
    order: {
      id: 900 + id,
      number: `ORD-2026-${String(id).padStart(6, '0')}`,
      status: 'confirmed',
      total: '65.67',
      currency_code: 'USD',
    },
    customer: { id: 1, name: 'Rana Haddad', email: 'rana@example.com' },
    discount_amount: '7.00',
    redeemed_at: STAMP,
    released_at: null,
    ...overrides,
  }
}

/**
 * The Coupons API in memory, answering like the real one: the list convention (search by
 * code, filters by type and state), one Coupon with its eligible items, 422 for a taken
 * code, and each Coupon's usage.
 */
export function couponStore(initial: Coupon[] = [], redemptions: CouponRedemption[] = []) {
  const coupons = [...initial]
  const bodies: unknown[] = []
  const find = (id: unknown) => coupons.find((coupon) => coupon.id === Number(id))

  server.use(
    http.get('*/api/v1/coupons', ({ request }) =>
      listResponse(coupons, request, {
        searchIn: ['code'],
        defaultSort: '-created_at',
        filters: {
          type: (row, value) => row.type === value,
          state: (row, value) => row.state === value,
        },
      }),
    ),
    http.get('*/api/v1/coupons/:id', ({ params }) => {
      const coupon = find(params.id)
      return coupon
        ? HttpResponse.json({ data: coupon })
        : apiError(404, 'not_found', 'The requested resource was not found.')
    }),
    http.get('*/api/v1/coupons/:id/redemptions', ({ request }) =>
      listResponse(redemptions, request, { searchIn: [], defaultSort: '-redeemed_at' }),
    ),
    http.post('*/api/v1/coupons', async ({ request }) => {
      const input = (await request.json()) as CouponInput
      bodies.push(input)
      if (coupons.some((coupon) => coupon.code === input.code)) {
        return apiError(422, 'validation_failed', 'The code has already been taken.', {
          code: ['The code has already been taken.'],
        })
      }
      const coupon = aCoupon({
        code: input.code,
        type: input.type,
        value: Number(input.value).toFixed(2) as Coupon['value'],
      })
      coupons.push(coupon)
      return HttpResponse.json({ data: coupon }, { status: 201 })
    }),
  )

  return { coupons, bodies }
}
