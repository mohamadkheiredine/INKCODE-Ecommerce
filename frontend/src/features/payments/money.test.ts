import { describe, expect, it } from 'vitest'
import { anOrder } from '@/test/msw/orders'
import { fromCents, refundNeeded, toCents, totalAfterCancellingCents } from './money'

describe('Refund needed maths', () => {
  it('reads and writes amounts in cents without floats', () => {
    expect(toCents('67.97')).toBe(6797)
    expect(toCents('5')).toBe(500)
    expect(toCents('-47.9')).toBe(-4790)
    expect(fromCents(-4790)).toBe('-47.90')
    expect(fromCents(5)).toBe('0.05')
  })

  it('re-prices a Partial cancellation the way the API does, Coupon share and VAT per line', () => {
    const order = anOrder({ coupon: { id: 1, code: 'TEN', type: 'percentage', value: '10.00' } })
    order.lines = order.lines!.map((line) => ({ ...line, coupon_eligible: true }))
    const lineId = order.lines[0]!.id

    // 2 × 19.99 = 39.98, 10% off = 4.00, VAT 5% of 35.98 = 1.80, + 5.00 shipping.
    expect(totalAfterCancellingCents(order, [{ line_id: lineId, quantity: 1 }])).toBe(4278)
  })

  it('needs no Refund when the Order holds no more than it will owe', () => {
    const paid = anOrder({ captured_amount: '30.00', refunded_amount: '0.00' })

    expect(refundNeeded(paid, 4698)).toBe('0.00')
    expect(refundNeeded(paid, 0)).toBe('30.00')
  })
})
