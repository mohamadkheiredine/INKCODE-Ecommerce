import {
  BanknoteIcon,
  BoxesIcon,
  CircleDollarSignIcon,
  PackageCheckIcon,
  ReceiptIcon,
  RotateCcwIcon,
  ShoppingCartIcon,
  TrendingDownIcon,
  TrendingUpIcon,
  UsersIcon,
  XCircleIcon,
  type LucideIcon,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useFormatters } from '@/lib/format'
import { cn } from '@/lib/utils'
import type { CountKpiName, Dashboard, Kpi, MoneyKpiName } from '../types'
import { CountUp } from './count-up'

type KpiDefinition =
  | { name: MoneyKpiName; kind: 'money'; icon: LucideIcon }
  | { name: CountKpiName; kind: 'count'; icon: LucideIcon; lowerIsBetter?: boolean }

/** The nine KPIs, in the order the spec lists them (no conversion rate: visits are not tracked). */
const KPIS: KpiDefinition[] = [
  { name: 'revenue', kind: 'money', icon: CircleDollarSignIcon },
  { name: 'net_sales', kind: 'money', icon: BanknoteIcon },
  { name: 'orders', kind: 'count', icon: ShoppingCartIcon },
  { name: 'pending_fulfillment', kind: 'count', icon: PackageCheckIcon },
  { name: 'average_order_value', kind: 'money', icon: ReceiptIcon },
  { name: 'customers', kind: 'count', icon: UsersIcon },
  { name: 'cancellations', kind: 'count', icon: XCircleIcon, lowerIsBetter: true },
  { name: 'returns', kind: 'count', icon: RotateCcwIcon, lowerIsBetter: true },
  { name: 'low_stock', kind: 'count', icon: BoxesIcon, lowerIsBetter: true },
]

export function KpiGrid({ kpis }: { kpis: Dashboard['kpis'] }) {
  const format = useFormatters()

  return (
    <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
      {KPIS.map((definition) =>
        definition.kind === 'money' ? (
          <KpiCard
            key={definition.name}
            name={definition.name}
            icon={definition.icon}
            kpi={kpis[definition.name]}
            value={Number(kpis[definition.name].value)}
            text={format.money(kpis[definition.name].value)}
            formatValue={(value) => format.money(value)}
          />
        ) : (
          <KpiCard
            key={definition.name}
            name={definition.name}
            icon={definition.icon}
            kpi={kpis[definition.name]}
            value={kpis[definition.name].value}
            text={format.number(kpis[definition.name].value)}
            formatValue={(value) => format.number(Math.round(value))}
            lowerIsBetter={definition.lowerIsBetter}
          />
        ),
      )}
    </section>
  )
}

type KpiCardProps = {
  name: MoneyKpiName | CountKpiName
  icon: LucideIcon
  kpi: Kpi<unknown>
  value: number
  text: string
  formatValue: (value: number) => string
  lowerIsBetter?: boolean
}

function KpiCard({ name, icon: Icon, kpi, value, text, formatValue, lowerIsBetter }: KpiCardProps) {
  const { t } = useTranslation('dashboard')

  return (
    <article
      className="js-reveal flex flex-col gap-3 rounded-2xl border bg-card p-5 shadow-xs"
      data-testid={`kpi-${name}`}
    >
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-sm font-medium text-muted-foreground">{t(`kpis.${name}`)}</h2>
        <span className="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
          <Icon className="size-4" aria-hidden="true" />
        </span>
      </div>
      <p className="text-2xl font-semibold tracking-tight" data-testid={`kpi-${name}-value`}>
        <CountUp value={value} text={text} format={formatValue} />
      </p>
      <Change kpi={kpi} lowerIsBetter={lowerIsBetter} />
    </article>
  )
}

/** The change against the previous period; a zero baseline reads as "new", a snapshot as "now". */
export function Change({
  kpi,
  lowerIsBetter = false,
}: {
  kpi: Kpi<unknown>
  lowerIsBetter?: boolean
}) {
  const { t } = useTranslation('dashboard')
  const format = useFormatters()

  if (kpi.previous === null) {
    return <p className="text-xs text-muted-foreground">{t('change.snapshot')}</p>
  }
  if (kpi.change === null) {
    return <p className="text-xs text-muted-foreground">{t('change.noBaseline')}</p>
  }

  const rising = kpi.change > 0
  const good = kpi.change === 0 ? null : rising !== lowerIsBetter
  const Trend = rising ? TrendingUpIcon : TrendingDownIcon

  return (
    <p className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
      <span
        className={cn(
          'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium',
          good === null && 'bg-muted text-muted-foreground',
          good === true && 'bg-success/15 text-foreground',
          good === false && 'bg-destructive/10 text-destructive',
        )}
      >
        {kpi.change !== 0 && <Trend className="size-3" aria-hidden="true" />}
        <span dir="ltr">
          {format.number(kpi.change / 100, {
            style: 'percent',
            maximumFractionDigits: 1,
            signDisplay: 'exceptZero',
          })}
        </span>
      </span>
      {t('change.vsPrevious')}
    </p>
  )
}
