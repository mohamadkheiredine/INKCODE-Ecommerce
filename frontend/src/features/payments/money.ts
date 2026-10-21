import type { LineCancellation, Order } from '@/features/orders/types'
import type { Amount } from '@/lib/format'

/** An API amount ("12.30") in whole cents, without going through a float. */
export function toCents(amount: Amount): number {
  const [whole = '0', fraction = ''] = String(amount).split('.')
  const sign = whole.startsWith('-') ? -1 : 1
  return sign * (Math.abs(Number(whole)) * 100 + Number(fraction.padEnd(2, '0').slice(0, 2)))
}

export function fromCents(cents: number): Amount {
  const sign = cents < 0 ? '-' : ''
  const absolute = Math.abs(cents)
  return `${sign}${Math.floor(absolute / 100)}.${String(absolute % 100).padStart(2, '0')}` as Amount
}

/** Whether a typed amount is a positive amount with at most two decimals. */
export function isAmount(value: string): boolean {
  return /^\d+(\.\d{1,2})?$/.test(value.trim()) && toCents(value.trim() as Amount) > 0
}

/** What the store still holds for the Order: Captured amount minus Refunds. */
export function netCapturedCents(order: Order): number {
  return toCents(order.captured_amount) - toCents(order.refunded_amount)
}

/**
 * Each line's share of the Order's Coupon discount, in cents, as
 * `OrderPricing::discounts()` spreads it: a percentage of the eligible lines (half up) or
 * a fixed amount (at most what they are worth), shared by value and cut to the cent, with
 * the cents left over going to the largest lines first.
 */
function couponShares(order: Order, amounts: number[]): number[] {
  const lines = order.lines ?? []
  const shares = amounts.map(() => 0)
  const eligible = lines.flatMap((line, index) => (line.coupon_eligible ? [index] : []))
  const eligibleTotal = eligible.reduce((sum, index) => sum + amounts[index]!, 0)
  if (!order.coupon || eligibleTotal <= 0) return shares

  const value = toCents(order.coupon.value)
  const discount =
    order.coupon.type === 'percentage'
      ? Math.floor((eligibleTotal * value + 5000) / 10000)
      : Math.min(value, eligibleTotal)

  let remainder = discount
  for (const index of eligible) {
    shares[index] = Math.floor((discount * amounts[index]!) / eligibleTotal)
    remainder -= shares[index]!
  }
  const largestFirst = [...eligible].sort((a, b) => amounts[b]! - amounts[a]! || a - b)
  for (const index of largestFirst) {
    if (remainder <= 0) break
    const extra = Math.min(amounts[index]! - shares[index]!, remainder)
    shares[index]! += extra
    remainder -= extra
  }
  return shares
}

/**
 * The Order's total after Partial cancellation, priced the way the API re-prices it
 * (`App\Services\OrderPricing::reprice`): quantity × unit price per line less its Coupon
 * share, VAT at the Order's rate on taxable lines rounded half up to cents per line,
 * shipping kept.
 */
export function totalAfterCancellingCents(order: Order, cancellations: LineCancellation[]): number {
  const rate = toCents(order.vat_rate)
  const cancelled = new Map(cancellations.map((line) => [line.line_id, line.quantity]))
  const lines = order.lines ?? []
  const amounts = lines.map(
    (line) => (line.quantity - (cancelled.get(line.id) ?? 0)) * toCents(line.unit_price),
  )
  const shares = couponShares(order, amounts)
  const linesTotal = lines.reduce((sum, line, index) => {
    const net = amounts[index]! - shares[index]!
    const vat = line.taxable ? Math.floor((net * rate + 5000) / 10000) : 0
    return sum + net + vat
  }, 0)
  return linesTotal + toCents(order.shipping_total)
}

/**
 * "Refund needed": what the Order holds beyond what it will owe after the change, which
 * the API gives back as a Refund in the same step. Zero when nothing goes back.
 */
export function refundNeeded(order: Order, owedAfterCents: number): Amount {
  return fromCents(Math.max(0, netCapturedCents(order) - owedAfterCents))
}
