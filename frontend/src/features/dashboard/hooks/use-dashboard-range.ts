import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { asPreset, DEFAULT_PRESET, resolveRange } from '../date-range'
import type { DatePreset, DateRange } from '../types'

/**
 * The dashboard's date range, kept in the URL (`range`, plus `from` and `to` for a custom
 * one) so a reload or a shared link shows the same period.
 */
export function useDashboardRange() {
  const [params, setParams] = useSearchParams()
  const [today] = useState(() => new Date())
  const preset = asPreset(params.get('range'))
  const range = resolveRange(preset, today, {
    from: params.get('from') ?? undefined,
    to: params.get('to') ?? undefined,
  })

  const update = (next: DatePreset, custom?: DateRange) =>
    setParams(
      (current) => {
        const result = new URLSearchParams(current)
        result.delete('from')
        result.delete('to')
        if (next === DEFAULT_PRESET) result.delete('range')
        else result.set('range', next)
        if (next === 'custom') {
          const days = custom ?? range
          result.set('from', days.from)
          result.set('to', days.to)
        }
        return result
      },
      { replace: true },
    )

  return {
    preset,
    range,
    setPreset: (next: DatePreset) => update(next),
    setCustomRange: (custom: DateRange) => update('custom', custom),
  }
}
