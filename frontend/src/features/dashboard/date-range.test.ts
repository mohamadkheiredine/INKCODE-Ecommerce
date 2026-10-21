import { describe, expect, it } from 'vitest'
import { asPreset, resolveRange } from './date-range'

const today = new Date(2026, 9, 15)

describe('resolveRange', () => {
  it('covers each preset with both days included', () => {
    expect(resolveRange('today', today)).toEqual({ from: '2026-10-15', to: '2026-10-15' })
    expect(resolveRange('7d', today)).toEqual({ from: '2026-10-09', to: '2026-10-15' })
    expect(resolveRange('30d', today)).toEqual({ from: '2026-09-16', to: '2026-10-15' })
    expect(resolveRange('month', today)).toEqual({ from: '2026-10-01', to: '2026-10-15' })
  })

  it('keeps a custom range, swaps a backwards one and falls back without days', () => {
    const custom = { from: '2026-08-01', to: '2026-08-31' }
    expect(resolveRange('custom', today, custom)).toEqual(custom)
    expect(resolveRange('custom', today, { from: '2026-08-31', to: '2026-08-01' })).toEqual(custom)
    expect(resolveRange('custom', today, { from: 'nope' })).toEqual(resolveRange('30d', today))
  })

  it('reads unknown presets as the default', () => {
    expect(asPreset('7d')).toBe('7d')
    expect(asPreset('yearly')).toBe('30d')
    expect(asPreset(null)).toBe('30d')
  })
})
