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
  }
}

/** Formatters for the active interface language; they update when the language switches. */
export function useFormatters(): Formatters {
  const { i18n } = useTranslation()
  const language = i18n.resolvedLanguage
  return useMemo(() => createFormatters(language), [language])
}
