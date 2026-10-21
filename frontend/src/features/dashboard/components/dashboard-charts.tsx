import { useTranslation } from 'react-i18next'
import { Area, AreaChart, CartesianGrid, Cell, Label, Pie, PieChart, XAxis, YAxis } from 'recharts'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  type ChartConfig,
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart'
import { useFormatters } from '@/lib/format'
import { usePrefersReducedMotion } from '@/lib/motion'
import type { Dashboard } from '../types'

/** Daily Revenue for the range, with the previous period's same day beside it. */
export function RevenueChart({ points }: { points: Dashboard['revenue_by_day'] }) {
  const { t, i18n } = useTranslation('dashboard')
  const format = useFormatters()
  const animate = !usePrefersReducedMotion()
  const rtl = i18n.dir() === 'rtl'
  const config = {
    revenue: { label: t('chart.revenue'), color: 'var(--chart-1)' },
    previous: { label: t('chart.previous'), color: 'var(--chart-3)' },
  } satisfies ChartConfig
  const data = points.map((point) => ({
    date: point.date,
    revenue: Number(point.revenue),
    previous: Number(point.previous_revenue),
  }))
  const day = (date: string) => format.date(`${date}T00:00:00`, { day: 'numeric', month: 'short' })

  return (
    <Card className="js-reveal gap-4 rounded-2xl lg:col-span-2">
      <CardHeader>
        <CardTitle>{t('chart.title')}</CardTitle>
        <CardDescription>{t('chart.description')}</CardDescription>
      </CardHeader>
      <CardContent>
        <ChartContainer
          config={config}
          className="aspect-auto h-64 w-full"
          data-testid="revenue-chart"
        >
          <AreaChart data={data} margin={{ left: 4, right: 4 }}>
            <CartesianGrid vertical={false} />
            <XAxis
              dataKey="date"
              tickLine={false}
              axisLine={false}
              minTickGap={24}
              reversed={rtl}
              tickFormatter={day}
            />
            <YAxis
              tickLine={false}
              axisLine={false}
              width={72}
              orientation={rtl ? 'right' : 'left'}
              tickFormatter={(value: number) => format.number(value, { notation: 'compact' })}
            />
            <ChartTooltip
              content={
                <ChartTooltipContent
                  labelFormatter={(_, payload) => day(String(payload[0]?.payload?.date ?? ''))}
                  formatter={(value, name) => (
                    <span className="flex w-full justify-between gap-4">
                      <span className="text-muted-foreground">
                        {config[name as keyof typeof config]?.label}
                      </span>
                      <span className="font-medium tabular-nums">
                        {format.money(Number(value))}
                      </span>
                    </span>
                  )}
                />
              }
            />
            <Area
              dataKey="previous"
              type="monotone"
              stroke="var(--color-previous)"
              fill="var(--color-previous)"
              fillOpacity={0.08}
              strokeDasharray="4 4"
              isAnimationActive={animate}
            />
            <Area
              dataKey="revenue"
              type="monotone"
              stroke="var(--color-revenue)"
              fill="var(--color-revenue)"
              fillOpacity={0.2}
              strokeWidth={2}
              isAnimationActive={animate}
            />
            <ChartLegend content={<ChartLegendContent />} />
          </AreaChart>
        </ChartContainer>
      </CardContent>
    </Card>
  )
}

const STATUS_COLORS = [
  'var(--chart-1)',
  'var(--chart-2)',
  'var(--chart-4)',
  'var(--chart-3)',
  'var(--success)',
]

/** How the range's Counted orders split by Order status. */
export function StatusDonut({ breakdown }: { breakdown: Dashboard['status_breakdown'] }) {
  const { t } = useTranslation('dashboard')
  const format = useFormatters()
  const animate = !usePrefersReducedMotion()
  const total = breakdown.reduce((sum, slice) => sum + slice.count, 0)
  const config = Object.fromEntries(
    breakdown.map((slice, index) => [
      slice.status,
      {
        label: t(`orders:statuses.${slice.status}`),
        color: STATUS_COLORS[index % STATUS_COLORS.length],
      },
    ]),
  ) satisfies ChartConfig

  return (
    <Card className="js-reveal gap-4 rounded-2xl">
      <CardHeader>
        <CardTitle>{t('statuses.title')}</CardTitle>
        <CardDescription>{t('statuses.description')}</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <ChartContainer config={config} className="mx-auto aspect-square h-48">
          <PieChart>
            <ChartTooltip content={<ChartTooltipContent nameKey="status" hideLabel />} />
            <Pie
              data={breakdown.filter((slice) => slice.count > 0)}
              dataKey="count"
              nameKey="status"
              innerRadius={56}
              strokeWidth={4}
              isAnimationActive={animate}
            >
              {breakdown
                .filter((slice) => slice.count > 0)
                .map((slice) => (
                  <Cell key={slice.status} fill={`var(--color-${slice.status})`} />
                ))}
              <Label
                content={({ viewBox }) =>
                  viewBox && 'cx' in viewBox ? (
                    <text
                      x={viewBox.cx}
                      y={viewBox.cy}
                      textAnchor="middle"
                      dominantBaseline="middle"
                    >
                      <tspan
                        x={viewBox.cx}
                        y={viewBox.cy}
                        className="fill-foreground text-2xl font-semibold"
                      >
                        {format.number(total)}
                      </tspan>
                      <tspan
                        x={viewBox.cx}
                        y={(viewBox.cy ?? 0) + 22}
                        className="fill-muted-foreground text-xs"
                      >
                        {t('statuses.orders')}
                      </tspan>
                    </text>
                  ) : null
                }
              />
            </Pie>
          </PieChart>
        </ChartContainer>
        <ul className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm" data-testid="status-breakdown">
          {breakdown.map((slice) => (
            <li key={slice.status} className="flex items-center justify-between gap-2">
              <span className="flex min-w-0 items-center gap-2">
                <span
                  aria-hidden="true"
                  className="size-2.5 shrink-0 rounded-sm"
                  style={{ background: config[slice.status]?.color }}
                />
                <span className="truncate text-muted-foreground">
                  {t(`orders:statuses.${slice.status}`)}
                </span>
              </span>
              <span className="font-medium tabular-nums">{format.number(slice.count)}</span>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  )
}
