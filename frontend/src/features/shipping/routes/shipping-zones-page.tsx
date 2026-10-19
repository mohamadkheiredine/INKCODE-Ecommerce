import { MapPinnedIcon, PlusIcon } from 'lucide-react'
import { useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { toast } from 'sonner'
import type { DataTableColumn } from '@/components/data-table/columns'
import { DataTable } from '@/components/data-table/data-table'
import { DataTableSearch, DataTableToolbar } from '@/components/data-table/data-table-toolbar'
import { RowActions } from '@/components/data-table/row-actions'
import { ConfirmDeleteDialog } from '@/components/feedback/confirm-delete-dialog'
import { CountrySelect } from '@/components/forms/country-select'
import { PageHeader } from '@/components/layout/page-header'
import { Button } from '@/components/ui/button'
import { useCan } from '@/features/auth/hooks/use-can'
import { useListQuery } from '@/hooks/use-list-query'
import { COUNTRY_CODES } from '@/lib/countries'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import type { ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { QuoteChecker } from '../components/quote-checker'
import { ZoneCoverage } from '../components/zone-coverage'
import { ZoneFormDialog } from '../components/zone-form-dialog'
import { useDeleteShippingZone, useShippingZones } from '../hooks/use-shipping-zones'
import type { ShippingZone } from '../types'

/** What the Shipping zones list accepts in its URL (mirrors ListShippingZonesRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'name', direction: 'asc' },
  sorts: ['name', 'created_at'],
  filters: { country: COUNTRY_CODES },
}

/** How many places a row shows before "+3 more". */
const PLACES_SHOWN = 4

type Editing = { zone: ShippingZone | null } | null

/**
 * Shipping zones: search, filter by country, sort, page; create, edit, delete; open a
 * zone for its Shipping methods; check which zone an address falls in.
 */
export function ShippingZonesPage() {
  const { t } = useTranslation('shipping')
  const can = useCan()
  const canManage = can('shipping.manage')
  const canQuote = canManage || can('orders.create')
  const list = useListQuery(LIST_CONFIG)
  const zones = useShippingZones(list.query)
  const deleteZone = useDeleteShippingZone()
  const navigate = useNavigate()
  const [editing, setEditing] = useState<Editing>(null)
  const [deleting, setDeleting] = useState<ShippingZone | null>(null)
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<ShippingZone>[]>(
    () => [
      {
        id: 'name',
        header: t('fields.name'),
        meta: { sortField: 'name' },
        cell: ({ row }) => (
          <Link
            to={`/shipping/zones/${row.original.id}`}
            dir="auto"
            data-name
            className="font-medium text-foreground underline-offset-4 hover:underline focus-visible:underline"
          >
            {row.original.name}
          </Link>
        ),
      },
      {
        id: 'coverage',
        header: t('fields.coverage'),
        meta: { className: 'min-w-56' },
        cell: ({ row }) => <ZoneCoverage locations={row.original.locations} limit={PLACES_SHOWN} />,
      },
      {
        id: 'methods',
        header: t('fields.methods'),
        meta: { className: 'hidden md:table-cell' },
        cell: ({ row }) => {
          const total = row.original.methods_count ?? 0
          return (
            <span className="whitespace-nowrap text-muted-foreground">
              {total === 0
                ? t('zones.noMethods')
                : t('zones.activeMethods', {
                    active: row.original.active_methods_count ?? 0,
                    total,
                  })}
            </span>
          )
        },
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
                  onEdit={() => setEditing({ zone: row.original })}
                  onDelete={() => setDeleting(row.original)}
                />
              ),
            } satisfies DataTableColumn<ShippingZone>,
          ]
        : []),
    ],
    [t, canManage],
  )

  const confirmDelete = () => {
    if (!deleting) return
    deleteZone.mutate(deleting.id, {
      onSuccess: () => toast.success(i18n.t('shipping:zones.deleted')),
      onError: (error) => toast.error(apiErrorMessage(error)),
      onSettled: () => setDeleting(null),
    })
  }

  const newButton = (
    <Button type="button" className="gap-2" onClick={() => setEditing({ zone: null })}>
      <PlusIcon className="size-4" aria-hidden="true" />
      {t('zones.new')}
    </Button>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.shippingZones')}
        description={t('zones.description')}
        actions={canManage && newButton}
      />
      {canQuote && <QuoteChecker />}
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('zones.search')} />
          <CountrySelect
            value={list.query.filters.country ?? ''}
            onChange={(event) => list.setFilter('country', event.target.value || null)}
            aria-label={t('zones.countryFilter')}
            placeholder={t('zones.allCountries')}
            className="sm:w-56"
          />
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={zones.data}
          isPending={zones.isPending}
          isError={zones.isError}
          isFetching={zones.isFetching}
          onRetry={() => void zones.refetch()}
          list={list}
          getRowId={(zone) => String(zone.id)}
          label={t('common:pages.shippingZones')}
          loadingLabel={t('zones.loading')}
          empty={{
            icon: MapPinnedIcon,
            title: t('zones.emptyTitle'),
            description: canManage
              ? t('zones.emptyDescription')
              : t('zones.emptyDescriptionReadOnly'),
            action: canManage ? newButton : undefined,
          }}
        />
      </div>

      <ZoneFormDialog
        open={editing !== null}
        zone={editing?.zone ?? null}
        onOpenChange={(open) => !open && setEditing(null)}
        onSaved={(zone) => {
          if (!editing?.zone) void navigate(`/shipping/zones/${zone.id}`)
        }}
      />
      <ConfirmDeleteDialog
        name={deleting?.name ?? null}
        description={t('zones.deleteDescription')}
        pending={deleteZone.isPending}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
