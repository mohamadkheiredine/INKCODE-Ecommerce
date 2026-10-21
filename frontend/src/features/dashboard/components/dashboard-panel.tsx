import { ChartNoAxesCombinedIcon, PlusIcon } from 'lucide-react'
import { lazy, Suspense, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { EmptyState } from '@/components/feedback/empty-state'
import { ErrorState } from '@/components/feedback/error-state'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useCan } from '@/features/auth/hooks/use-can'
import { useFormatters } from '@/lib/format'
import { useEntranceAnimation } from '@/lib/motion'
import { useDashboard } from '../hooks/use-dashboard'
import { useDashboardRange } from '../hooks/use-dashboard-range'
import type { Dashboard } from '../types'
import { AlertsCard, RecentOrdersCard, TopProductsCard } from './dashboard-lists'
import { DateRangePicker } from './date-range-picker'
import { KpiGrid } from './kpi-grid'

// Recharts is heavy: the charts load on their own, so the KPIs and lists show first.
const RevenueChart = lazy(async () => ({
  default: (await import('./dashboard-charts')).RevenueChart,
}))
const StatusDonut = lazy(async () => ({
  default: (await import('./dashboard-charts')).StatusDonut,
}))

/**
 * The executive dashboard: KPIs, Revenue chart, Order-status donut, top Products, recent
 * Orders and alerts for the date range in the URL, all computed by the API from Counted
 * orders.
 */
export function DashboardPanel() {
  const { t } = useTranslation('dashboard')
  const format = useFormatters()
  const { preset, range, setPreset, setCustomRange } = useDashboardRange()
  const dashboard = useDashboard(range)
  const day = (date: string) => format.date(`${date}T00:00:00`)

  return (
    <section className="flex flex-col gap-6" aria-labelledby="dashboard-title">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div className="flex flex-col gap-1">
          <h2 id="dashboard-title" className="text-xl font-semibold tracking-tight">
            {t('title')}
          </h2>
          <p className="text-sm text-muted-foreground" data-testid="dashboard-range">
            {t('rangeSummary', { from: day(range.from), to: day(range.to) })}
          </p>
        </div>
        <DateRangePicker
          preset={preset}
          range={range}
          onPresetChange={setPreset}
          onCustomRangeChange={setCustomRange}
        />
      </div>

      {dashboard.isPending ? (
        <DashboardSkeleton />
      ) : dashboard.isError ? (
        <ErrorState
          onRetry={() => void dashboard.refetch()}
          retrying={dashboard.isFetching}
          className="rounded-2xl border bg-card"
        />
      ) : (
        <DashboardContent data={dashboard.data} />
      )}
    </section>
  )
}

function DashboardContent({ data }: { data: Dashboard }) {
  const container = useRef<HTMLDivElement>(null)
  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.04, duration: 0.4 }])

  return (
    <div ref={container} className="flex flex-col gap-6">
      {data.kpis.orders.value === 0 && <NoOrdersYet />}
      <KpiGrid kpis={data.kpis} />
      <div className="grid gap-4 lg:grid-cols-3">
        <Suspense fallback={<Skeleton className="h-80 rounded-2xl lg:col-span-2" />}>
          <RevenueChart points={data.revenue_by_day} />
        </Suspense>
        <Suspense fallback={<Skeleton className="h-80 rounded-2xl" />}>
          <StatusDonut breakdown={data.status_breakdown} />
        </Suspense>
      </div>
      <div className="grid gap-4 lg:grid-cols-3">
        <TopProductsCard products={data.top_products} />
        <RecentOrdersCard orders={data.recent_orders} />
        <AlertsCard alerts={data.alerts} />
      </div>
    </div>
  )
}

/** No Counted orders in the range: say so, and offer the next step. */
function NoOrdersYet() {
  const { t } = useTranslation('dashboard')
  const can = useCan()

  return (
    <EmptyState
      icon={ChartNoAxesCombinedIcon}
      title={t('empty.title')}
      description={t('empty.description')}
      className="js-reveal rounded-2xl"
      action={
        can('orders.create') ? (
          <Button asChild className="gap-2">
            <Link to="/orders/new">
              <PlusIcon className="size-4" aria-hidden="true" />
              {t('empty.createOrder')}
            </Link>
          </Button>
        ) : (
          <Button asChild variant="outline">
            <Link to="/orders">{t('empty.viewOrders')}</Link>
          </Button>
        )
      }
    />
  )
}

function DashboardSkeleton() {
  return (
    <div className="flex flex-col gap-6" data-testid="dashboard-skeleton" aria-busy="true">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {Array.from({ length: 9 }, (_, index) => (
          <Skeleton key={index} className="h-32 rounded-2xl" />
        ))}
      </div>
      <div className="grid gap-4 lg:grid-cols-3">
        <Skeleton className="h-80 rounded-2xl lg:col-span-2" />
        <Skeleton className="h-80 rounded-2xl" />
      </div>
    </div>
  )
}
