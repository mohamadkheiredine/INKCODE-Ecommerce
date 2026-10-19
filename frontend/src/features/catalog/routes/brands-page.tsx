import { PlusIcon, TagIcon } from 'lucide-react'
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
import { LogoTile } from '@/components/media/logo-tile'
import { Button } from '@/components/ui/button'
import { useCan } from '@/features/auth/hooks/use-can'
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import type { ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { BrandFormDialog } from '../components/brand-form-dialog'
import { useBrands, useDeleteBrand } from '../hooks/use-brands'
import type { Brand } from '../types'

/** What the Brands list accepts in its URL (mirrors ListBrandsRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'name', direction: 'asc' },
  sorts: ['name', 'created_at'],
  filters: { logo: ['with', 'without'] },
}

type Editing = { brand: Brand | null } | null

/** Brands: search, filter by logo, sort, page; create, edit (with a logo), delete. */
export function BrandsPage() {
  const { t } = useTranslation('catalog')
  const can = useCan()
  const canManage = can('catalog.manage')
  const list = useListQuery(LIST_CONFIG)
  const brands = useBrands(list.query)
  const deleteBrand = useDeleteBrand()
  const { date } = useFormatters()
  const [editing, setEditing] = useState<Editing>(null)
  const [deleting, setDeleting] = useState<Brand | null>(null)
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<Brand>[]>(
    () => [
      {
        id: 'name',
        header: t('fields.name'),
        meta: { sortField: 'name' },
        cell: ({ row }) => (
          <div className="flex min-w-48 items-center gap-3">
            <LogoTile
              name={row.original.name}
              src={row.original.logo_url}
              alt={t('brands.logoOf', { name: row.original.name })}
            />
            <div className="flex flex-col gap-0.5">
              <span data-name dir="auto" className="self-start font-medium text-foreground">
                {row.original.name}
              </span>
              <span className="self-start text-xs text-muted-foreground" dir="ltr">
                {row.original.slug}
              </span>
            </div>
          </div>
        ),
      },
      {
        id: 'created_at',
        header: t('fields.created'),
        meta: { sortField: 'created_at', className: 'hidden sm:table-cell' },
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
                  onEdit={() => setEditing({ brand: row.original })}
                  onDelete={() => setDeleting(row.original)}
                />
              ),
            } satisfies DataTableColumn<Brand>,
          ]
        : []),
    ],
    [t, canManage, date],
  )

  const confirmDelete = () => {
    if (!deleting) return
    deleteBrand.mutate(deleting.id, {
      onSuccess: () => toast.success(i18n.t('catalog:brands.deleted')),
      onError: (error) => toast.error(apiErrorMessage(error)),
      onSettled: () => setDeleting(null),
    })
  }

  const newButton = (
    <Button type="button" className="gap-2" onClick={() => setEditing({ brand: null })}>
      <PlusIcon className="size-4" aria-hidden="true" />
      {t('brands.new')}
    </Button>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.brands')}
        description={t('brands.description')}
        actions={canManage && newButton}
      />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('brands.search')} />
          <DataTableFilter
            list={list}
            name="logo"
            label={t('brands.logoFilter')}
            allLabel={t('brands.allLogos')}
            options={[
              { value: 'with', label: t('brands.withLogo') },
              { value: 'without', label: t('brands.withoutLogo') },
            ]}
          />
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={brands.data}
          isPending={brands.isPending}
          isError={brands.isError}
          isFetching={brands.isFetching}
          onRetry={() => void brands.refetch()}
          list={list}
          getRowId={(brand) => String(brand.id)}
          label={t('common:pages.brands')}
          loadingLabel={t('brands.loading')}
          empty={{
            icon: TagIcon,
            title: t('brands.emptyTitle'),
            description: canManage
              ? t('brands.emptyDescription')
              : t('brands.emptyDescriptionReadOnly'),
            action: canManage ? newButton : undefined,
          }}
        />
      </div>

      <BrandFormDialog
        open={editing !== null}
        brand={editing?.brand ?? null}
        onOpenChange={(open) => !open && setEditing(null)}
      />
      <ConfirmDeleteDialog
        name={deleting?.name ?? null}
        description={t('brands.deleteDescription')}
        pending={deleteBrand.isPending}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
