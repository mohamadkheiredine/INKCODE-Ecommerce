import {
  ArchiveIcon,
  CheckCircle2Icon,
  PackageIcon,
  PlusIcon,
  Trash2Icon,
  XIcon,
} from 'lucide-react'
import { useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
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
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { useCan } from '@/features/auth/hooks/use-can'
import { type ListQueryControls, useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { type ListQueryConfig, isAmount, isId, toApiParams } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { ProductStatusBadge } from '../components/product-status-badge'
import { ProductThumbnail } from '../components/product-thumbnail'
import { useBrandOptions } from '../hooks/use-brands'
import { useCategoryOptions } from '../hooks/use-categories'
import { useBulkProducts, useDeleteProduct, useProducts } from '../hooks/use-products'
import { type BulkAction, PRODUCT_STATUSES, type ProductListItem } from '../types'

/** What the Products list accepts in its URL (mirrors ListProductsRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'name', direction: 'asc' },
  sorts: ['name', 'price', 'created_at'],
  filters: {
    status: PRODUCT_STATUSES,
    category_id: isId,
    brand_id: isId,
    price_min: isAmount,
    price_max: isAmount,
  },
}

/**
 * Products: search by name or SKU; filter by status, Category, Brand and price; sort by
 * name, price and date; select rows for bulk activate, archive and delete.
 */
export function ProductsPage() {
  const { t } = useTranslation('catalog')
  const canManage = useCan()('catalog.manage')
  const navigate = useNavigate()
  const list = useListQuery(LIST_CONFIG)
  const products = useProducts(list.query)
  const categories = useCategoryOptions()
  const brands = useBrandOptions()
  const deleteProduct = useDeleteProduct()
  const { money, date } = useFormatters()
  const [deleting, setDeleting] = useState<ProductListItem | null>(null)
  const selection = useSelection(JSON.stringify(toApiParams(list.query)))
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const rows = products.data?.data
  const columns = useMemo<DataTableColumn<ProductListItem>[]>(() => {
    const pageIds = rows?.map((product) => product.id) ?? []
    const allSelected = pageIds.length > 0 && pageIds.every((id) => selection.ids.has(id))

    return [
      ...(canManage
        ? [
            {
              id: 'select',
              header: () => (
                <Checkbox
                  aria-label={t('products.selectAll')}
                  checked={allSelected ? true : selection.ids.size > 0 ? 'indeterminate' : false}
                  onCheckedChange={(checked) => selection.set(checked === true ? pageIds : [])}
                />
              ),
              meta: { className: 'w-10' },
              cell: ({ row }) => (
                <Checkbox
                  aria-label={t('products.selectRow', { name: row.original.name })}
                  checked={selection.ids.has(row.original.id)}
                  onCheckedChange={() => selection.toggle(row.original.id)}
                />
              ),
            } satisfies DataTableColumn<ProductListItem>,
          ]
        : []),
      {
        id: 'name',
        header: t('fields.name'),
        meta: { sortField: 'name' },
        cell: ({ row }) => (
          <div className="flex min-w-56 items-center gap-3">
            <ProductThumbnail src={row.original.main_image_url} alt="" />
            <div className="flex min-w-0 flex-col gap-0.5">
              <Link
                to={`/catalog/products/${row.original.id}`}
                dir="auto"
                className="self-start font-medium text-foreground hover:underline"
              >
                {row.original.name}
              </Link>
              <span className="self-start text-xs text-muted-foreground">
                {row.original.variants_count > 0
                  ? t('products.variantCount', { count: row.original.variants_count })
                  : t('products.noVariants')}
              </span>
            </div>
          </div>
        ),
      },
      {
        id: 'category',
        header: t('fields.category'),
        meta: { className: 'hidden md:table-cell' },
        cell: ({ row }) => (
          <div className="flex flex-col gap-0.5 text-sm">
            <span dir="auto">{row.original.category.name}</span>
            {row.original.brand && (
              <span dir="auto" className="text-xs text-muted-foreground">
                {row.original.brand.name}
              </span>
            )}
          </div>
        ),
      },
      {
        id: 'price',
        header: t('fields.price'),
        meta: { sortField: 'price' },
        cell: ({ row }) => {
          const { price_min: min, price_max: max, base_price: base } = row.original
          return (
            <span className="whitespace-nowrap tabular-nums">
              {min && max && min !== max ? `${money(min)} – ${money(max)}` : money(min ?? base)}
            </span>
          )
        },
      },
      {
        id: 'status',
        header: t('fields.status'),
        cell: ({ row }) => <ProductStatusBadge status={row.original.status} />,
      },
      {
        id: 'created_at',
        header: t('fields.created'),
        meta: { sortField: 'created_at', className: 'hidden lg:table-cell' },
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
                  onEdit={() => void navigate(`/catalog/products/${row.original.id}`)}
                  onDelete={() => setDeleting(row.original)}
                />
              ),
            } satisfies DataTableColumn<ProductListItem>,
          ]
        : []),
    ]
  }, [t, canManage, money, date, navigate, rows, selection])

  const confirmDelete = () => {
    if (!deleting) return
    deleteProduct.mutate(deleting.id, {
      onSuccess: () => toast.success(i18n.t('catalog:products.deleted')),
      onError: (error) => toast.error(apiErrorMessage(error)),
      onSettled: () => setDeleting(null),
    })
  }

  const newButton = (
    <Button asChild className="gap-2">
      <Link to="/catalog/products/new">
        <PlusIcon className="size-4" aria-hidden="true" />
        {t('products.new')}
      </Link>
    </Button>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.products')}
        description={t('products.description')}
        actions={canManage && newButton}
      />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('products.search')} />
          <DataTableFilter
            list={list}
            name="status"
            label={t('fields.status')}
            allLabel={t('products.allStatuses')}
            options={PRODUCT_STATUSES.map((status) => ({
              value: status,
              label: t(`products.status.${status}`),
            }))}
          />
          <DataTableFilter
            list={list}
            name="category_id"
            label={t('fields.category')}
            allLabel={t('products.allCategories')}
            options={(categories.data ?? []).map((category) => ({
              value: String(category.id),
              label: category.parent ? `${category.parent.name} › ${category.name}` : category.name,
            }))}
          />
          <DataTableFilter
            list={list}
            name="brand_id"
            label={t('fields.brand')}
            allLabel={t('products.allBrands')}
            options={(brands.data ?? []).map((brand) => ({
              value: String(brand.id),
              label: brand.name,
            }))}
          />
          <PriceFilter list={list} name="price_min" label={t('products.priceMin')} />
          <PriceFilter list={list} name="price_max" label={t('products.priceMax')} />
        </DataTableToolbar>
        {canManage && selection.ids.size > 0 && (
          <BulkBar ids={[...selection.ids]} onDone={() => selection.set([])} />
        )}
        <DataTable
          columns={columns}
          page={products.data}
          isPending={products.isPending}
          isError={products.isError}
          isFetching={products.isFetching}
          onRetry={() => void products.refetch()}
          list={list}
          getRowId={(product) => String(product.id)}
          label={t('common:pages.products')}
          loadingLabel={t('products.loading')}
          empty={{
            icon: PackageIcon,
            title: t('products.emptyTitle'),
            description: canManage
              ? t('products.emptyDescription')
              : t('products.emptyDescriptionReadOnly'),
            action: canManage ? newButton : undefined,
          }}
        />
      </div>

      <ConfirmDeleteDialog
        name={deleting?.name ?? null}
        description={t('products.deleteDescription')}
        pending={deleteProduct.isPending}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}

const NOTHING_SELECTED: ReadonlySet<number> = new Set()

/**
 * The selected Product ids. A new search, filter, sort or page (`scope`) starts an empty
 * selection, so a bulk action only ever touches rows the User can see.
 */
function useSelection(scope: string) {
  const [state, setState] = useState<{ scope: string; ids: ReadonlySet<number> }>({
    scope,
    ids: NOTHING_SELECTED,
  })
  const ids = state.scope === scope ? state.ids : NOTHING_SELECTED

  return useMemo(
    () => ({
      ids,
      set: (next: number[]) => setState({ scope, ids: new Set(next) }),
      toggle: (id: number) => {
        const next = new Set(ids)
        if (next.has(id)) next.delete(id)
        else next.add(id)
        setState({ scope, ids: next })
      },
    }),
    [ids, scope],
  )
}

/** A price bound, applied when the field is left or Enter is pressed; invalid text is ignored. */
function PriceFilter({
  list,
  name,
  label,
}: {
  list: ListQueryControls
  name: 'price_min' | 'price_max'
  label: string
}) {
  const applied = list.query.filters[name] ?? ''
  const [value, setValue] = useState(applied)
  const [previous, setPrevious] = useState(applied)
  if (previous !== applied) {
    setPrevious(applied)
    setValue(applied)
  }

  const apply = () => {
    const trimmed = value.trim()
    if (trimmed === '' || isAmount(trimmed)) list.setFilter(name, trimmed || null)
    else setValue(applied)
  }

  return (
    <Input
      inputMode="decimal"
      aria-label={label}
      placeholder={label}
      value={value}
      onChange={(event) => setValue(event.target.value)}
      onBlur={apply}
      onKeyDown={(event) => event.key === 'Enter' && apply()}
      dir="ltr"
      className="h-9 bg-card text-start sm:w-32"
    />
  )
}

/** The selected count and the bulk actions; deleting asks first. */
function BulkBar({ ids, onDone }: { ids: number[]; onDone: () => void }) {
  const { t } = useTranslation('catalog')
  const bulk = useBulkProducts()
  const [confirmingDelete, setConfirmingDelete] = useState(false)

  const run = (action: BulkAction) =>
    bulk.mutate(
      { action, ids },
      {
        onSuccess: ({ changed, deleted, skipped }) => {
          if (action === 'delete') {
            if (deleted > 0)
              toast.success(i18n.t('catalog:products.bulkDeleted', { count: deleted }))
          } else {
            toast.success(
              changed > 0
                ? i18n.t('catalog:products.bulkChanged', { count: changed })
                : i18n.t('catalog:products.bulkNothingChanged'),
            )
          }
          if (skipped.length > 0) {
            toast.warning(
              i18n.t('catalog:products.bulkSkipped', {
                count: skipped.length,
                reasons: skipped.map((product) => product.reason).join(' '),
              }),
            )
          }
          onDone()
        },
        onError: (error) => toast.error(apiErrorMessage(error)),
        onSettled: () => setConfirmingDelete(false),
      },
    )

  return (
    <div
      role="region"
      aria-label={t('products.selected', { count: ids.length })}
      className="flex flex-wrap items-center gap-2 rounded-xl border bg-primary/5 px-3 py-2"
    >
      <span className="me-auto text-sm font-medium">
        {t('products.selected', { count: ids.length })}
      </span>
      <Button
        type="button"
        size="sm"
        variant="outline"
        className="gap-1.5"
        disabled={bulk.isPending}
        onClick={() => run('activate')}
      >
        <CheckCircle2Icon className="size-4" aria-hidden="true" />
        {t('products.bulkActivate')}
      </Button>
      <Button
        type="button"
        size="sm"
        variant="outline"
        className="gap-1.5"
        disabled={bulk.isPending}
        onClick={() => run('archive')}
      >
        <ArchiveIcon className="size-4" aria-hidden="true" />
        {t('products.bulkArchive')}
      </Button>
      <Button
        type="button"
        size="sm"
        variant="outline"
        className="gap-1.5 text-destructive"
        disabled={bulk.isPending}
        onClick={() => setConfirmingDelete(true)}
      >
        <Trash2Icon className="size-4" aria-hidden="true" />
        {t('products.bulkDelete')}
      </Button>
      <Button
        type="button"
        size="icon-sm"
        variant="ghost"
        aria-label={t('products.clearSelection')}
        onClick={onDone}
      >
        <XIcon aria-hidden="true" />
      </Button>
      <ConfirmDeleteDialog
        name={confirmingDelete ? t('products.productCount', { count: ids.length }) : null}
        description={t('products.bulkDeleteDescription')}
        pending={bulk.isPending}
        onConfirm={() => run('delete')}
        onCancel={() => setConfirmingDelete(false)}
      />
    </div>
  )
}
