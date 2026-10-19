import { useMemo } from 'react'
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

export type Formatters = ReturnType<typeof createFormatters>

export function createFormatters(language: string | undefined) {
  const locale = intlLocale(language)

  return {
    locale,
    number: (value: number, options?: Intl.NumberFormatOptions) =>
      new Intl.NumberFormat(locale, options).format(value),
    money: (amount: Amount, currency = 'USD') =>
      new Intl.NumberFormat(locale, { style: 'currency', currency }).format(amount),
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

/** Formatters for the active interface language; they update when the language switches. */
export function useFormatters(): Formatters {
  const { i18n } = useTranslation()
  const language = i18n.resolvedLanguage
  return useMemo(() => createFormatters(language), [language])
}
