import {
  type ColumnDef,
  type RowData,
  type TableFeatures,
  tableFeatures,
} from '@tanstack/react-table'

declare module '@tanstack/react-table' {
  // Declaration merging must repeat the library's own type parameters.
  // oxlint-disable-next-line no-unused-vars
  interface ColumnMeta<TFeatures extends TableFeatures, TData extends RowData, TValue> {
    /** The API field this column sorts by; the column is not sortable without one. */
    sortField?: string
    /** Classes for the column's header and cells (for example hiding it on phones). */
    className?: string
  }
}

/** The table features every list uses: none, since the server sorts, filters and pages. */
export const listTableFeatures = tableFeatures({})
export type ListTableFeatures = typeof listTableFeatures

/**
 * One column of a list. Give sortable columns a plain-text `header` and
 * `meta.sortField` (the API's sort name); `meta.className` hides a column on small
 * screens (`hidden md:table-cell`).
 */
// oxlint-disable-next-line no-explicit-any -- each column's cell value has its own type
export type DataTableColumn<TData extends RowData> = ColumnDef<ListTableFeatures, TData, any>
