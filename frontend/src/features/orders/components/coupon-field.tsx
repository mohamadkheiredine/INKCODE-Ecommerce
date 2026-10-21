import { CheckCircle2Icon, TicketPercentIcon } from 'lucide-react'
import type { Control, FieldPath, FieldValues } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { TextField } from '@/components/forms/form-fields'
import { Button } from '@/components/ui/button'
import { useFormatters } from '@/lib/format'
import type { QuoteCoupon } from '../types'

type CouponFieldProps<T extends FieldValues> = {
  control: Control<T>
  name: FieldPath<T>
  idPrefix: string
  /** Applies the typed code to the live quote. */
  onApply?: () => void
  /** What the live quote says about the applied code. */
  status?: QuoteCoupon | null
}

/**
 * The Order's one Coupon code. With `onApply` the code is tried in the live quote, which
 * says whether it applies (or why not); the API checks it again when the Order is saved.
 */
export function CouponField<T extends FieldValues>({
  control,
  name,
  idPrefix,
  onApply,
  status,
}: CouponFieldProps<T>) {
  const { t } = useTranslation('orders')
  const { money, number } = useFormatters()

  return (
    <div className="flex flex-col gap-2">
      <div className="flex items-start gap-2">
        <div className="min-w-0 flex-1">
          <TextField
            control={control}
            name={name}
            idPrefix={idPrefix}
            label={t('fields.coupon')}
            placeholder={t('couponPlaceholder')}
            maxLength={40}
            autoComplete="off"
            dir="ltr"
            className="text-start font-mono uppercase"
            onKeyDown={(event) => {
              if (event.key === 'Enter' && onApply) {
                event.preventDefault()
                onApply()
              }
            }}
          />
        </div>
        {onApply && (
          <Button type="button" variant="outline" className="mt-6 gap-2" onClick={onApply}>
            <TicketPercentIcon className="size-4" aria-hidden="true" />
            {t('applyCoupon')}
          </Button>
        )}
      </div>
      {status && (
        <p
          role="status"
          className={
            status.is_valid
              ? 'flex items-center gap-1.5 text-xs text-primary'
              : 'text-xs text-destructive'
          }
        >
          {status.is_valid ? (
            <>
              <CheckCircle2Icon className="size-3.5 shrink-0" aria-hidden="true" />
              {t('couponApplied', {
                code: status.code,
                discount:
                  status.type === 'percentage'
                    ? `${number(Number(status.value))}%`
                    : money(status.value),
              })}
            </>
          ) : (
            status.message
          )}
        </p>
      )}
    </div>
  )
}
