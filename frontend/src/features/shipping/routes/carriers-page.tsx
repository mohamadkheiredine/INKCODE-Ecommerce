import { PlusIcon, TruckIcon } from 'lucide-react'
import { useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
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
import { CarrierFormDialog } from '../components/carrier-form-dialog'
import { useCarriers, useDeleteCarrier } from '../hooks/use-carriers'
import type { Carrier } from '../types'

/** What the Carriers list accepts in its URL (mirrors ListCarriersRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'name', direction: 'asc' },
  sorts: ['name', 'created_at'],
  filters: { tracking: ['with', 'without'] },
}

type Editing = { carrier: Carrier | null } | null

/** Carriers: search, filter by tracking link, sort, page; create, edit, delete. */
export function CarriersPage() {
  const { t } = useTranslation('shipping')
  const canManage = useCan()('shipping.manage')
  const list = useListQuery(LIST_CONFIG)
  const carriers = useCarriers(list.query)
  const deleteCarrier = useDeleteCarrier()
  const { date } = useFormatters()
  const [editing, setEditing] = useState<Editing>(null)
  const [deleting, setDeleting] = useState<Carrier | null>(null)
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<Carrier>[]>(
    () => [
      {
        id: 'name',
        header: t('fields.name'),
        meta: { sortField: 'name' },
        cell: ({ row }) => (
          <span data-name dir="auto" className="font-medium text-foreground">
            {row.original.name}
          </span>
        ),
      },
      {
        id: 'tracking',
        header: t('fields.trackingLink'),
        meta: { className: 'max-w-80' },
        cell: ({ row }) =>
          row.original.tracking_url_template ? (
            <span
              dir="ltr"
              className="block truncate text-start font-mono text-xs text-muted-foreground"
              title={row.original.tracking_url_template}
            >
              {row.original.tracking_url_template}
            </span>
          ) : (
            <span className="text-muted-foreground">{t('carriers.noTracking')}</span>
          ),
      },
      {
        id: 'created_at',
        header: t('fields.created'),
        meta: { sortField: 'created_at', className: 'hidden md:table-cell' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap text-muted-foreground">
            {date(row.original.created_at)}
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
                  name={row.original.name}
                  onEdit={() => setEditing({ carrier: row.original })}
                  onDelete={() => setDeleting(row.original)}
                />
              ),
            } satisfies DataTableColumn<Carrier>,
          ]
        : []),
    ],
    [t, canManage, date],
  )

  const confirmDelete = () => {
    if (!deleting) return
    deleteCarrier.mutate(deleting.id, {
      onSuccess: () => toast.success(i18n.t('shipping:carriers.deleted')),
      onError: (error) => toast.error(apiErrorMessage(error)),
      onSettled: () => setDeleting(null),
    })
  }

  const newButton = (
    <Button type="button" className="gap-2" onClick={() => setEditing({ carrier: null })}>
      <PlusIcon className="size-4" aria-hidden="true" />
      {t('carriers.new')}
    </Button>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.carriers')}
        description={t('carriers.description')}
        actions={canManage && newButton}
      />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('carriers.search')} />
          <DataTableFilter
            list={list}
            name="tracking"
            label={t('carriers.trackingFilter')}
            allLabel={t('carriers.allTracking')}
            options={[
              { value: 'with', label: t('carriers.withTracking') },
              { value: 'without', label: t('carriers.withoutTracking') },
            ]}
          />
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={carriers.data}
          isPending={carriers.isPending}
          isError={carriers.isError}
          isFetching={carriers.isFetching}
          onRetry={() => void carriers.refetch()}
          list={list}
          getRowId={(carrier) => String(carrier.id)}
          label={t('common:pages.carriers')}
          loadingLabel={t('carriers.loading')}
          empty={{
            icon: TruckIcon,
            title: t('carriers.emptyTitle'),
            description: canManage
              ? t('carriers.emptyDescription')
              : t('carriers.emptyDescriptionReadOnly'),
            action: canManage ? newButton : undefined,
          }}
        />
      </div>

      <CarrierFormDialog
        open={editing !== null}
        carrier={editing?.carrier ?? null}
        onOpenChange={(open) => !open && setEditing(null)}
      />
      <ConfirmDeleteDialog
        name={deleting?.name ?? null}
        description={t('carriers.deleteDescription')}
        pending={deleteCarrier.isPending}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
