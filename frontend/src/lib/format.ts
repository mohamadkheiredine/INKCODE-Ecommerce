import { createContext, useContext, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { asLanguage } from './i18n'

/**
 * The `Intl` locale for an interface language. Arabic keeps Arabic month and day names
 * but uses Western digits (`nu-latn`), so amounts read the same in both languages.
 */
export function intlLocale(language: string | undefined): string {
  return asLanguage(language) === 'ar' ? 'ar-u-nu-latn' : 'en'
}

/** Amounts arrive from the API as decimal strings ("1234.50") and are never turned into floats. */
export type Amount = number | `${number}`

/** The Store currency: its ISO code and the symbol chosen for it in Settings. */
export type StoreCurrency = { code: string; symbol: string }

/** The Store currency until the store's own settings have loaded. */
export const DEFAULT_CURRENCY: StoreCurrency = { code: 'USD', symbol: '$' }

/** The Store currency every amount on screen is written in; the app shell provides it. */
export const CurrencyContext = createContext<StoreCurrency>(DEFAULT_CURRENCY)

export type Formatters = ReturnType<typeof createFormatters>

export function createFormatters(
  language: string | undefined,
  currency: StoreCurrency = DEFAULT_CURRENCY,
) {
  const locale = intlLocale(language)
  // Every amount is stored to the cent, so it is shown to the cent in any currency.
  const moneyFormat = new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: currency.code,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })

  return {
    locale,
    number: (value: number, options?: Intl.NumberFormatOptions) =>
      new Intl.NumberFormat(locale, options).format(value),
    /** An amount in the Store currency, written with the symbol chosen in Settings. */
    money: (amount: Amount) =>
      moneyFormat
        .formatToParts(amount)
        .map((part) => (part.type === 'currency' ? currency.symbol : part.value))
        .join(''),
    date: (value: Date | string, options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' }) =>
      new Intl.DateTimeFormat(locale, options).format(new Date(value)),
    dateTime: (value: Date | string) =>
      new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(
        new Date(value),
      ),
    /** "5 minutes ago", "yesterday", "now": in the largest whole unit that fits. */
    relativeTime: (value: Date | string, now: Date | number = Date.now()) => {
      const seconds = Math.round((new Date(value).getTime() - new Date(now).getTime()) / 1000)
      const relative = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' })
      for (const [unit, size] of RELATIVE_TIME_UNITS) {
        if (Math.abs(seconds) >= size) return relative.format(Math.trunc(seconds / size), unit)
      }
      return relative.format(0, 'second')
    },
  }
}

/** Each unit with its length in seconds, largest first. */
const RELATIVE_TIME_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
  ['year', 365 * 24 * 3600],
  ['month', 30 * 24 * 3600],
  ['week', 7 * 24 * 3600],
  ['day', 24 * 3600],
  ['hour', 3600],
  ['minute', 60],
]

/**
 * Formatters for the active interface language and the Store currency; they update when
 * either changes.
 */
export function useFormatters(): Formatters {
  const { i18n } = useTranslation()
  const language = i18n.resolvedLanguage
  const currency = useContext(CurrencyContext)
  return useMemo(() => createFormatters(language, currency), [language, currency])
}
