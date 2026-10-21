import { useTranslation } from 'react-i18next'
import { Card, CardContent } from '@/components/ui/card'
import { Change } from '@/features/dashboard/components/kpi-grid'
import { useFormatters } from '@/lib/format'
import { formatReportValue } from '../format-value'
import type { ReportFigure } from '../types'

/** Figures where a rise is bad news, so their change reads red when it goes up. */
const LOWER_IS_BETTER = new Set(['returns', 'refunded', 'return_rate', 'low_stock'])

/** The report's headline figures, each against the previous period. */
export function ReportSummary({ figures }: { figures: ReportFigure[] }) {
  const { t } = useTranslation('reports')
  const format = useFormatters()

  return (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-testid="report-summary">
      {figures.map((figure) => (
        <Card key={figure.key} className="rounded-2xl" data-testid={`figure-${figure.key}`}>
          <CardContent className="flex flex-col gap-2">
            <p className="text-sm text-muted-foreground">{t(`summary.${figure.key}`)}</p>
            <p
              className="text-2xl font-semibold tracking-tight tabular-nums"
              data-testid={`figure-${figure.key}-value`}
            >
              {formatReportValue(figure.value, figure.type, format, t)}
            </p>
            <Change kpi={figure} lowerIsBetter={LOWER_IS_BETTER.has(figure.key)} />
          </CardContent>
        </Card>
      ))}
    </div>
  )
}
