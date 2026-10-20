import { PlusIcon, ShoppingCartIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { ErrorState } from '@/components/feedback/error-state'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useCan } from '@/features/auth/hooks/use-can'
import { useFormatters } from '@/lib/format'
import { useOrders } from '../hooks/use-orders'
import { OrderStatusBadge } from './order-status-badge'

const RECENT = 5

/** A Customer's latest Orders on their profile, with a link to all of them. */
export function CustomerOrdersCard({ customerId }: { customerId: number }) {
  const { t } = useTranslation('orders')
  const canCreate = useCan()('orders.create')
  const { money, date } = useFormatters()
  const orders = useOrders({
    search: '',
    filters: { customer: String(customerId) },
    sort: { field: 'placed_at', direction: 'desc' },
    page: 1,
    perPage: RECENT,
  })
  const total = orders.data?.meta.total ?? 0

  return (
    <Card className="js-reveal rounded-2xl">
      <CardHeader className="flex flex-row items-center justify-between gap-3">
        <CardTitle className="text-base">{t('customerHistory.title')}</CardTitle>
        {total > RECENT && (
          <Button asChild variant="link" size="sm" className="h-auto p-0">
            <Link to={`/orders?filter[customer]=${customerId}`}>
              {t('customerHistory.viewAll', { count: total })}
            </Link>
          </Button>
        )}
      </CardHeader>
      <CardContent>
        {orders.isPending && (
          <div className="flex flex-col gap-2" aria-label={t('loading')}>
            {Array.from({ length: 3 }, (_, index) => (
              <Skeleton key={index} className="h-12 rounded-lg" />
            ))}
          </div>
        )}
        {orders.isError && (
          <ErrorState onRetry={() => void orders.refetch()} retrying={orders.isFetching} />
        )}
        {orders.data && orders.data.data.length === 0 && (
          <div className="flex flex-col items-center gap-3 py-6 text-center">
            <ShoppingCartIcon className="size-8 text-muted-foreground" aria-hidden="true" />
            <p className="text-sm text-muted-foreground">{t('customerHistory.empty')}</p>
            {canCreate && (
              <Button asChild size="sm" variant="outline" className="gap-2">
                <Link to="/orders/new">
                  <PlusIcon className="size-4" aria-hidden="true" />
                  {t('new')}
                </Link>
              </Button>
            )}
          </div>
        )}
        {orders.data && orders.data.data.length > 0 && (
          <ul className="flex flex-col divide-y">
            {orders.data.data.map((order) => (
              <li key={order.id} className="flex items-center justify-between gap-3 py-3">
                <div className="flex min-w-0 flex-col">
                  <Link
                    to={`/orders/${order.id}`}
                    dir="ltr"
                    className="text-start font-mono text-sm font-medium hover:text-primary hover:underline"
                  >
                    {order.number}
                  </Link>
                  <span className="text-xs text-muted-foreground">{date(order.placed_at)}</span>
                </div>
                <div className="flex shrink-0 items-center gap-3">
                  <OrderStatusBadge status={order.status} />
                  <span className="font-medium tabular-nums">{money(order.total)}</span>
                </div>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}
