import type { TFunction } from 'i18next'
import type { Formatters } from '@/lib/format'
import type { ReportColumnType, ReportValue, SalesInterval } from './types'

/**
 * A report value as the screen shows it, in the active locale: money with the Store
 * currency, percentages, days (months for the Sales report by month), and stored keys
 * (reasons, New / Returning) translated.
 */
export function formatReportValue(
  value: ReportValue,
  type: ReportColumnType,
  format: Formatters,
  t: TFunction<'reports'>,
  interval: SalesInterval = 'day',
): string {
  if (value === null || value === '') return '—'
  switch (type) {
    case 'money':
      return format.money(String(value) as `${number}`)
    case 'integer':
      return format.number(Number(value))
    case 'percent':
      return format.number(Number(value) / 100, { style: 'percent', maximumFractionDigits: 1 })
    case 'date':
      return format.date(
        `${value}T00:00:00`,
        interval === 'month' ? { month: 'long', year: 'numeric' } : { dateStyle: 'medium' },
      )
    case 'enum':
      return t(`values.${String(value)}`, { defaultValue: String(value) })
    case 'text':
      return String(value)
  }
}
