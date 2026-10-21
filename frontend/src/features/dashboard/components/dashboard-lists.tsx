import {
  BellRingIcon,
  BoxesIcon,
  ChevronRightIcon,
  ClipboardCheckIcon,
  RotateCcwIcon,
  type LucideIcon,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useCan } from '@/features/auth/hooks/use-can'
import { OrderStatusBadge } from '@/features/orders/components/order-status-badge'
import { useFormatters } from '@/lib/format'
import type { Permission } from '@/lib/permissions'
import type { Dashboard, DashboardAlertType } from '../types'

export function TopProductsCard({ products }: { products: Dashboard['top_products'] }) {
  const { t } = useTranslation('dashboard')
  const format = useFormatters()
  const best = Number(products[0]?.sales ?? 0)

  return (
    <Card className="js-reveal gap-4 rounded-2xl">
      <CardHeader>
        <CardTitle>{t('topProducts.title')}</CardTitle>
        <CardDescription>{t('topProducts.description')}</CardDescription>
      </CardHeader>
      <CardContent>
        {products.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('topProducts.empty')}</p>
        ) : (
          <ol className="flex flex-col gap-4" data-testid="top-products">
            {products.map((product) => (
              <li key={product.product_id} className="flex flex-col gap-1.5">
                <div className="flex items-baseline justify-between gap-3 text-sm">
                  <span className="truncate font-medium">{product.name}</span>
                  <span className="shrink-0 font-medium tabular-nums">
                    {format.money(product.sales)}
                  </span>
                </div>
                <div className="flex items-center gap-3">
                  <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                    <span
                      className="block h-full rounded-full bg-primary"
                      style={{ width: `${best > 0 ? (Number(product.sales) / best) * 100 : 0}%` }}
                    />
                  </span>
                  <span className="shrink-0 text-xs text-muted-foreground">
                    {t('topProducts.units', { count: product.units })}
                  </span>
                </div>
              </li>
            ))}
          </ol>
        )}
      </CardContent>
    </Card>
  )
}

export function RecentOrdersCard({ orders }: { orders: Dashboard['recent_orders'] }) {
  const { t } = useTranslation('dashboard')
  const format = useFormatters()

  return (
    <Card className="js-reveal gap-4 rounded-2xl">
      <CardHeader>
        <CardTitle>{t('recentOrders.title')}</CardTitle>
        <CardDescription>{t('recentOrders.description')}</CardDescription>
      </CardHeader>
      <CardContent>
        {orders.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('recentOrders.empty')}</p>
        ) : (
          <ul className="-mx-2 flex flex-col" data-testid="recent-orders">
            {orders.map((order) => (
              <li key={order.id}>
                <Link
                  to={`/orders/${order.id}`}
                  className="flex items-center justify-between gap-3 rounded-lg px-2 py-2.5 transition-colors hover:bg-muted/60"
                >
                  <span className="flex min-w-0 flex-col">
                    <span className="font-medium" dir="ltr">
                      {order.number}
                    </span>
                    <span className="truncate text-xs text-muted-foreground">
                      {order.customer.name} · {format.relativeTime(order.placed_at)}
                    </span>
                  </span>
                  <span className="flex shrink-0 flex-col items-end gap-1">
                    <span className="text-sm font-medium tabular-nums">
                      {format.money(order.total)}
                    </span>
                    <OrderStatusBadge status={order.status} />
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}

const ALERTS: Record<DashboardAlertType, { icon: LucideIcon; to: string; permission: Permission }> =
  {
    low_stock: {
      icon: BoxesIcon,
      to: '/inventory/stock?filter[low_stock]=1',
      permission: 'inventory.view',
    },
    orders_awaiting_confirmation: {
      icon: ClipboardCheckIcon,
      to: '/orders?filter[status]=pending',
      permission: 'orders.view',
    },
    returns_awaiting_decision: {
      icon: RotateCcwIcon,
      to: '/returns?filter[status]=requested',
      permission: 'returns.view',
    },
  }

/** What needs someone's attention now, each linking to its list when the Role may open it. */
export function AlertsCard({ alerts }: { alerts: Dashboard['alerts'] }) {
  const { t } = useTranslation('dashboard')
  const can = useCan()

  return (
    <Card className="js-reveal gap-4 rounded-2xl">
      <CardHeader>
        <CardTitle>{t('alerts.title')}</CardTitle>
        <CardDescription>{t('alerts.description')}</CardDescription>
      </CardHeader>
      <CardContent>
        {alerts.length === 0 ? (
          <p className="flex items-center gap-2 text-sm text-muted-foreground">
            <BellRingIcon className="size-4" aria-hidden="true" />
            {t('alerts.empty')}
          </p>
        ) : (
          <ul className="flex flex-col gap-2" data-testid="alerts">
            {alerts.map((alert) => {
              const { icon: Icon, to, permission } = ALERTS[alert.type]
              const content = (
                <>
                  <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-warning/15 text-foreground">
                    <Icon className="size-4" aria-hidden="true" />
                  </span>
                  <span className="flex-1 text-sm">
                    {t(`alerts.${alert.type}`, { count: alert.count })}
                  </span>
                </>
              )
              return (
                <li key={alert.type}>
                  {can(permission) ? (
                    <Link
                      to={to}
                      className="flex items-center gap-3 rounded-lg border bg-card p-2.5 transition-colors hover:bg-muted/60"
                    >
                      {content}
                      <ChevronRightIcon
                        className="size-4 text-muted-foreground rtl:rotate-180"
                        aria-hidden="true"
                      />
                    </Link>
                  ) : (
                    <div className="flex items-center gap-3 rounded-lg border bg-card p-2.5">
                      {content}
                    </div>
                  )}
                </li>
              )
            })}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}
