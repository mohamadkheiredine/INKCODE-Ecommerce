import { HistoryIcon } from 'lucide-react'
import { useCallback, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useDirection } from '@/components/ui/direction'
import { Input } from '@/components/ui/input'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import { type ListQueryConfig, isRecordId } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { AuditDiff } from '../components/audit-diff'
import { useAuditAuthors, useAuditEntries } from '../hooks/use-audit-log'
import { RECORD_TYPE_KEYS, isRecordType, recordLink } from '../record-types'
import { AUDIT_ACTIONS, type AuditAction, type AuditEntry } from '../types'

const isDay = (value: string) => /^\d{4}-\d{2}-\d{2}$/.test(value)

/** What the audit log accepts in its URL (mirrors ListAuditEntriesRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'created_at', direction: 'desc' },
  sorts: ['created_at'],
  filters: {
    user: isRecordId,
    action: AUDIT_ACTIONS,
    record_type: isRecordType,
    record: isRecordId,
    from: isDay,
    to: isDay,
  },
}

const ACTION_BADGE: Record<AuditAction, 'secondary' | 'outline' | 'destructive'> = {
  created: 'secondary',
  updated: 'outline',
  deleted: 'destructive',
  password_changed: 'outline',
  status_changed: 'secondary',
}

/** The audit log: every recorded change, searchable and filterable, read-only. */
export function AuditLogPage() {
  const { t } = useTranslation('audit')
  const list = useListQuery(LIST_CONFIG)
  const entries = useAuditEntries(list.query)
  const authors = useAuditAuthors()
  const { dateTime } = useFormatters()
  const dir = useDirection()
  const [opened, setOpened] = useState<AuditEntry | null>(null)
  const container = useRef<HTMLDivElement>(null)
  const { filters } = list.query

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const recordType = useCallback(
    (type: string) => (isRecordType(type) ? t(`recordTypes.${type}`) : type.replaceAll('_', ' ')),
    [t],
  )

  const columns = useMemo<DataTableColumn<AuditEntry>[]>(
    () => [
      {
        id: 'created_at',
        header: t('when'),
        meta: { sortField: 'created_at' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap text-muted-foreground">
            {dateTime(row.original.created_at)}
          </span>
        ),
      },
      {
        id: 'record',
        header: t('record'),
        cell: ({ row }) => {
          const link = recordLink(row.original)
          return (
            <div className="flex min-w-44 flex-col gap-0.5">
              {link ? (
                <Link
                  to={link}
                  dir="auto"
                  className="self-start font-medium underline-offset-4 hover:text-primary hover:underline focus-visible:underline"
                >
                  {row.original.record_label}
                </Link>
              ) : (
                <span dir="auto" className="self-start font-medium">
                  {row.original.record_label}
                </span>
              )}
              <span className="text-xs text-muted-foreground">
                {recordType(row.original.record_type)}
              </span>
            </div>
          )
        },
      },
      {
        id: 'action',
        header: t('action'),
        cell: ({ row }) => (
          <Badge variant={ACTION_BADGE[row.original.action]}>
            {t(`actions.${row.original.action}`)}
          </Badge>
        ),
      },
      {
        id: 'user',
        header: t('user'),
        meta: { className: 'hidden md:table-cell' },
        cell: ({ row }) => (
          <span dir="auto" className="whitespace-nowrap">
            {row.original.user?.name ?? t('system')}
          </span>
        ),
      },
      {
        id: 'changes',
        header: t('changes'),
        cell: ({ row }) => (
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => setOpened(row.original)}
            aria-label={t('viewChangesFor', { name: row.original.record_label })}
          >
            {t('viewChanges')}
          </Button>
        ),
      },
    ],
    [t, dateTime, recordType],
  )

  /** A record id only makes sense with its type, so changing the type drops it. */
  const setRecordType = (value: string | null) => {
    list.setFilter('record', null)
    list.setFilter('record_type', value)
  }

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader title={t('common:pages.auditLog')} description={t('description')} />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('search')} />
          <NativeSelect
            aria-label={t('recordType')}
            value={filters.record_type ?? ''}
            onChange={(event) => setRecordType(event.target.value || null)}
            className="min-w-40"
          >
            <NativeSelectOption value="">{t('allRecordTypes')}</NativeSelectOption>
            {RECORD_TYPE_KEYS.map((type) => (
              <NativeSelectOption key={type} value={type}>
                {t(`recordTypes.${type}`)}
              </NativeSelectOption>
            ))}
          </NativeSelect>
          <DataTableFilter
            list={list}
            name="action"
            label={t('action')}
            allLabel={t('allActions')}
            options={AUDIT_ACTIONS.map((action) => ({
              value: action,
              label: t(`actions.${action}`),
            }))}
          />
          <DataTableFilter
            list={list}
            name="user"
            label={t('user')}
            allLabel={t('allUsers')}
            options={(authors.data ?? []).map((author) => ({
              value: String(author.id),
              label: author.name,
            }))}
          />
          <div className="flex flex-wrap items-center gap-3">
            <label className="flex items-center gap-2 text-sm text-muted-foreground">
              {t('from')}
              <Input
                type="date"
                value={filters.from ?? ''}
                max={filters.to}
                onChange={(event) => list.setFilter('from', event.target.value || null)}
                className="h-9 w-40 bg-card"
              />
            </label>
            <label className="flex items-center gap-2 text-sm text-muted-foreground">
              {t('to')}
              <Input
                type="date"
                value={filters.to ?? ''}
                min={filters.from}
                onChange={(event) => list.setFilter('to', event.target.value || null)}
                className="h-9 w-40 bg-card"
              />
            </label>
          </div>
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={entries.data}
          isPending={entries.isPending}
          isError={entries.isError}
          isFetching={entries.isFetching}
          onRetry={() => void entries.refetch()}
          list={list}
          getRowId={(entry) => String(entry.id)}
          label={t('common:pages.auditLog')}
          loadingLabel={t('loading')}
          empty={{
            icon: HistoryIcon,
            title: t('emptyTitle'),
            description: t('emptyDescription'),
            action: (
              <Button asChild variant="outline">
                <Link to="/catalog/products">{t('emptyAction')}</Link>
              </Button>
            ),
          }}
        />
      </div>

      <Dialog open={opened !== null} onOpenChange={(open) => !open && setOpened(null)}>
        <DialogContent dir={dir} className="sm:max-w-2xl">
          {opened && (
            <>
              <DialogHeader>
                <DialogTitle dir="auto">
                  {t(`actions.${opened.action}`)} · {opened.record_label}
                </DialogTitle>
                <DialogDescription>
                  {t('madeBy', {
                    type: recordType(opened.record_type),
                    user: opened.user?.name ?? t('system'),
                    when: dateTime(opened.created_at),
                  })}
                </DialogDescription>
              </DialogHeader>
              <AuditDiff entry={opened} />
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
