import { PencilIcon, PlusIcon, WarehouseIcon } from 'lucide-react'
import { useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { DataTableColumn } from '@/components/data-table/columns'
import { DataTable } from '@/components/data-table/data-table'
import {
  DataTableFilter,
  DataTableSearch,
  DataTableToolbar,
} from '@/components/data-table/data-table-toolbar'
import { PageHeader } from '@/components/layout/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useCan } from '@/features/auth/hooks/use-can'
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import type { ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { WarehouseFormDialog } from '../components/warehouse-form-dialog'
import { useWarehouses } from '../hooks/use-inventory'
import type { Warehouse } from '../types'

/** What the Warehouses list accepts in its URL (mirrors ListWarehousesRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'name', direction: 'asc' },
  sorts: ['name', 'code', 'created_at'],
  filters: { status: ['active', 'inactive'] },
}

type Editing = { warehouse: Warehouse | null } | null

/** Warehouses: search, filter by status, sort; create, edit, deactivate. */
export function WarehousesPage() {
  const { t } = useTranslation('inventory')
  const canManage = useCan()('warehouses.manage')
  const list = useListQuery(LIST_CONFIG)
  const warehouses = useWarehouses(list.query)
  const { number } = useFormatters()
  const [editing, setEditing] = useState<Editing>(null)
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<Warehouse>[]>(
    () => [
      {
        id: 'name',
        header: t('fields.name'),
        meta: { sortField: 'name' },
        cell: ({ row }) => (
          <div className="flex min-w-0 flex-col">
            <span dir="auto" className="font-medium text-foreground">
              {row.original.name}
            </span>
            <span dir="auto" className="truncate text-xs text-muted-foreground">
              {row.original.address}
            </span>
          </div>
        ),
      },
      {
        id: 'code',
        header: t('fields.code'),
        meta: { sortField: 'code' },
        cell: ({ row }) => (
          <span dir="ltr" className="font-mono text-xs">
            {row.original.code}
          </span>
        ),
      },
      {
        id: 'on_hand',
        header: t('fields.onHand'),
        meta: { className: 'text-end' },
        cell: ({ row }) => (
          <span className="tabular-nums">{number(row.original.on_hand ?? 0)}</span>
        ),
      },
      {
        id: 'status',
        header: t('fields.status'),
        cell: ({ row }) =>
          row.original.is_active ? (
            <Badge variant="secondary">{t('status.active')}</Badge>
          ) : (
            <Badge variant="outline">{t('status.inactive')}</Badge>
          ),
      },
      ...(canManage
        ? [
            {
              id: 'actions',
              header: () => <span className="sr-only">{t('common:dataTable.actions')}</span>,
              meta: { className: 'w-12 text-end' },
              cell: ({ row }) => (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={`${t('common:actions.edit')} ${row.original.name}`}
                  onClick={() => setEditing({ warehouse: row.original })}
                >
                  <PencilIcon aria-hidden="true" />
                </Button>
              ),
            } satisfies DataTableColumn<Warehouse>,
          ]
        : []),
    ],
    [t, canManage, number],
  )

  const newButton = (
    <Button type="button" className="gap-2" onClick={() => setEditing({ warehouse: null })}>
      <PlusIcon className="size-4" aria-hidden="true" />
      {t('warehouses.new')}
    </Button>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.warehouses')}
        description={t('warehouses.description')}
        actions={canManage && newButton}
      />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('warehouses.search')} />
          <DataTableFilter
            list={list}
            name="status"
            label={t('fields.status')}
            allLabel={t('status.all')}
            options={[
              { value: 'active', label: t('status.active') },
              { value: 'inactive', label: t('status.inactive') },
            ]}
          />
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={warehouses.data}
          isPending={warehouses.isPending}
          isError={warehouses.isError}
          isFetching={warehouses.isFetching}
          onRetry={() => void warehouses.refetch()}
          list={list}
          getRowId={(warehouse) => String(warehouse.id)}
          label={t('common:pages.warehouses')}
          loadingLabel={t('warehouses.loading')}
          empty={{
            icon: WarehouseIcon,
            title: t('warehouses.emptyTitle'),
            description: canManage
              ? t('warehouses.emptyDescription')
              : t('warehouses.emptyDescriptionReadOnly'),
            action: canManage ? newButton : undefined,
          }}
        />
      </div>

      <WarehouseFormDialog
        open={editing !== null}
        warehouse={editing?.warehouse ?? null}
        onOpenChange={(open) => !open && setEditing(null)}
      />
    </div>
  )
}
