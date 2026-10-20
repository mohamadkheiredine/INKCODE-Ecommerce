import { describe, expect, it } from 'vitest'
import { combinations, countMissing } from './variant-combinations'

describe('Variant combinations', () => {
  it('pairs every chosen Color with every chosen Size', () => {
    const colorsBySizes = combinations({ 1: [11, 12, 13], 2: [21, 22, 23, 24] })

    expect(colorsBySizes).toHaveLength(12)
    expect(colorsBySizes).toContainEqual([13, 24])
  })

  it('gives a Product without Attributes one empty combination', () => {
    expect(combinations({})).toEqual([[]])
    expect(combinations({ 1: [] })).toEqual([[]])
  })

  it('counts only the combinations the Product does not have, whatever the value order', () => {
    const existing = [{ value_ids: [21, 11] }, { value_ids: [11, 22] }]

    expect(countMissing({ 1: [11, 12], 2: [21, 22] }, existing)).toBe(2)
    expect(countMissing({ 1: [11], 2: [21, 22] }, existing)).toBe(0)
  })
})
