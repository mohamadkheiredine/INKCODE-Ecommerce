import type { Payment } from '@/features/payments/types'
import type { Amount } from '@/lib/format'

/** Where a Return is, mirroring `App\Enums\ReturnStatus`. */
export const RETURN_STATUSES = [
  'requested',
  'approved',
  'rejected',
  'received',
  'completed',
] as const
export type ReturnStatus = (typeof RETURN_STATUSES)[number]

/** Why the Customer sends items back, mirroring `App\Enums\ReturnReason`. */
export const RETURN_REASONS = [
  'damaged',
  'wrong_item',
  'not_as_described',
  'changed_mind',
  'other',
] as const
export type ReturnReason = (typeof RETURN_REASONS)[number]

/** Back into On-hand, or a Write-off, mirroring `App\Enums\RestockDecision`. */
export const RESTOCK_DECISIONS = ['restock', 'write_off'] as const
export type RestockDecision = (typeof RESTOCK_DECISIONS)[number]

/** Some units of one Order line in a Return, with what the Customer paid for them. */
export type ReturnLine = {
  id: number
  order_line_id: number
  product_name: string
  sku: string
  options: { attribute: string; value: string }[]
  quantity: number
  ordered_quantity: number
  paid_amount: Amount
  restock_decision: RestockDecision | null
  decided_at: string | null
}

/**
 * One Return. The list has `units`; one Return has its `lines` and `refund_suggestion`
 * (the items, the Order's shipping or null once refunded, and what the Order may still
 * refund).
 */
export type OrderReturn = {
  id: number
  number: string
  status: ReturnStatus
  reason: ReturnReason
  note: string | null
  decision_note: string | null
  refund_shipping: boolean
  refund_amount: Amount | null
  order?: {
    id: number
    number: string
    currency_code: string
    customer: { id: number; name: string } | null
  }
  user?: { id: number; name: string } | null
  refund?: Payment | null
  units?: number
  lines?: ReturnLine[]
  refund_suggestion?: { items: Amount; shipping: Amount | null; refundable: Amount }
  requested_at: string
  decided_at: string | null
  received_at: string | null
  completed_at: string | null
}

/** A Return as the Order page lists it. */
export type OrderReturnSummary = Pick<
  OrderReturn,
  'id' | 'number' | 'status' | 'reason' | 'requested_at'
>

export type ReturnRequestInput = {
  reason: ReturnReason
  note: string
  lines: { line_id: number; quantity: number }[]
}
