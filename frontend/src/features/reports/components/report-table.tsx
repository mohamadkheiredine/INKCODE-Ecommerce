import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useFormatters } from '@/lib/format'
import { cn } from '@/lib/utils'
import { formatReportValue } from '../format-value'
import type { Report, SalesInterval } from '../types'

const PAGE_SIZE = 25

/**
 * The report's rows, exactly as the API built them (and as the exports carry them), a page
 * at a time; numbers line up at the end of their cells.
 */
export function ReportTable({ report, interval }: { report: Report; interval: SalesInterval }) {
  const { t } = useTranslation('reports')
  const format = useFormatters()
  const [page, setPage] = useState(0)
  const pages = Math.max(1, Math.ceil(report.rows.length / PAGE_SIZE))
  const rows = report.rows.slice(page * PAGE_SIZE, (page + 1) * PAGE_SIZE)
  const numeric = (type: string) => type === 'integer' || type === 'money' || type === 'percent'

  return (
    <div className="flex flex-col gap-3 rounded-2xl border bg-card p-2">
      <div className="overflow-x-auto" data-lenis-prevent>
        <Table data-testid="report-table">
          <TableHeader>
            <TableRow>
              {report.columns.map((column) => (
                <TableHead
                  key={column.key}
                  className={cn('whitespace-nowrap', numeric(column.type) && 'text-end')}
                >
                  {t(`columns.${column.key}`)}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.map((row, index) => (
              <TableRow key={page * PAGE_SIZE + index}>
                {report.columns.map((column) => (
                  <TableCell
                    key={column.key}
                    dir={column.type === 'text' ? 'auto' : undefined}
                    className={cn(
                      'whitespace-nowrap',
                      numeric(column.type) && 'text-end tabular-nums',
                    )}
                  >
                    {formatReportValue(row[column.key] ?? null, column.type, format, t, interval)}
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
      {pages > 1 && (
        <div className="flex items-center justify-between gap-3 px-2 pb-1 text-sm text-muted-foreground">
          <span>
            {t('pagination', {
              from: format.number(page * PAGE_SIZE + 1),
              to: format.number(Math.min((page + 1) * PAGE_SIZE, report.rows.length)),
              total: format.number(report.rows.length),
            })}
          </span>
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              size="icon-sm"
              disabled={page === 0}
              onClick={() => setPage(page - 1)}
              aria-label={t('previousPage')}
            >
              <ChevronLeftIcon className="rtl:rotate-180" aria-hidden="true" />
            </Button>
            <Button
              type="button"
              variant="outline"
              size="icon-sm"
              disabled={page >= pages - 1}
              onClick={() => setPage(page + 1)}
              aria-label={t('nextPage')}
            >
              <ChevronRightIcon className="rtl:rotate-180" aria-hidden="true" />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
