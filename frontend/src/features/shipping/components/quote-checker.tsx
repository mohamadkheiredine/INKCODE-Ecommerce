import { MapPinOffIcon, SearchCheckIcon } from 'lucide-react'
import { type FormEvent, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { CountrySelect } from '@/components/forms/country-select'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import { useShippingQuote } from '../hooks/use-shipping-zones'
import { useDeliveryDays } from '../labels'
import type { QuoteAddress } from '../types'

/**
 * "Which zone and methods does this address get?": the same quote Order creation asks
 * for, so Users can check their zones before an Order depends on them.
 */
export function QuoteChecker() {
  const { t } = useTranslation('shipping')
  const [countryCode, setCountryCode] = useState('')
  const [city, setCity] = useState('')
  const [address, setAddress] = useState<QuoteAddress | null>(null)
  const quote = useShippingQuote(address)

  const check = (event: FormEvent) => {
    event.preventDefault()
    if (countryCode && city.trim()) setAddress({ country_code: countryCode, city: city.trim() })
  }

  return (
    <Card role="region" aria-labelledby="quote-checker-title" className="js-reveal gap-4">
      <CardHeader>
        <CardTitle>
          <h2 id="quote-checker-title" className="flex items-center gap-2 text-base font-semibold">
            <SearchCheckIcon className="size-4 text-primary" aria-hidden="true" />
            {t('quote.title')}
          </h2>
        </CardTitle>
        <CardDescription>{t('quote.description')}</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <form
          noValidate
          onSubmit={check}
          className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
        >
          <CountrySelect
            value={countryCode}
            onChange={(event) => setCountryCode(event.target.value)}
            aria-label={t('fields.country')}
            placeholder={t('zones.chooseCountry')}
            className="[&_select]:bg-background"
          />
          <Input
            value={city}
            onChange={(event) => setCity(event.target.value)}
            aria-label={t('fields.city')}
            placeholder={t('quote.cityPlaceholder')}
            maxLength={120}
            dir="auto"
            className="h-10 rounded-lg bg-background"
          />
          <Button type="submit" className="h-10 gap-2" disabled={!countryCode || !city.trim()}>
            {quote.isFetching && <InlineSpinner className="size-4" />}
            {t('quote.submit')}
          </Button>
        </form>
        <QuoteResult quote={quote} />
      </CardContent>
    </Card>
  )
}

function QuoteResult({ quote }: { quote: ReturnType<typeof useShippingQuote> }) {
  const { t } = useTranslation('shipping')
  const { money } = useFormatters()
  const deliveryDays = useDeliveryDays()

  if (quote.isError) {
    return (
      <Alert variant="destructive" role="alert">
        <AlertDescription>{apiErrorMessage(quote.error)}</AlertDescription>
      </Alert>
    )
  }
  if (!quote.data) return null

  const { zone, methods, message } = quote.data
  return (
    <div aria-live="polite" className="flex flex-col gap-3">
      {message ? (
        <Alert>
          <MapPinOffIcon aria-hidden="true" />
          {zone && <AlertTitle>{t('quote.zone', { name: zone.name })}</AlertTitle>}
          <AlertDescription>{message}</AlertDescription>
        </Alert>
      ) : (
        <>
          <p className="text-sm font-medium" dir="auto">
            {t('quote.zone', { name: zone?.name })}
          </p>
          <ul className="divide-y rounded-lg border">
            {methods.map((method) => (
              <li
                key={method.id}
                className="flex items-center justify-between gap-4 px-3 py-2.5 text-sm"
              >
                <div className="flex min-w-0 flex-col">
                  <span className="truncate font-medium" dir="auto">
                    {method.name}
                  </span>
                  <span className="text-xs text-muted-foreground">
                    {deliveryDays(method.min_delivery_days, method.max_delivery_days)}
                  </span>
                </div>
                <span className="font-medium tabular-nums">{money(method.price)}</span>
              </li>
            ))}
          </ul>
        </>
      )}
    </div>
  )
}
