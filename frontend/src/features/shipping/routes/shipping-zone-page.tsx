import { ArrowLeftIcon, PencilIcon, PlusIcon, Trash2Icon, TruckIcon } from 'lucide-react'
import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { toast } from 'sonner'
import { RowActions } from '@/components/data-table/row-actions'
import { ConfirmDeleteDialog } from '@/components/feedback/confirm-delete-dialog'
import { EmptyState } from '@/components/feedback/empty-state'
import { ErrorState } from '@/components/feedback/error-state'
import { PageHeader } from '@/components/layout/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useCan } from '@/features/auth/hooks/use-can'
import { isApiError } from '@/lib/api/api-error'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useEntranceAnimation } from '@/lib/motion'
import { MethodFormDialog } from '../components/method-form-dialog'
import { ZoneCoverage } from '../components/zone-coverage'
import { ZoneFormDialog } from '../components/zone-form-dialog'
import {
  useDeleteShippingMethod,
  useDeleteShippingZone,
  useShippingZone,
} from '../hooks/use-shipping-zones'
import { useDeliveryDays } from '../labels'
import type { ShippingMethod, ShippingZone } from '../types'

/** One Shipping zone: the places it covers and its Shipping methods. */
export function ShippingZonePage() {
  const { t } = useTranslation('shipping')
  const zoneId = Number(useParams().zoneId)
  const zone = useShippingZone(zoneId)

  if (zone.isPending) return <ZoneSkeleton label={t('common:states.loading')} />
  if (zone.isError) {
    if (isApiError(zone.error) && zone.error.status === 404) {
      return (
        <EmptyState
          icon={TruckIcon}
          title={t('common:pages.shippingZone')}
          description={t('zones.notFound')}
          action={<BackLink />}
        />
      )
    }
    return <ErrorState onRetry={() => void zone.refetch()} retrying={zone.isFetching} />
  }
  return <ZoneDetail zone={zone.data} />
}

function BackLink() {
  const { t } = useTranslation('shipping')
  return (
    <Button asChild variant="outline" className="gap-2">
      <Link to="/shipping/zones">
        <ArrowLeftIcon className="size-4 rtl:rotate-180" aria-hidden="true" />
        {t('zones.back')}
      </Link>
    </Button>
  )
}

function ZoneDetail({ zone }: { zone: ShippingZone }) {
  const { t } = useTranslation('shipping')
  const canManage = useCan()('shipping.manage')
  const navigate = useNavigate()
  const deleteZone = useDeleteShippingZone()
  const [editingZone, setEditingZone] = useState(false)
  const [deletingZone, setDeletingZone] = useState(false)
  const container = useRef<HTMLDivElement>(null)

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const confirmDeleteZone = () =>
    deleteZone.mutate(zone.id, {
      onSuccess: () => {
        toast.success(i18n.t('shipping:zones.deleted'))
        void navigate('/shipping/zones')
      },
      onError: (error) => toast.error(apiErrorMessage(error)),
      onSettled: () => setDeletingZone(false),
    })

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader
        title={zone.name}
        description={t('methods.description')}
        actions={
          canManage && (
            <>
              <Button
                type="button"
                variant="outline"
                className="gap-2"
                onClick={() => setEditingZone(true)}
              >
                <PencilIcon className="size-4" aria-hidden="true" />
                {t('common:actions.edit')}
              </Button>
              <Button
                type="button"
                variant="ghost"
                className="gap-2 text-destructive hover:text-destructive"
                onClick={() => setDeletingZone(true)}
              >
                <Trash2Icon className="size-4" aria-hidden="true" />
                {t('common:actions.delete')}
              </Button>
            </>
          )
        }
      />
      <section aria-label={t('fields.coverage')} className="js-reveal">
        <ZoneCoverage locations={zone.locations} />
      </section>
      <MethodsCard zone={zone} canManage={canManage} />

      <ZoneFormDialog open={editingZone} zone={zone} onOpenChange={setEditingZone} />
      <ConfirmDeleteDialog
        name={deletingZone ? zone.name : null}
        description={t('zones.deleteDescription')}
        pending={deleteZone.isPending}
        onConfirm={confirmDeleteZone}
        onCancel={() => setDeletingZone(false)}
      />
    </div>
  )
}

function MethodsCard({ zone, canManage }: { zone: ShippingZone; canManage: boolean }) {
  const { t } = useTranslation('shipping')
  const { money } = useFormatters()
  const deliveryDays = useDeliveryDays()
  const deleteMethod = useDeleteShippingMethod()
  // undefined: closed; null: adding one.
  const [editing, setEditing] = useState<ShippingMethod | null | undefined>(undefined)
  const [deleting, setDeleting] = useState<ShippingMethod | null>(null)
  const methods = zone.methods ?? []

  const confirmDelete = () => {
    if (!deleting) return
    deleteMethod.mutate(deleting.id, {
      onSuccess: () => toast.success(i18n.t('shipping:methods.deleted')),
      onError: (error) => toast.error(apiErrorMessage(error)),
      onSettled: () => setDeleting(null),
    })
  }

  const newButton = (
    <Button type="button" className="gap-2" onClick={() => setEditing(null)}>
      <PlusIcon className="size-4" aria-hidden="true" />
      {t('methods.new')}
    </Button>
  )

  return (
    <Card className="js-reveal gap-4 pb-0">
      <CardHeader>
        <CardTitle>
          <h2 className="text-base font-semibold">{t('methods.title')}</h2>
        </CardTitle>
        <CardDescription>{t('methods.activeHint')}</CardDescription>
        {canManage && methods.length > 0 && <CardAction>{newButton}</CardAction>}
      </CardHeader>
      {methods.length === 0 ? (
        <div className="px-4 pb-4">
          <EmptyState
            icon={TruckIcon}
            title={t('methods.emptyTitle')}
            description={
              canManage ? t('methods.emptyDescription') : t('methods.emptyDescriptionReadOnly')
            }
            action={canManage ? newButton : undefined}
          />
        </div>
      ) : (
        <div className="overflow-x-auto border-t" data-lenis-prevent>
          <Table aria-label={t('methods.title')}>
            <TableHeader>
              <TableRow>
                <TableHead className="ps-6">{t('fields.name')}</TableHead>
                <TableHead>{t('fields.price')}</TableHead>
                <TableHead className="hidden sm:table-cell">{t('fields.delivery')}</TableHead>
                <TableHead>{t('fields.status')}</TableHead>
                {canManage && (
                  <TableHead className="w-12 pe-6 text-end">
                    <span className="sr-only">{t('common:dataTable.actions')}</span>
                  </TableHead>
                )}
              </TableRow>
            </TableHeader>
            <TableBody>
              {methods.map((method) => (
                <TableRow key={method.id}>
                  <TableCell className="ps-6 font-medium" dir="auto">
                    {method.name}
                  </TableCell>
                  <TableCell className="tabular-nums">{money(method.price)}</TableCell>
                  <TableCell className="hidden text-muted-foreground sm:table-cell">
                    {deliveryDays(method.min_delivery_days, method.max_delivery_days)}
                  </TableCell>
                  <TableCell>
                    <Badge variant={method.is_active ? 'secondary' : 'outline'}>
                      {method.is_active ? t('methods.active') : t('methods.inactive')}
                    </Badge>
                  </TableCell>
                  {canManage && (
                    <TableCell className="pe-6 text-end">
                      <RowActions
                        name={method.name}
                        onEdit={() => setEditing(method)}
                        onDelete={() => setDeleting(method)}
                      />
                    </TableCell>
                  )}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      <MethodFormDialog
        zoneId={zone.id}
        method={editing}
        onOpenChange={(open) => !open && setEditing(undefined)}
      />
      <ConfirmDeleteDialog
        name={deleting?.name ?? null}
        description={t('methods.deleteDescription')}
        pending={deleteMethod.isPending}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </Card>
  )
}

function ZoneSkeleton({ label }: { label: string }) {
  return (
    <div className="flex flex-col gap-6" role="status" aria-live="polite">
      <span className="sr-only">{label}</span>
      <div className="flex flex-col gap-2">
        <Skeleton className="h-8 w-56 rounded-lg" />
        <Skeleton className="h-4 w-96 max-w-full rounded-lg" />
      </div>
      <div className="flex gap-2">
        <Skeleton className="h-5 w-20 rounded-full" />
        <Skeleton className="h-5 w-28 rounded-full" />
      </div>
      <Skeleton className="h-64 w-full rounded-xl" />
    </div>
  )
}
