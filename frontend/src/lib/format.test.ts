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

  it('formats English the usual way', () => {
    const format = createFormatters('en')

    expect(format.money('1234.50')).toBe('$1,234.50')
    expect(format.date(october19, { dateStyle: 'full' })).toBe('Monday, October 19, 2026')
  })
})
