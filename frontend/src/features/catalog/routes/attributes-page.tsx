import { PlusIcon, SlidersHorizontalIcon } from 'lucide-react'
import { useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { DataTableColumn } from '@/components/data-table/columns'
import { DataTable } from '@/components/data-table/data-table'
import { DataTableSearch, DataTableToolbar } from '@/components/data-table/data-table-toolbar'
import { RowActions } from '@/components/data-table/row-actions'
import { ConfirmDeleteDialog } from '@/components/feedback/confirm-delete-dialog'
import { PageHeader } from '@/components/layout/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useCan } from '@/features/auth/hooks/use-can'
import { useListQuery } from '@/hooks/use-list-query'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import type { ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { AttributeFormDialog } from '../components/attribute-form-dialog'
import { useAttributes, useDeleteAttribute } from '../hooks/use-attributes'
import type { Attribute } from '../types'

/** What the Attributes list accepts in its URL (mirrors ListAttributesRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'name', direction: 'asc' },
  sorts: ['name', 'created_at'],
}

/** At most this many values show as chips; the rest are counted. */
const SHOWN_VALUES = 8

/** The Attribute library: search, sort, page; create, edit (with values), delete. */
export function AttributesPage() {
  const { t } = useTranslation('catalog')
  const canManage = useCan()('catalog.manage')
  const list = useListQuery(LIST_CONFIG)
  const attributes = useAttributes(list.query)
  const deleteAttribute = useDeleteAttribute()
  const [editing, setEditing] = useState<{ attribute: Attribute | null } | null>(null)
  const [deleting, setDeleting] = useState<Attribute | null>(null)
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<Attribute>[]>(
    () => [
      {
        id: 'name',
        header: t('fields.name'),
        meta: { sortField: 'name' },
        cell: ({ row }) => (
          <div className="flex min-w-32 flex-col gap-0.5">
            <span dir="auto" className="self-start font-medium">
              {row.original.name}
            </span>
            <span className="text-xs text-muted-foreground">
              {t('attributes.valueCount', { count: row.original.values.length })}
            </span>
          </div>
        ),
      },
      {
        id: 'values',
        header: t('attributes.values'),
        cell: ({ row }) => {
          const { values } = row.original
          return (
            <div className="flex min-w-48 flex-wrap gap-1.5">
              {values.slice(0, SHOWN_VALUES).map((value) => (
                <Badge key={value.id} variant="secondary" className="gap-1.5 font-normal">
                  <span dir="auto">{value.name}</span>
                  <span className="font-mono text-[0.65rem] text-muted-foreground" dir="ltr">
                    {value.code}
                  </span>
                </Badge>
              ))}
              {values.length > SHOWN_VALUES && (
                <Badge variant="outline">+{values.length - SHOWN_VALUES}</Badge>
              )}
            </div>
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
                  onEdit={() => setEditing({ attribute: row.original })}
                  onDelete={() => setDeleting(row.original)}
                />
              ),
            } satisfies DataTableColumn<Attribute>,
          ]
        : []),
    ],
    [t, canManage],
  )

  const confirmDelete = () => {
    if (!deleting) return
    deleteAttribute.mutate(deleting.id, {
      onSuccess: () => toast.success(i18n.t('catalog:attributes.deleted')),
      onError: (error) => toast.error(apiErrorMessage(error)),
      onSettled: () => setDeleting(null),
    })
  }

  const newButton = (
    <Button type="button" className="gap-2" onClick={() => setEditing({ attribute: null })}>
      <PlusIcon className="size-4" aria-hidden="true" />
      {t('attributes.new')}
    </Button>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.attributes')}
        description={t('attributes.description')}
        actions={canManage && newButton}
      />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('attributes.search')} />
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={attributes.data}
          isPending={attributes.isPending}
          isError={attributes.isError}
          isFetching={attributes.isFetching}
          onRetry={() => void attributes.refetch()}
          list={list}
          getRowId={(attribute) => String(attribute.id)}
          label={t('common:pages.attributes')}
          loadingLabel={t('attributes.loading')}
          empty={{
            icon: SlidersHorizontalIcon,
            title: t('attributes.emptyTitle'),
            description: canManage
              ? t('attributes.emptyDescription')
              : t('attributes.emptyDescriptionReadOnly'),
            action: canManage ? newButton : undefined,
          }}
        />
      </div>

      <AttributeFormDialog
        open={editing !== null}
        attribute={editing?.attribute ?? null}
        onOpenChange={(open) => !open && setEditing(null)}
      />
      <ConfirmDeleteDialog
        name={deleting?.name ?? null}
        description={t('attributes.deleteDescription')}
        pending={deleteAttribute.isPending}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
