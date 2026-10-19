export { cn } from 'cn'

/** Up to two capital initials ("Ahmed Ali" → "AA"), for avatars and logos without a picture. */
export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}
