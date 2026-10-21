import { ArrowLeftIcon, PencilIcon, TicketPercentIcon } from 'lucide-react'
import { type ReactNode, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import type { DataTableColumn } from '@/components/data-table/columns'
import { DataTable } from '@/components/data-table/data-table'
import { DataTableFilter, DataTableToolbar } from '@/components/data-table/data-table-toolbar'
import { EmptyState } from '@/components/feedback/empty-state'
import { ErrorState } from '@/components/feedback/error-state'
import { ContentSkeleton } from '@/components/feedback/page-skeleton'
import { PageHeader } from '@/components/layout/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { useCan } from '@/features/auth/hooks/use-can'
import { OrderStatusBadge } from '@/features/orders/components/order-status-badge'
import { useListQuery } from '@/hooks/use-list-query'
import { isApiError } from '@/lib/api/api-error'
import { useFormatters } from '@/lib/format'
import type { ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { CouponStateBadge } from '../components/coupon-badges'
import { useCouponDiscount } from '../hooks/use-coupon-discount'
import { CouponFormDialog } from '../components/coupon-form-dialog'
import { useCoupon, useCouponRedemptions } from '../hooks/use-coupons'
import type { Coupon, CouponRedemption } from '../types'

/** What the usage list accepts in its URL (mirrors ListCouponRedemptionsRequest). */
const USAGE_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'redeemed_at', direction: 'desc' },
  sorts: ['redeemed_at', 'discount_amount'],
  filters: { state: ['counted', 'released'] },
}

/** One Coupon: its terms and its usage (which Orders and Customers used it). */
export function CouponPage() {
  const { t } = useTranslation('coupons')
  const couponId = Number(useParams().couponId)
  const coupon = useCoupon(couponId)

  if (coupon.isPending) return <ContentSkeleton />
  if (coupon.isError) {
    if (isApiError(coupon.error) && coupon.error.status === 404) {
      return (
        <EmptyState
          icon={TicketPercentIcon}
          title={t('common:pages.coupon')}
          description={t('notFound')}
          action={<BackLink />}
        />
      )
    }
    return <ErrorState onRetry={() => void coupon.refetch()} retrying={coupon.isFetching} />
  }
  return <CouponDetail coupon={coupon.data} />
}

function BackLink() {
  const { t } = useTranslation('coupons')
  return (
    <Button asChild variant="outline" className="gap-2">
      <Link to="/coupons">
        <ArrowLeftIcon className="size-4 rtl:rotate-180" aria-hidden="true" />
        {t('back')}
      </Link>
    </Button>
  )
}

function CouponDetail({ coupon }: { coupon: Coupon }) {
  const { t } = useTranslation('coupons')
  const canManage = useCan()('coupons.manage')
  const discount = useCouponDiscount()
  const { date, money, number } = useFormatters()
  const [editing, setEditing] = useState(false)
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const terms: [string, ReactNode][] = [
    [t('fields.discount'), discount(coupon)],
    [
      t('fields.validity'),
      `${date(coupon.starts_on)} – ${coupon.ends_on ? date(coupon.ends_on) : t('noEnd')}`,
    ],
    [
      t('fields.usageLimit'),
      coupon.usage_limit
        ? t('usedOf', { used: number(coupon.times_used ?? 0), limit: number(coupon.usage_limit) })
        : t('usedUnlimited', { used: number(coupon.times_used ?? 0) }),
    ],
    [
      t('fields.perCustomerLimit'),
      coupon.per_customer_limit ? number(coupon.per_customer_limit) : t('unlimited'),
    ],
    [
      t('fields.minimumOrderAmount'),
      Number(coupon.minimum_order_amount) > 0 ? money(coupon.minimum_order_amount) : t('none'),
    ],
  ]
  const eligible = [...(coupon.categories ?? []), ...(coupon.products ?? [])]

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={coupon.code}
        description={t('detailDescription')}
        actions={
          canManage && (
            <Button
              type="button"
              variant="outline"
              className="gap-2"
              onClick={() => setEditing(true)}
            >
              <PencilIcon className="size-4" aria-hidden="true" />
              {t('common:actions.edit')}
            </Button>
          )
        }
      />
      <div className="grid items-start gap-6 lg:grid-cols-3">
        <Card className="js-reveal rounded-2xl">
          <CardHeader className="flex flex-row items-center justify-between gap-3">
            <CardTitle className="text-base">{t('terms')}</CardTitle>
            <CouponStateBadge state={coupon.state} />
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <dl className="flex flex-col gap-2 text-sm">
              {terms.map(([label, value]) => (
                <div key={label} className="flex items-start justify-between gap-4">
                  <dt className="text-muted-foreground">{label}</dt>
                  <dd className="text-end tabular-nums">{value}</dd>
                </div>
              ))}
            </dl>
            <div className="flex flex-col gap-2 border-t pt-3">
              <span className="text-sm text-muted-foreground">{t('eligibleTitle')}</span>
              {eligible.length === 0 ? (
                <span className="text-sm">{t('allItems')}</span>
              ) : (
                <ul className="flex flex-wrap gap-1.5">
                  {coupon.categories?.map((category) => (
                    <li key={`c${category.id}`}>
                      <Badge variant="secondary" dir="auto">
                        {category.name}
                      </Badge>
                    </li>
                  ))}
                  {coupon.products?.map((product) => (
                    <li key={`p${product.id}`}>
                      <Badge variant="outline" dir="auto">
                        {product.name}
                      </Badge>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </CardContent>
        </Card>
        <div className="js-reveal lg:col-span-2">
          <CouponUsage coupon={coupon} />
        </div>
      </div>
      <CouponFormDialog
        open={editing}
        coupon={coupon}
        onOpenChange={(open) => !open && setEditing(false)}
      />
    </div>
  )
}

function CouponUsage({ coupon }: { coupon: Coupon }) {
  const { t } = useTranslation('coupons')
  const canCreateOrders = useCan()('orders.create')
  const list = useListQuery(USAGE_CONFIG)
  const redemptions = useCouponRedemptions(coupon.id, list.query)
  const { dateTime, money } = useFormatters()

  const columns = useMemo<DataTableColumn<CouponRedemption>[]>(
    () => [
      {
        id: 'order',
        header: t('usage.order'),
        cell: ({ row }) => (
          <Link
            to={`/orders/${row.original.order.id}`}
            dir="ltr"
            className="font-mono text-sm font-medium hover:underline"
          >
            {row.original.order.number}
          </Link>
        ),
      },
      {
        id: 'customer',
        header: t('usage.customer'),
        cell: ({ row }) => (
          <Link
            to={`/customers/${row.original.customer.id}`}
            dir="auto"
            className="hover:underline"
          >
            {row.original.customer.name}
          </Link>
        ),
      },
      {
        id: 'status',
        header: t('usage.status'),
        meta: { className: 'hidden sm:table-cell' },
        cell: ({ row }) => <OrderStatusBadge status={row.original.order.status} />,
      },
      {
        id: 'discount_amount',
        header: t('usage.discount'),
        meta: { sortField: 'discount_amount', className: 'text-end' },
        cell: ({ row }) => (
          <span className="tabular-nums">{money(row.original.discount_amount)}</span>
        ),
      },
      {
        id: 'redeemed_at',
        header: t('usage.redeemedAt'),
        meta: { sortField: 'redeemed_at', className: 'hidden md:table-cell' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap text-muted-foreground">
            {dateTime(row.original.redeemed_at)}
            {row.original.released_at && (
              <span className="block text-xs">
                {t('usage.releasedAt', { date: dateTime(row.original.released_at) })}
              </span>
            )}
          </span>
        ),
      },
    ],
    [t, dateTime, money],
  )

  return (
    <Card className="rounded-2xl">
      <CardHeader>
        <CardTitle className="text-base">{t('usage.title')}</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableFilter
            list={list}
            name="state"
            label={t('usage.stateFilter')}
            allLabel={t('usage.allUses')}
            options={[
              { value: 'counted', label: t('usage.counted') },
              { value: 'released', label: t('usage.released') },
            ]}
          />
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={redemptions.data}
          isPending={redemptions.isPending}
          isError={redemptions.isError}
          isFetching={redemptions.isFetching}
          onRetry={() => void redemptions.refetch()}
          list={list}
          getRowId={(redemption) => String(redemption.id)}
          label={t('usage.title')}
          loadingLabel={t('usage.loading')}
          empty={{
            icon: TicketPercentIcon,
            title: t('usage.emptyTitle'),
            description: t('usage.emptyDescription'),
            action: canCreateOrders ? (
              <Button asChild variant="outline">
                <Link to="/orders/new">{t('usage.newOrder')}</Link>
              </Button>
            ) : undefined,
          }}
        />
      </CardContent>
    </Card>
  )
}
