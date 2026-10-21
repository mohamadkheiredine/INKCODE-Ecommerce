import { DownloadIcon, FileChartColumnIcon } from 'lucide-react'
import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router'
import { EmptyState } from '@/components/feedback/empty-state'
import { ErrorState } from '@/components/feedback/error-state'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { PageHeader } from '@/components/layout/page-header'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import { useCan } from '@/features/auth/hooks/use-can'
import { DateRangePicker } from '@/features/dashboard/components/date-range-picker'
import { useDashboardRange } from '@/features/dashboard/hooks/use-dashboard-range'
import { useFormatters } from '@/lib/format'
import { useEntranceAnimation } from '@/lib/motion'
import { ReportSummary } from '../components/report-summary'
import { ReportTable } from '../components/report-table'
import { useExportReport, useReport } from '../hooks/use-report'
import {
  PRODUCT_RANKS,
  REPORT_NAMES,
  SALES_INTERVALS,
  type ExportFormat,
  type ProductRank,
  type ReportParams,
  type SalesInterval,
} from '../types'

function pick<T extends string>(options: readonly T[], value: string | null, fallback: T): T {
  return options.find((option) => option === value) ?? fallback
}

/**
 * The six reports: one at a time (`report` in the URL), for the date range in the URL,
 * with a summary against the previous period, the rows, and CSV / Excel downloads for
 * Roles with reports.export.
 */
export function ReportsPage() {
  const { t } = useTranslation('reports')
  const format = useFormatters()
  const can = useCan()
  const [params, setParams] = useSearchParams()
  const { preset, range, setPreset, setCustomRange } = useDashboardRange()
  const name = pick(REPORT_NAMES, params.get('report'), 'sales')
  const interval = pick(SALES_INTERVALS, params.get('interval'), 'day')
  const rank = pick(PRODUCT_RANKS, params.get('rank'), 'sales')
  const query: ReportParams = {
    ...range,
    ...(name === 'sales' && { interval }),
    ...(name === 'products' && { rank }),
  }
  const report = useReport(name, query)
  const exporter = useExportReport(name, query)
  const container = useRef<HTMLDivElement>(null)
  const day = (date: string) => format.date(`${date}T00:00:00`)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.05 }])

  const setParam = (key: string, value: string | null) =>
    setParams(
      (current) => {
        const next = new URLSearchParams(current)
        if (value === null) next.delete(key)
        else next.set(key, value)
        return next
      },
      { replace: true },
    )

  const download = (fileFormat: ExportFormat) => exporter.mutate(fileFormat)

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.reports')}
        description={t('description')}
        actions={
          can('reports.export') && (
            <div className="flex flex-wrap gap-2">
              {(['csv', 'xlsx'] as const).map((fileFormat) => (
                <Button
                  key={fileFormat}
                  type="button"
                  variant="outline"
                  disabled={exporter.isPending || !report.data}
                  onClick={() => download(fileFormat)}
                >
                  {exporter.isPending && exporter.variables === fileFormat ? (
                    <InlineSpinner />
                  ) : (
                    <DownloadIcon data-icon="inline-start" aria-hidden="true" />
                  )}
                  {t(`export.${fileFormat}`)}
                </Button>
              ))}
            </div>
          )
        }
      />

      <div className="js-reveal flex flex-col gap-4">
        <Tabs value={name} onValueChange={(value) => setParam('report', value)}>
          <TabsList className="h-auto flex-wrap justify-start" aria-label={t('choose')}>
            {REPORT_NAMES.map((item) => (
              <TabsTrigger key={item} value={item}>
                {t(`names.${item}`)}
              </TabsTrigger>
            ))}
          </TabsList>
        </Tabs>

        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div className="flex flex-col gap-3">
            <p className="text-sm text-muted-foreground" data-testid="report-range">
              {name === 'inventory'
                ? t('inventoryRange', { from: day(range.from), to: day(range.to) })
                : t('range', { from: day(range.from), to: day(range.to) })}
            </p>
            {name === 'sales' && (
              <OptionToggle
                label={t('interval.label')}
                value={interval}
                options={SALES_INTERVALS}
                text={(option: SalesInterval) => t(`interval.${option}`)}
                onChange={(option) => setParam('interval', option === 'day' ? null : option)}
              />
            )}
            {name === 'products' && (
              <OptionToggle
                label={t('rank.label')}
                value={rank}
                options={PRODUCT_RANKS}
                text={(option: ProductRank) => t(`rank.${option}`)}
                onChange={(option) => setParam('rank', option === 'sales' ? null : option)}
              />
            )}
          </div>
          <DateRangePicker
            preset={preset}
            range={range}
            onPresetChange={setPreset}
            onCustomRangeChange={setCustomRange}
          />
        </div>
      </div>

      {report.isPending ? (
        <ReportSkeleton />
      ) : report.isError ? (
        <ErrorState
          onRetry={() => void report.refetch()}
          retrying={report.isFetching}
          className="rounded-2xl border bg-card"
        />
      ) : (
        <div className="js-reveal flex flex-col gap-6" aria-busy={report.isFetching}>
          <ReportSummary figures={report.data.summary} />
          {report.data.rows.length === 0 ? (
            <EmptyState
              icon={FileChartColumnIcon}
              title={t('empty.title')}
              description={t('empty.description')}
              action={
                <Button type="button" variant="outline" onClick={() => setPreset('month')}>
                  {t('empty.action')}
                </Button>
              }
              className="rounded-2xl border bg-card"
            />
          ) : (
            <ReportTable
              key={`${name}-${range.from}-${range.to}-${interval}-${rank}`}
              report={report.data}
              interval={interval}
            />
          )}
        </div>
      )}
    </div>
  )
}

type OptionToggleProps<T extends string> = {
  label: string
  value: T
  options: readonly T[]
  text: (option: T) => string
  onChange: (option: T) => void
}

function OptionToggle<T extends string>({
  label,
  value,
  options,
  text,
  onChange,
}: OptionToggleProps<T>) {
  return (
    <ToggleGroup
      type="single"
      variant="outline"
      size="sm"
      spacing={0}
      value={value}
      aria-label={label}
      onValueChange={(next) => next && onChange(next as T)}
      className="bg-card"
    >
      {options.map((option) => (
        <ToggleGroupItem key={option} value={option} className="px-3">
          {text(option)}
        </ToggleGroupItem>
      ))}
    </ToggleGroup>
  )
}

function ReportSkeleton() {
  return (
    <div className="flex flex-col gap-6" data-testid="report-skeleton">
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {Array.from({ length: 4 }, (_, index) => (
          <Skeleton key={index} className="h-28 rounded-2xl" />
        ))}
      </div>
      <Skeleton className="h-96 rounded-2xl" />
    </div>
  )
}
