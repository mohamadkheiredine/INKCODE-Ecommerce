import { type Control, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { createFormatters } from '@/lib/format'
import { type SettingsFormValues, settingsSchema } from '../schemas/settings-schema'

const SAMPLE_PRICE = '1234.50'
const SAMPLE_TAXED = '100.00'

const previewSchema = settingsSchema.pick({
  currency_code: true,
  currency_symbol: true,
  vat_rate: true,
})

/**
 * How amounts will read with the currency and VAT being typed, before saving. VAT on
 * 100.00 is the rate itself, so the preview needs no arithmetic on money.
 */
export function MoneyPreview({ control }: { control: Control<SettingsFormValues> }) {
  const { t, i18n } = useTranslation('settings')
  const [code, symbol, vatRate] = useWatch({
    control,
    name: ['currency_code', 'currency_symbol', 'vat_rate'],
  })
  const parsed = previewSchema.safeParse({
    currency_code: code,
    currency_symbol: symbol,
    vat_rate: vatRate,
  })

  if (!parsed.success) {
    return (
      <p role="status" className="rounded-xl bg-muted/60 px-4 py-3 text-sm text-muted-foreground">
        {t('preview.invalid')}
      </p>
    )
  }

  const { currency_code, currency_symbol, vat_rate } = parsed.data
  const { money } = createFormatters(i18n.resolvedLanguage, {
    code: currency_code,
    symbol: currency_symbol,
  })

  return (
    <dl
      role="status"
      aria-label={t('preview.title')}
      className="grid gap-2 rounded-xl bg-muted/60 px-4 py-3 text-sm"
    >
      <div className="flex items-center justify-between gap-4">
        <dt className="text-muted-foreground">{t('preview.amount')}</dt>
        <dd className="font-medium tabular-nums">{money(SAMPLE_PRICE)}</dd>
      </div>
      <div className="flex items-center justify-between gap-4">
        <dt className="text-muted-foreground">
          {t('preview.vat', { amount: money(SAMPLE_TAXED) })}
        </dt>
        <dd className="font-medium tabular-nums">{money(vat_rate as `${number}`)}</dd>
      </div>
    </dl>
  )
}
