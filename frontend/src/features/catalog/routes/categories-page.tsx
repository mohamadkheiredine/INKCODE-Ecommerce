import { FolderTreeIcon, PlusIcon } from 'lucide-react'
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
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useCan } from '@/features/auth/hooks/use-can'
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import type { ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { CategoryFormDialog } from '../components/category-form-dialog'
import { useCategories, useDeleteCategory } from '../hooks/use-categories'
import type { Category } from '../types'

/** What the Categories list accepts in its URL (mirrors ListCategoriesRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'name', direction: 'asc' },
  sorts: ['name', 'children_count', 'created_at'],
  filters: { level: ['top', 'child'] },
}

type Editing = { category: Category | null } | null

/** Categories in two levels: search, filter by level, sort, page; create, edit, delete. */
export function CategoriesPage() {
  const { t } = useTranslation('catalog')
  const can = useCan()
  const canManage = can('catalog.manage')
  const list = useListQuery(LIST_CONFIG)
  const categories = useCategories(list.query)
  const deleteCategory = useDeleteCategory()
  const { date, number } = useFormatters()
  const [editing, setEditing] = useState<Editing>(null)
  const [deleting, setDeleting] = useState<Category | null>(null)
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<Category>[]>(
    () => [
      {
        id: 'name',
        header: t('fields.name'),
        meta: { sortField: 'name' },
        cell: ({ row }) => (
          <div className="flex min-w-40 flex-col gap-0.5">
            <span data-name dir="auto" className="self-start font-medium text-foreground">
              {row.original.name}
            </span>
            <span className="self-start text-xs text-muted-foreground" dir="ltr">
              {row.original.slug}
            </span>
          </div>
        ),
      },
      {
        id: 'parent',
        header: t('categories.parentColumn'),
        cell: ({ row }) =>
          row.original.parent ? (
            <span dir="auto">{row.original.parent.name}</span>
          ) : (
            <Badge variant="secondary">{t('categories.topLevel')}</Badge>
          ),
      },
      {
        id: 'children_count',
        header: t('categories.children'),
        meta: { sortField: 'children_count', className: 'hidden sm:table-cell' },
        cell: ({ row }) => (
          <span className="tabular-nums">{number(row.original.children_count)}</span>
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
                  onEdit={() => setEditing({ category: row.original })}
                  onDelete={() => setDeleting(row.original)}
                />
              ),
            } satisfies DataTableColumn<Category>,
          ]
        : []),
    ],
    [t, canManage, date, number],
  )

  const confirmDelete = () => {
    if (!deleting) return
    deleteCategory.mutate(deleting.id, {
      onSuccess: () => toast.success(i18n.t('catalog:categories.deleted')),
      onError: (error) => toast.error(apiErrorMessage(error)),
      onSettled: () => setDeleting(null),
    })
  }

  const newButton = canManage && (
    <Button type="button" className="gap-2" onClick={() => setEditing({ category: null })}>
      <PlusIcon className="size-4" aria-hidden="true" />
      {t('categories.new')}
    </Button>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.categories')}
        description={t('categories.description')}
        actions={newButton}
      />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('categories.search')} />
          <DataTableFilter
            list={list}
            name="level"
            label={t('categories.level')}
            allLabel={t('categories.allLevels')}
            options={[
              { value: 'top', label: t('categories.topLevel') },
              { value: 'child', label: t('categories.child') },
            ]}
          />
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={categories.data}
          isPending={categories.isPending}
          isError={categories.isError}
          isFetching={categories.isFetching}
          onRetry={() => void categories.refetch()}
          list={list}
          getRowId={(category) => String(category.id)}
          label={t('common:pages.categories')}
          loadingLabel={t('categories.loading')}
          empty={{
            icon: FolderTreeIcon,
            title: t('categories.emptyTitle'),
            description: canManage
              ? t('categories.emptyDescription')
              : t('categories.emptyDescriptionReadOnly'),
            action: canManage ? (
              <Button
                type="button"
                className="gap-2"
                onClick={() => setEditing({ category: null })}
              >
                <PlusIcon className="size-4" aria-hidden="true" />
                {t('categories.new')}
              </Button>
            ) : undefined,
          }}
        />
      </div>

      <CategoryFormDialog
        open={editing !== null}
        category={editing?.category ?? null}
        onOpenChange={(open) => !open && setEditing(null)}
      />
      <ConfirmDeleteDialog
        name={deleting?.name ?? null}
        description={t('categories.deleteDescription')}
        pending={deleteCategory.isPending}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
