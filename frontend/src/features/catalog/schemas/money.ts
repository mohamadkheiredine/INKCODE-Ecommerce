import { z } from 'zod'

/**
 * Amounts stay decimal strings from the input to the API ("19.90"), never floats: the
 * API does the arithmetic. At most 8 digits before the point, `decimals` after it.
 */
export function amount(decimals: 2 | 4, message: string) {
  const pattern = decimals === 2 ? /^\d{1,8}(?:\.\d{1,2})?$/ : /^\d{1,8}(?:\.\d{1,4})?$/
  return z
    .string()
    .trim()
    .refine((value) => pattern.test(value), message)
}

/** What SKUs and value codes look like (App\Support\Sku): "TSHIRT-RED-M". */
export const CODE_PATTERN = /^[A-Z0-9]+(?:-[A-Z0-9]+)*$/

/** A code suggested from a name: "Navy blue" → "NAVY-BLUE"; empty for a name with no Latin letters. */
export function codeFrom(name: string, maxLength = 20): string {
  return name
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, maxLength)
    .replace(/-+$/, '')
}
