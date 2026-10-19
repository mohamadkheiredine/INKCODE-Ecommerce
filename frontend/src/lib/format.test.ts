import { describe, expect, it } from 'vitest'
import { createFormatters } from './format'

const october19 = new Date('2026-10-19T09:30:00')

describe('Formatting numbers, money and dates', () => {
  it('uses Western digits in Arabic, with Arabic month and day names', () => {
    const format = createFormatters('ar')

    expect(format.number(1234567.5)).toBe('1,234,567.5')
    expect(format.money('1234.50')).toContain('1,234.50')
    expect(format.date(october19, { dateStyle: 'full' })).toBe('الاثنين، 19 أكتوبر 2026')
    expect(format.dateTime(october19)).not.toMatch(/[٠-٩]/)
  })

  it('says how long ago something happened, in the largest whole unit', () => {
    const en = createFormatters('en')
    const ar = createFormatters('ar')

    expect(en.relativeTime('2026-10-19T09:29:50', october19)).toBe('now')
    expect(en.relativeTime('2026-10-19T09:25:00', october19)).toBe('5 minutes ago')
    expect(en.relativeTime('2026-10-19T06:00:00', october19)).toBe('3 hours ago')
    expect(en.relativeTime('2026-10-18T09:00:00', october19)).toBe('yesterday')
    expect(en.relativeTime('2026-10-05T09:00:00', october19)).toBe('2 weeks ago')
    expect(ar.relativeTime('2026-10-19T09:25:00', october19)).toBe('قبل 5 دقائق')
  })

  it('formats English the usual way', () => {
    const format = createFormatters('en')

    expect(format.money('1234.50')).toBe('$1,234.50')
    expect(format.date(october19, { dateStyle: 'full' })).toBe('Monday, October 19, 2026')
  })
})
