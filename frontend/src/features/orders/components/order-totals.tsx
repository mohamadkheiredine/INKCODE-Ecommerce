import { useTranslation } from 'react-i18next'
import type { Amount } from '@/lib/format'
import { useFormatters } from '@/lib/format'

type Totals = {
  items_total: Amount
  discount_total: Amount
  vat_total: Amount
  shipping_total: Amount
  total: Amount
}

/** Items, discount (when there is one), VAT, shipping and the total, as the API computed them. */
export function OrderTotals({
  totals,
  shippingLabel,
  couponCode,
}: {
  totals: Totals
  shippingLabel?: string
  /** The Coupon behind the discount, named on its row. */
  couponCode?: string | null
}) {
  const { t } = useTranslation('orders')
  const { money } = useFormatters()
  const rows: [string, Amount][] = [
    [t('totals.items'), totals.items_total],
    ...(Number(totals.discount_total) > 0
      ? ([
          [
            couponCode
              ? t('totals.discountWithCoupon', { code: couponCode })
              : t('totals.discount'),
            `-${totals.discount_total}` as Amount,
          ],
        ] as [string, Amount][])
      : []),
    [t('totals.vat'), totals.vat_total],
    [
      shippingLabel ? t('totals.shippingVia', { method: shippingLabel }) : t('totals.shipping'),
      totals.shipping_total,
    ],
  ]

  return (
    <dl className="flex flex-col gap-2 text-sm">
      {rows.map(([label, amount]) => (
        <div key={label} className="flex items-center justify-between gap-4">
          <dt className="text-muted-foreground" dir="auto">
            {label}
          </dt>
          <dd className="tabular-nums">{money(amount)}</dd>
        </div>
      ))}
      <div className="mt-1 flex items-center justify-between gap-4 border-t pt-3">
        <dt className="font-medium">{t('totals.total')}</dt>
        <dd className="text-lg font-semibold tabular-nums" data-testid="order-total">
          {money(totals.total)}
        </dd>
      </div>
    </dl>
  )
}
