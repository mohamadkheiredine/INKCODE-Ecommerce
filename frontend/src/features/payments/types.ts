import type { Amount } from '@/lib/format'

/** How a Customer pays, mirroring `App\Enums\PaymentMethod`. */
export const PAYMENT_METHODS = ['cash_on_delivery', 'manual', 'card'] as const
export type PaymentMethod = (typeof PAYMENT_METHODS)[number]

export const PAYMENT_KINDS = ['payment', 'refund'] as const
export type PaymentKind = (typeof PAYMENT_KINDS)[number]

/** Whether a ledger row moved money; only a declined Card charge is Failed. */
export const PAYMENT_OUTCOMES = ['succeeded', 'failed'] as const
export type PaymentOutcome = (typeof PAYMENT_OUTCOMES)[number]

/** The summary read from an Order's Payment ledger, mirroring `App\Enums\PaymentStatus`. */
export const PAYMENT_STATUSES = [
  'unpaid',
  'partially_paid',
  'paid',
  'partially_refunded',
  'refunded',
] as const
export type PaymentStatus = (typeof PAYMENT_STATUSES)[number]

/** The mock Card gateway's test option; no card details are ever asked for. */
export type CardOutcome = 'approve' | 'decline'

/** One Payment ledger row. `order` is there in the payments list. */
export type Payment = {
  id: number
  kind: PaymentKind
  method: PaymentMethod
  amount: Amount
  status: PaymentOutcome
  gateway_reference: string | null
  note: string | null
  idempotency_key: string | null
  user?: { id: number; name: string } | null
  order?: { id: number; number: string; currency_code: string; payment_status: PaymentStatus }
  created_at: string
}

export type PaymentInput = {
  method: PaymentMethod
  amount: string
  card_outcome?: CardOutcome
  note: string
}

export type RefundInput = { method: PaymentMethod; amount: string; note: string }

/** One Order whose ledger total differs from what it owes (`difference` = ledger − owed). */
export type ReconciliationRow = {
  id: number
  number: string
  status: string
  payment_status: PaymentStatus
  placed_at: string
  customer: { id: number; name: string }
  currency_code: string
  total: Amount
  amount_owed: Amount
  ledger_total: Amount
  difference: Amount
}
