import { MailIcon, PencilIcon, PhoneIcon, Trash2Icon, UserXIcon } from 'lucide-react'
import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { toast } from 'sonner'
import { ConfirmDeleteDialog } from '@/components/feedback/confirm-delete-dialog'
import { EmptyState } from '@/components/feedback/empty-state'
import { ErrorState } from '@/components/feedback/error-state'
import { ContentSkeleton } from '@/components/feedback/page-skeleton'
import { PageHeader } from '@/components/layout/page-header'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { useCan } from '@/features/auth/hooks/use-can'
import { isApiError } from '@/lib/api/api-error'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useEntranceAnimation } from '@/lib/motion'
import { AddressesCard } from '../components/addresses-card'
import { CustomerAvatar } from '../components/customer-avatar'
import { CustomerFormDialog } from '../components/customer-form-dialog'
import { NotesCard } from '../components/notes-card'
import { TagList } from '../components/tag-list'
import { useCustomer, useDeleteCustomer } from '../hooks/use-customers'
import type { CustomerProfile } from '../types'

/**
 * A Customer's profile: contact details and Tags, addresses, and notes. Order history
 * and Lifetime value join this page with the Orders and Payments modules.
 */
export function CustomerPage() {
  const { t } = useTranslation('customers')
  const id = Number(useParams().customerId)
  const customer = useCustomer(id)

  if (customer.isPending) return <ContentSkeleton />
  if (customer.isError) {
    if (isApiError(customer.error) && customer.error.status === 404) {
      return (
        <EmptyState
          className="mt-6"
          icon={UserXIcon}
          title={t('profile.notFoundTitle')}
          description={t('profile.notFoundDescription')}
          action={
            <Button asChild>
              <Link to="/customers">{t('profile.backToList')}</Link>
            </Button>
          }
        />
      )
    }
    return <ErrorState onRetry={() => void customer.refetch()} retrying={customer.isFetching} />
  }
  return <Profile customer={customer.data} />
}

function Profile({ customer }: { customer: CustomerProfile }) {
  const { t } = useTranslation('customers')
  const canManage = useCan()('customers.manage')
  const { date } = useFormatters()
  const navigate = useNavigate()
  const deleteCustomer = useDeleteCustomer()
  const [editing, setEditing] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const confirmDelete = () =>
    deleteCustomer.mutate(customer.id, {
      onSuccess: () => {
        toast.success(i18n.t('customers:toasts.deleted'))
        void navigate('/customers')
      },
      onError: (error) => {
        toast.error(apiErrorMessage(error))
        setDeleting(false)
      },
    })

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={customer.name}
        description={t('profile.since', { date: date(customer.created_at) })}
        actions={
          canManage && (
            <>
              <Button
                type="button"
                variant="outline"
                className="gap-2"
                onClick={() => setEditing(true)}
              >
                <PencilIcon className="size-4" aria-hidden="true" />
                {t('common:actions.edit')}
              </Button>
              <Button
                type="button"
                variant="outline"
                className="gap-2 text-destructive hover:text-destructive"
                onClick={() => setDeleting(true)}
              >
                <Trash2Icon className="size-4" aria-hidden="true" />
                {t('common:actions.delete')}
              </Button>
            </>
          )
        }
      />

      <Card className="js-reveal">
        <CardContent className="flex flex-col gap-5 sm:flex-row sm:items-center">
          <CustomerAvatar name={customer.name} className="size-14 text-lg" />
          <dl className="grid flex-1 gap-4 sm:grid-cols-3">
            <ContactItem label={t('fields.email')} icon={MailIcon}>
              {customer.email ? (
                <a
                  href={`mailto:${customer.email}`}
                  dir="ltr"
                  className="break-all hover:text-primary hover:underline"
                >
                  {customer.email}
                </a>
              ) : (
                <span className="text-muted-foreground">{t('profile.none')}</span>
              )}
            </ContactItem>
            <ContactItem label={t('fields.phone')} icon={PhoneIcon}>
              {customer.phone ? (
                <a
                  href={`tel:${customer.phone.replace(/[^\d+]/g, '')}`}
                  dir="ltr"
                  className="hover:text-primary hover:underline"
                >
                  {customer.phone}
                </a>
              ) : (
                <span className="text-muted-foreground">{t('profile.none')}</span>
              )}
            </ContactItem>
            <div className="flex flex-col gap-1.5">
              <dt className="text-xs font-medium text-muted-foreground">{t('fields.tags')}</dt>
              <dd>
                {customer.tags.length > 0 ? (
                  <TagList tags={customer.tags} />
                ) : (
                  <span className="text-sm text-muted-foreground">{t('profile.noTags')}</span>
                )}
              </dd>
            </div>
          </dl>
        </CardContent>
      </Card>

      <div className="grid items-start gap-6 lg:grid-cols-5">
        <div className="lg:col-span-3">
          <AddressesCard customer={customer} canManage={canManage} />
        </div>
        <div className="lg:col-span-2">
          <NotesCard customerId={customer.id} canManage={canManage} />
        </div>
      </div>

      <CustomerFormDialog open={editing} customer={customer} onOpenChange={setEditing} />
      <ConfirmDeleteDialog
        name={deleting ? customer.name : null}
        description={t('deleteDescription')}
        pending={deleteCustomer.isPending}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(false)}
      />
    </div>
  )
}

function ContactItem({
  label,
  icon: Icon,
  children,
}: {
  label: string
  icon: typeof MailIcon
  children: React.ReactNode
}) {
  return (
    <div className="flex flex-col gap-1.5">
      <dt className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
        <Icon className="size-3.5" aria-hidden="true" />
        {label}
      </dt>
      <dd className="text-sm">{children}</dd>
    </div>
  )
}
