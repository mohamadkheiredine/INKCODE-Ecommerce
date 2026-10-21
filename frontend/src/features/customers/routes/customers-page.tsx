import { PlusIcon, UsersIcon } from 'lucide-react'
import { useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useSearchParams } from 'react-router'
import type { DataTableColumn } from '@/components/data-table/columns'
import { DataTable } from '@/components/data-table/data-table'
import {
  DataTableFilter,
  DataTableSearch,
  DataTableToolbar,
} from '@/components/data-table/data-table-toolbar'
import { PageHeader } from '@/components/layout/page-header'
import { Button } from '@/components/ui/button'
import { useCan } from '@/features/auth/hooks/use-can'
import { useListQuery } from '@/hooks/use-list-query'
import { useFormatters } from '@/lib/format'
import { type ListQueryConfig, isRecordId } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { CustomerAvatar } from '../components/customer-avatar'
import { CustomerFormDialog } from '../components/customer-form-dialog'
import { TagList } from '../components/tag-list'
import { useCustomerTags, useCustomers } from '../hooks/use-customers'
import type { Customer } from '../types'

/** What the Customers list accepts in its URL (mirrors ListCustomersRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'created_at', direction: 'desc' },
  sorts: ['name', 'created_at'],
  filters: { tag: isRecordId },
}

/** Customers: search by name, email or phone, filter by Tag, sort, page; add a Customer. */
export function CustomersPage() {
  const { t } = useTranslation('customers')
  const can = useCan()
  const canManage = can('customers.manage')
  const list = useListQuery(LIST_CONFIG)
  const customers = useCustomers(list.query)
  const tags = useCustomerTags()
  const navigate = useNavigate()
  const { date } = useFormatters()
  // The "New customer" quick action links here with ?new=1 to open the form at once.
  const [searchParams, setSearchParams] = useSearchParams()
  const [creating, setCreating] = useState(false)
  const createRequested = canManage && searchParams.get('new') === '1'
  const container = useRef<HTMLDivElement>(null)

  const handleCreatingChange = (open: boolean) => {
    setCreating(open)
    if (open || !createRequested) return
    setSearchParams(
      (params) => {
        params.delete('new')
        return params
      },
      { replace: true },
    )
  }

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<Customer>[]>(
    () => [
      {
        id: 'name',
        header: t('fields.name'),
        meta: { sortField: 'name' },
        cell: ({ row }) => (
          <div className="flex min-w-52 items-center gap-3">
            <CustomerAvatar name={row.original.name} />
            <div className="flex min-w-0 flex-col gap-0.5">
              <Link
                to={`/customers/${row.original.id}`}
                dir="auto"
                className="self-start font-medium text-foreground underline-offset-4 hover:text-primary hover:underline focus-visible:underline"
              >
                {row.original.name}
              </Link>
              {row.original.email && (
                <span className="self-start truncate text-xs text-muted-foreground" dir="ltr">
                  {row.original.email}
                </span>
              )}
            </div>
          </div>
        ),
      },
      {
        id: 'phone',
        header: t('fields.phone'),
        meta: { className: 'hidden md:table-cell' },
        cell: ({ row }) =>
          row.original.phone ? (
            <span className="whitespace-nowrap text-muted-foreground" dir="ltr">
              {row.original.phone}
            </span>
          ) : (
            <span className="text-muted-foreground" aria-label={t('noPhone')}>
              —
            </span>
          ),
      },
      {
        id: 'tags',
        header: t('fields.tags'),
        meta: { className: 'hidden sm:table-cell' },
        cell: ({ row }) => <TagList tags={row.original.tags} className="max-w-72" />,
      },
      {
        id: 'created_at',
        header: t('fields.since'),
        meta: { sortField: 'created_at', className: 'hidden lg:table-cell' },
        cell: ({ row }) => (
          <span className="whitespace-nowrap text-muted-foreground">
            {date(row.original.created_at)}
          </span>
        ),
      },
    ],
    [t, date],
  )

  const newButton = (
    <Button type="button" className="gap-2" onClick={() => setCreating(true)}>
      <PlusIcon className="size-4" aria-hidden="true" />
      {t('new')}
    </Button>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={t('common:pages.customers')}
        description={t('description')}
        actions={canManage && newButton}
      />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('search')} />
          <DataTableFilter
            list={list}
            name="tag"
            label={t('tagFilter')}
            allLabel={t('allTags')}
            options={(tags.data ?? []).map((tag) => ({ value: String(tag.id), label: tag.name }))}
          />
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={customers.data}
          isPending={customers.isPending}
          isError={customers.isError}
          isFetching={customers.isFetching}
          onRetry={() => void customers.refetch()}
          list={list}
          getRowId={(customer) => String(customer.id)}
          label={t('common:pages.customers')}
          loadingLabel={t('loading')}
          empty={{
            icon: UsersIcon,
            title: t('emptyTitle'),
            description: canManage ? t('emptyDescription') : t('emptyDescriptionReadOnly'),
            action: canManage ? newButton : undefined,
          }}
        />
      </div>

      <CustomerFormDialog
        open={creating || createRequested}
        customer={null}
        onOpenChange={handleCreatingChange}
        onSaved={(customer) => void navigate(`/customers/${customer.id}`)}
      />
    </div>
  )
}
