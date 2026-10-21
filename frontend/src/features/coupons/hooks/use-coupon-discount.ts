import { useFormatters } from '@/lib/format'
import type { Coupon } from '../types'

/** Writes a Coupon's discount: "10%" or "$5.00". */
export function useCouponDiscount() {
  const { money, number } = useFormatters()
  return (coupon: Pick<Coupon, 'type' | 'value'>) =>
    coupon.type === 'percentage' ? `${number(Number(coupon.value))}%` : money(coupon.value)
}
