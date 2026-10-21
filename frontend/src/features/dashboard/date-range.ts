import { DATE_PRESETS, type DatePreset, type DateRange } from './types'

export const DEFAULT_PRESET: DatePreset = '30d'

/** A day as the API takes it (YYYY-MM-DD), in the browser's own calendar. */
export function isoDay(date: Date): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

function daysBefore(today: Date, days: number): Date {
  return new Date(today.getFullYear(), today.getMonth(), today.getDate() - days)
}

const ISO_DAY = /^\d{4}-\d{2}-\d{2}$/

export function asPreset(value: string | null): DatePreset {
  return DATE_PRESETS.find((preset) => preset === value) ?? DEFAULT_PRESET
}

/**
 * The days a preset covers, both included. A custom range keeps the days it was given
 * (swapped if they are backwards) and falls back to the default preset without them.
 */
export function resolveRange(
  preset: DatePreset,
  today: Date,
  custom: Partial<DateRange> = {},
): DateRange {
  const to = isoDay(today)
  switch (preset) {
    case 'today':
      return { from: to, to }
    case '7d':
      return { from: isoDay(daysBefore(today, 6)), to }
    case 'month':
      return { from: isoDay(new Date(today.getFullYear(), today.getMonth(), 1)), to }
    case 'custom': {
      const { from, to: end } = custom
      if (from && end && ISO_DAY.test(from) && ISO_DAY.test(end)) {
        return from <= end ? { from, to: end } : { from: end, to: from }
      }
      return resolveRange(DEFAULT_PRESET, today)
    }
    case '30d':
      return { from: isoDay(daysBefore(today, 29)), to }
  }
}
