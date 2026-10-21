import { PlusIcon, TicketPercentIcon } from 'lucide-react'
import { useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { toast } from 'sonner'
import type { DataTableColumn } from '@/components/data-table/columns'
import { DataTable } from '@/components/data-table/data-table'
import {
  DataTableFilter,
  DataTableSearch,
  DataTableToolbar,
} from '@/components/data-table/data-table-toolbar'
import { RowActions } from '@/components/data-table/row-actions'
import { ConfirmDeleteDialog } from '@/components/feedback/confirm-delete-dialog'
import { PageHeader } from '@/components/layout/page-header'
import { Button } from '@/components/ui/button'
import { useCan } from '@/features/auth/hooks/use-can'
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import type { ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { CouponStateBadge } from '../components/coupon-badges'
import { useCouponDiscount } from '../hooks/use-coupon-discount'
import { CouponFormDialog } from '../components/coupon-form-dialog'
import { useCoupons, useDeleteCoupon } from '../hooks/use-coupons'
import { couponsApi } from '../api/coupons-api'
import { COUPON_STATES, type Coupon } from '../types'

/** What the Coupons list accepts in its URL (mirrors ListCouponsRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'created_at', direction: 'desc' },
  sorts: ['code', 'starts_on', 'ends_on', 'times_used', 'created_at'],
  filters: { type: ['percentage', 'fixed'], state: COUPON_STATES },
}

type Editing = { coupon: Coupon | null } | null

/** Coupons: search, filter by type and state, sort, page; create, edit, delete. */
export function CouponsPage() {
  const { t } = useTranslation('coupons')
  const canManage = useCan()('coupons.manage')
  const list = useListQuery(LIST_CONFIG)
  const coupons = useCoupons(list.query)
  const deleteCoupon = useDeleteCoupon()
  const discount = useCouponDiscount()
  const { date, money, number } = useFormatters()
  const [editing, setEditing] = useState<Editing>(null)
  const [deleting, setDeleting] = useState<Coupon | null>(null)
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<Coupon>[]>(
    () => [
      {
        id: 'code',
        header: t('fields.code'),
        meta: { sortField: 'code' },
        cell: ({ row }) => (
          <Link
            to={`/coupons/${row.original.id}`}
            dir="ltr"
            className="font-mono font-medium text-foreground hover:underline"
          >
            {row.original.code}
          </Link>
        ),
      },
      {
        id: 'discount',
        header: t('fields.discount'),
        cell: ({ row }) => (
          <span className="tabular-nums">
            {discount(row.original)}
            {Number(row.original.minimum_order_amount) > 0 && (
              <span className="block text-xs text-muted-foreground">
                {t('minimumShort', { amount: money(row.original.minimum_order_amount) })}
              </span>
            )}
          </span>
        ),
      },
      {
        id: 'state',
        header: t('fields.state'),
        cell: ({ row }) => <CouponStateBadge state={row.original.state} />,
      },
      {
        id: 'starts_on',
        header: t('fields.validity'),
        meta: { sortField: 'starts_on', className: 'hidden md:table-cell' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap text-muted-foreground">
            {date(row.original.starts_on)} –{' '}
            {row.original.ends_on ? date(row.original.ends_on) : t('noEnd')}
          </span>
        ),
      },
      {
        id: 'times_used',
        header: t('fields.used'),
        meta: { sortField: 'times_used', className: 'text-end' },
        cell: ({ row }) => (
          <span className="tabular-nums">
            {row.original.usage_limit
              ? t('usedOf', {
                  used: number(row.original.times_used ?? 0),
                  limit: number(row.original.usage_limit),
                })
              : number(row.original.times_used ?? 0)}
          </span>
        ),
      },
      ...(canManage
        ? [
            {
              id: 'actions',
              header: () => <span className="sr-only">{t('common:dataTable.actions')}</span>,
              meta: { className: 'w-12 text-end' },
              cell: ({ row }) => (
                <RowActions
                  name={row.original.code}
                  onEdit={() =>
                    // The form needs the eligible items, which only one Coupon's answer has.
                    void couponsApi
                      .get(row.original.id)
                      .then((coupon) => setEditing({ coupon }))
                      .catch((error: unknown) => toast.error(apiErrorMessage(error)))
                  }
                  onDelete={() => setDeleting(row.original)}
                />
              ),
            } satisfies DataTableColumn<Coupon>,
          ]
        : []),
    ],
    [t, canManage, date, money, number, discount],
  )

  const confirmDelete = () => {
    if (!deleting) return
    deleteCoupon.mutate(deleting.id, {
      onSuccess: () => toast.success(i18n.t('coupons:deleted', { code: deleting.code })),
      onError: (error) => toast.error(apiErrorMessage(error)),
      onSettled: () => setDeleting(null),
    })
  }

  const newButton = (
    <Button type="button" className="gap-2" onClick={() => setEditing({ coupon: null })}>
      <PlusIcon className="size-4" aria-hidden="true" />
      {t('new')}
    </Button>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.coupons')}
        description={t('description')}
        actions={canManage && newButton}
      />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('search')} />
          <DataTableFilter
            list={list}
            name="state"
            label={t('fields.state')}
            allLabel={t('allStates')}
            options={COUPON_STATES.map((state) => ({ value: state, label: t(`states.${state}`) }))}
          />
          <DataTableFilter
            list={list}
            name="type"
            label={t('fields.type')}
            allLabel={t('allTypes')}
            options={[
              { value: 'percentage', label: t('types.percentage') },
              { value: 'fixed', label: t('types.fixed') },
            ]}
          />
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={coupons.data}
          isPending={coupons.isPending}
          isError={coupons.isError}
          isFetching={coupons.isFetching}
          onRetry={() => void coupons.refetch()}
          list={list}
          getRowId={(coupon) => String(coupon.id)}
          label={t('common:pages.coupons')}
          loadingLabel={t('loading')}
          empty={{
            icon: TicketPercentIcon,
            title: t('emptyTitle'),
            description: canManage ? t('emptyDescription') : t('emptyDescriptionReadOnly'),
            action: canManage ? newButton : undefined,
          }}
        />
      </div>

      <CouponFormDialog
        open={editing !== null}
        coupon={editing?.coupon ?? null}
        onOpenChange={(open) => !open && setEditing(null)}
      />
      <ConfirmDeleteDialog
        name={deleting?.code ?? null}
        description={t('deleteDescription')}
        pending={deleteCoupon.isPending}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
