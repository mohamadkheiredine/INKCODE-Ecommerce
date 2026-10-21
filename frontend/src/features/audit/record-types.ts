import type { AuditEntry } from './types'

type RecordType = {
  /**
   * Where the record can be seen, or null when it has no page of its own. Records shown
   * only in a list open that list searched for the record's name.
   */
  link: ((entry: AuditEntry) => string) | null
}

const searchedList = (path: string) => (entry: AuditEntry) =>
  `${path}?${new URLSearchParams({ search: entry.record_label })}`

/**
 * Every record type the audit trail writes (`auditRecordType()` on the API's models).
 * A module that starts recording a new type adds it here and its name under
 * `recordTypes` in both audit.json locales; nothing else changes.
 */
export const RECORD_TYPES = {
  category: { link: searchedList('/catalog/categories') },
  brand: { link: searchedList('/catalog/brands') },
  attribute: { link: searchedList('/catalog/attributes') },
  product: { link: (entry) => `/catalog/products/${entry.record_id}` },
  variant: { link: null },
  customer: { link: (entry) => `/customers/${entry.record_id}` },
  customer_address: { link: null },
  customer_note: { link: null },
  order_note: { link: null },
  shipment: { link: null },
  shipping_zone: { link: (entry) => `/shipping/zones/${entry.record_id}` },
  shipping_method: { link: null },
  carrier: { link: searchedList('/shipping/carriers') },
  store_settings: { link: () => '/settings' },
  user: { link: null },
  order: { link: (entry) => `/orders/${entry.record_id}` },
} satisfies Record<string, RecordType>

export type RecordTypeKey = keyof typeof RECORD_TYPES

export const RECORD_TYPE_KEYS = Object.keys(RECORD_TYPES) as RecordTypeKey[]

export function isRecordType(value: string): value is RecordTypeKey {
  return Object.hasOwn(RECORD_TYPES, value)
}

/** The page that shows the entry's record, when there is one. */
export function recordLink(entry: AuditEntry): string | null {
  return isRecordType(entry.record_type)
    ? ((RECORD_TYPES[entry.record_type] as RecordType).link?.(entry) ?? null)
    : null
}
