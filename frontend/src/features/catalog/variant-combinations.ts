/**
 * What generating Variants will do, before asking the API: every combination of one
 * chosen value per Attribute, and which of them the Product doesn't have yet (the API
 * creates only those, GenerateVariants).
 */

/** The chosen value ids, per Attribute id. */
export type Selection = Record<number, number[]>

const keyOf = (valueIds: readonly number[]) => [...valueIds].sort((a, b) => a - b).join('-')

/** Every combination of the selection; one empty combination when nothing is chosen. */
export function combinations(selection: Selection): number[][] {
  return Object.values(selection)
    .filter((values) => values.length > 0)
    .reduce<number[][]>(
      (combos, values) => combos.flatMap((combo) => values.map((value) => [...combo, value])),
      [[]],
    )
}

/** How many of the selection's combinations no existing Variant has. */
export function countMissing(selection: Selection, existing: readonly { value_ids: number[] }[]) {
  const have = new Set(existing.map((variant) => keyOf(variant.value_ids)))
  return combinations(selection).filter((combo) => !have.has(keyOf(combo))).length
}
