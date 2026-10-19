export { cn } from 'cn'

/**
 * Up to two capital initials ("Ahmed Ali" → "AA", "Cedar & Co." → "CC"), for avatars and
 * logos without a picture. Words without a letter or digit are skipped.
 */
export function initials(name: string): string {
  return name
    .split(/\s+/)
    .map((word) => word.match(/[\p{L}\p{N}]/u)?.[0])
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase()
}
