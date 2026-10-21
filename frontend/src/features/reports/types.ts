/** The six reports, in menu order; each is also its URL segment on the API. */
export const REPORT_NAMES = [
  'sales',
  'products',
  'categories',
  'customers',
  'returns',
  'inventory',
] as const
export type ReportName = (typeof REPORT_NAMES)[number]

export const SALES_INTERVALS = ['day', 'week', 'month'] as const
export type SalesInterval = (typeof SALES_INTERVALS)[number]

export const PRODUCT_RANKS = ['sales', 'units'] as const
export type ProductRank = (typeof PRODUCT_RANKS)[number]

export type ExportFormat = 'csv' | 'xlsx'

/** How a column's values read: `enum` values are stored keys the screen translates. */
export type ReportColumnType = 'text' | 'date' | 'integer' | 'money' | 'percent' | 'enum'

export type ReportValue = string | number | null

/** A summary figure, with the previous period's value and the change in percent. */
export type ReportFigure = {
  key: string
  type: ReportColumnType
  value: string | number
  /** Null for a snapshot of now (Stock value, On-hand, low stock). */
  previous: string | number | null
  /** Null on a zero baseline or for a snapshot. */
  change: number | null
}

/** A report as the API builds it; the exports carry the same rows. */
export type Report = {
  report: ReportName
  range: { from: string; to: string; previous_from: string; previous_to: string }
  columns: { key: string; type: ReportColumnType }[]
  summary: ReportFigure[]
  rows: Record<string, ReportValue>[]
}

/** What a report is asked for: the range plus the Sales interval and Products rank. */
export type ReportParams = {
  from: string
  to: string
  interval?: SalesInterval
  rank?: ProductRank
}
