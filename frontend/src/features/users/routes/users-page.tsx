import { PlusIcon, UserCogIcon } from 'lucide-react'
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
import { PageHeader } from '@/components/layout/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useCurrentUser } from '@/features/auth/hooks/use-auth'
import { ROLES } from '@/features/auth/types'
import { useListQuery } from '@/hooks/use-list-query'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import type { ListQueryConfig } from '@/lib/list-query'
import { useEntranceAnimation } from '@/lib/motion'
import { UserFormDialog } from '../components/user-form-dialog'
import { useSetUserActive, useUsers } from '../hooks/use-users'
import type { User } from '../types'

const STATUSES = ['active', 'deactivated'] as const

/** What the Users list accepts in its URL (mirrors ListUsersRequest). */
const LIST_CONFIG: ListQueryConfig = {
  defaultSort: { field: 'name', direction: 'asc' },
  sorts: ['name', 'email', 'created_at'],
  filters: {
    role: (value) => (ROLES as readonly string[]).includes(value),
    status: (value) => (STATUSES as readonly string[]).includes(value),
  },
}

/** Users: search, filter by Role and status, add, edit, deactivate and reactivate. */
export function UsersPage() {
  const { t } = useTranslation('users')
  const list = useListQuery(LIST_CONFIG)
  const users = useUsers(list.query)
  const me = useCurrentUser().data
  const setActive = useSetUserActive()
  const [editing, setEditing] = useState<User | null>(null)
  const [creating, setCreating] = useState(false)
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const columns = useMemo<DataTableColumn<User>[]>(() => {
    const toggle = async (user: User) => {
      try {
        await setActive.mutateAsync({ id: user.id, active: !user.is_active })
        toast.success(
          i18n.t(user.is_active ? 'users:toasts.deactivated' : 'users:toasts.reactivated', {
            name: user.name,
          }),
        )
      } catch (error) {
        toast.error(apiErrorMessage(error))
      }
    }

    return [
      {
        id: 'name',
        header: t('fields.name'),
        meta: { sortField: 'name' },
        cell: ({ row }) => (
          <div className="flex min-w-40 flex-col gap-0.5">
            <span dir="auto" className="self-start font-medium text-foreground">
              {row.original.name}
              {row.original.id === me?.id && (
                <span className="ms-2 text-xs font-normal text-muted-foreground">{t('you')}</span>
              )}
            </span>
            <span className="self-start truncate text-xs text-muted-foreground" dir="ltr">
              {row.original.email}
            </span>
            {row.original.role && (
              <Badge variant="secondary" className="mt-1 sm:hidden">
                {t(`common:roles.${row.original.role}`)}
              </Badge>
            )}
          </div>
        ),
      },
      {
        id: 'role',
        header: t('fields.role'),
        meta: { className: 'hidden sm:table-cell' },
        cell: ({ row }) =>
          row.original.role ? (
            <Badge variant="secondary">{t(`common:roles.${row.original.role}`)}</Badge>
          ) : (
            '—'
          ),
      },
      {
        id: 'status',
        header: t('fields.status'),
        meta: { className: 'hidden sm:table-cell' },
        cell: ({ row }) => (
          <Badge variant={row.original.is_active ? 'outline' : 'destructive'}>
            {t(`statuses.${row.original.is_active ? 'active' : 'deactivated'}`)}
          </Badge>
        ),
      },
      {
        id: 'actions',
        header: () => <span className="sr-only">{t('fields.actions')}</span>,
        meta: { className: 'text-end' },
        cell: ({ row }) => {
          const user = row.original
          const isSelf = user.id === me?.id
          return (
            <div className="flex flex-col items-end gap-1 sm:flex-row sm:justify-end sm:gap-2">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                aria-label={t('editUser', { name: user.name })}
                onClick={() => setEditing(user)}
              >
                {t('common:actions.edit')}
              </Button>
              {!isSelf && (
                <Button
                  type="button"
                  variant={user.is_active ? 'outline' : 'secondary'}
                  size="sm"
                  disabled={setActive.isPending}
                  aria-label={t(user.is_active ? 'deactivateUser' : 'reactivateUser', {
                    name: user.name,
                  })}
                  onClick={() => void toggle(user)}
                >
                  {t(user.is_active ? 'deactivate' : 'reactivate')}
                </Button>
              )}
            </div>
          )
        },
      },
    ]
  }, [t, me?.id, setActive])

  const newButton = (
    <Button type="button" className="gap-2" onClick={() => setCreating(true)}>
      <PlusIcon className="size-4" aria-hidden="true" />
      {t('new')}
    </Button>
  )

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader title={t('common:pages.users')} description={t('description')} actions={newButton} />
      <div className="js-reveal flex flex-col gap-4">
        <DataTableToolbar list={list}>
          <DataTableSearch list={list} label={t('search')} />
          <DataTableFilter
            list={list}
            name="role"
            label={t('fields.role')}
            allLabel={t('allRoles')}
            options={ROLES.map((role) => ({ value: role, label: t(`common:roles.${role}`) }))}
          />
          <DataTableFilter
            list={list}
            name="status"
            label={t('fields.status')}
            allLabel={t('allStatuses')}
            options={STATUSES.map((status) => ({ value: status, label: t(`statuses.${status}`) }))}
          />
        </DataTableToolbar>
        <DataTable
          columns={columns}
          page={users.data}
          isPending={users.isPending}
          isError={users.isError}
          isFetching={users.isFetching}
          onRetry={() => void users.refetch()}
          list={list}
          getRowId={(user) => String(user.id)}
          label={t('common:pages.users')}
          loadingLabel={t('loading')}
          empty={{
            icon: UserCogIcon,
            title: t('emptyTitle'),
            description: t('emptyDescription'),
            action: newButton,
          }}
        />
      </div>

      <UserFormDialog open={creating} user={null} onOpenChange={setCreating} />
      <UserFormDialog
        open={editing !== null}
        user={editing}
        isSelf={editing?.id === me?.id}
        onOpenChange={(open) => !open && setEditing(null)}
      />
    </div>
  )
}
