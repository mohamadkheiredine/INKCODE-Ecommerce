import { CheckCircle2Icon, ExternalLinkIcon, PackageIcon, TruckIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { EmptyState } from '@/components/feedback/empty-state'
import { ErrorState } from '@/components/feedback/error-state'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useDirection } from '@/components/ui/direction'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import { Skeleton } from '@/components/ui/skeleton'
import { useCan } from '@/features/auth/hooks/use-can'
import { isApiError } from '@/lib/api/api-error'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { cn } from '@/lib/utils'
import { useDeliverShipment, useShipOrder, useShipmentCarriers } from '../hooks/use-orders'
import type { Fulfillment, Order, Shipment } from '../types'

/** Each Shipment progress's tint, from theme tokens only. */
const FULFILLMENT_TONES: Record<Fulfillment['status'], string> = {
  unfulfilled: 'border-border bg-muted text-muted-foreground',
  partially_shipped: 'border-chart-2/40 bg-chart-2/15 text-foreground',
  shipped: 'border-chart-3/40 bg-chart-3/15 text-foreground',
  delivered: 'border-success/40 bg-success/15 text-foreground',
}

/**
 * Shipment progress (separate from the Order status) and the Order's Shipments, each with
 * its Carrier, tracking link, units and status history. Users who manage Shipments ship
 * a Ready for shipping Order (in one or several Shipments) and mark Shipments delivered.
 */
export function ShipmentsCard({ order }: { order: Order }) {
  const { t } = useTranslation('orders')
  const can = useCan()
  const { number } = useFormatters()
  const [shipping, setShipping] = useState(false)
  const shipments = order.shipments ?? []
  const fulfillment = order.fulfillment
  const canShip =
    order.status === 'ready_for_shipping' && can('shipments.manage') && hasUnshippedUnits(order)

  return (
    <Card className="js-reveal rounded-2xl">
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
        <CardTitle className="text-base">{t('shipments.title')}</CardTitle>
        <div className="flex items-center gap-2">
          {fulfillment && (
            <Badge
              variant="outline"
              className={cn('font-medium', FULFILLMENT_TONES[fulfillment.status])}
            >
              {t(`shipments.progress.${fulfillment.status}`)}
            </Badge>
          )}
          {canShip && shipments.length > 0 && (
            <Button size="sm" className="gap-2" onClick={() => setShipping(true)}>
              <TruckIcon className="size-4" aria-hidden="true" />
              {t('shipments.create')}
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {fulfillment && fulfillment.units > 0 && (
          <div className="flex flex-col gap-1.5">
            <div
              role="progressbar"
              aria-label={t('shipments.progressLabel')}
              aria-valuemin={0}
              aria-valuemax={fulfillment.units}
              aria-valuenow={fulfillment.shipped_units}
              className="h-2 overflow-hidden rounded-full bg-muted"
            >
              <div
                className="h-full rounded-full bg-primary transition-[width] duration-500"
                style={{ width: `${(fulfillment.shipped_units / fulfillment.units) * 100}%` }}
              />
            </div>
            <span className="text-xs text-muted-foreground">
              {t('shipments.unitsShipped', {
                shipped: number(fulfillment.shipped_units),
                units: number(fulfillment.units),
              })}
            </span>
          </div>
        )}

        {shipments.length === 0 ? (
          <EmptyState
            icon={PackageIcon}
            title={t('shipments.emptyTitle')}
            description={t(
              order.status === 'ready_for_shipping'
                ? 'shipments.emptyReady'
                : 'shipments.emptyNotReady',
            )}
            action={
              canShip ? (
                <Button className="gap-2" onClick={() => setShipping(true)}>
                  <TruckIcon className="size-4" aria-hidden="true" />
                  {t('shipments.create')}
                </Button>
              ) : null
            }
          />
        ) : (
          <ul className="flex flex-col gap-3">
            {shipments.map((shipment) => (
              <ShipmentItem key={shipment.id} order={order} shipment={shipment} />
            ))}
          </ul>
        )}
      </CardContent>
      {canShip && (
        <CreateShipmentDialog order={order} open={shipping} onClose={() => setShipping(false)} />
      )}
    </Card>
  )
}

function hasUnshippedUnits(order: Order) {
  return (order.lines ?? []).some((line) => line.quantity > line.shipped_quantity)
}

function ShipmentItem({ order, shipment }: { order: Order; shipment: Shipment }) {
  const { t } = useTranslation('orders')
  const can = useCan()
  const { number, dateTime } = useFormatters()
  const deliver = useDeliverShipment()
  const [deliverKey, setDeliverKey] = useState(() => crypto.randomUUID())
  const lines = new Map((order.lines ?? []).map((line) => [line.id, line]))

  const markDelivered = async () => {
    try {
      await deliver.mutateAsync({
        id: order.id,
        shipmentId: shipment.id,
        idempotencyKey: deliverKey,
      })
      toast.success(i18n.t('orders:shipments.delivered', { number: shipment.number }))
    } catch (error) {
      toast.error(apiErrorMessage(error))
    } finally {
      setDeliverKey(crypto.randomUUID())
    }
  }

  return (
    <li className="flex flex-col gap-3 rounded-xl border p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex min-w-0 flex-col gap-0.5">
          <span dir="ltr" className="text-start font-mono text-sm font-medium">
            {shipment.number}
          </span>
          <span className="text-xs text-muted-foreground">
            <span dir="auto">{shipment.carrier.name}</span> ·{' '}
            {shipment.tracking_url ? (
              <a
                href={shipment.tracking_url}
                target="_blank"
                rel="noreferrer noopener"
                className="inline-flex items-center gap-1 font-mono text-primary hover:underline"
                dir="ltr"
              >
                {shipment.tracking_number}
                <ExternalLinkIcon className="size-3" aria-hidden="true" />
                <span className="sr-only">{t('shipments.track')}</span>
              </a>
            ) : (
              <span dir="ltr" className="font-mono">
                {shipment.tracking_number}
              </span>
            )}
          </span>
        </div>
        <div className="flex items-center gap-2">
          <Badge
            variant="outline"
            className={cn(
              'font-medium',
              FULFILLMENT_TONES[shipment.status === 'delivered' ? 'delivered' : 'shipped'],
            )}
          >
            {t(`shipments.statuses.${shipment.status}`)}
          </Badge>
          {shipment.status === 'shipped' && can('shipments.manage') && (
            <Button
              size="sm"
              variant="outline"
              className="gap-2"
              disabled={deliver.isPending}
              onClick={() => void markDelivered()}
            >
              {deliver.isPending ? (
                <InlineSpinner className="size-4" />
              ) : (
                <CheckCircle2Icon className="size-4" aria-hidden="true" />
              )}
              {t('shipments.markDelivered')}
            </Button>
          )}
        </div>
      </div>
      <ul className="flex flex-col gap-1 text-sm">
        {shipment.lines.map((shipped) => {
          const line = lines.get(shipped.order_line_id)
          return (
            <li key={shipped.order_line_id} className="flex justify-between gap-3">
              <span dir="auto" className="truncate">
                {line?.product_name}{' '}
                <span dir="ltr" className="font-mono text-xs text-muted-foreground">
                  {line?.sku}
                </span>
              </span>
              <span className="tabular-nums">× {number(shipped.quantity)}</span>
            </li>
          )
        })}
      </ul>
      <ol className="flex flex-col gap-1 border-t pt-2 text-xs text-muted-foreground">
        {shipment.status_history.map((change) => (
          <li key={change.id}>
            {t('shipments.history', {
              status: t(`shipments.statuses.${change.to_status}`),
              user: change.user?.name ?? t('system'),
              when: dateTime(change.created_at),
            })}
          </li>
        ))}
      </ol>
    </li>
  )
}

/**
 * A new Shipment: its Carrier, tracking number and how many units of each line it carries
 * (every unit left to ship by default). Server refusals (422 per field, 409) show inline.
 */
function CreateShipmentDialog({
  order,
  open,
  onClose,
}: {
  order: Order
  open: boolean
  onClose: () => void
}) {
  const { t } = useTranslation('orders')
  const dir = useDirection()
  const { number } = useFormatters()
  const carriers = useShipmentCarriers(open)
  const ship = useShipOrder()
  const lines = (order.lines ?? []).filter((line) => line.quantity > line.shipped_quantity)
  const unshipped = (lineId: number) => {
    const line = lines.find((item) => item.id === lineId)
    return line ? line.quantity - line.shipped_quantity : 0
  }
  const [carrierId, setCarrierId] = useState('')
  const [tracking, setTracking] = useState('')
  const [quantities, setQuantities] = useState<Record<number, string>>({})
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID())

  const close = () => {
    setCarrierId('')
    setTracking('')
    setQuantities({})
    setErrors({})
    onClose()
  }

  const quantityOf = (lineId: number) => quantities[lineId] ?? String(unshipped(lineId))
  const chosen = lines
    .map((line) => ({ line_id: line.id, quantity: Number(quantityOf(line.id) || 0) }))
    .filter((line) => line.quantity > 0)
  const invalidQuantity = (lineId: number) => {
    const value = quantityOf(lineId).trim()
    return value !== '' && (!/^\d+$/.test(value) || Number(value) > unshipped(lineId))
  }

  const submit = async () => {
    const found: Record<string, string> = {}
    if (carrierId === '') found.carrier_id = t('shipments.validation.carrier')
    if (tracking.trim() === '') found.tracking_number = t('shipments.validation.tracking')
    if (chosen.length === 0 || lines.some((line) => invalidQuantity(line.id))) {
      found.lines = t('shipments.validation.quantities')
    }
    setErrors(found)
    if (Object.keys(found).length > 0) return

    try {
      const updated = await ship.mutateAsync({
        id: order.id,
        input: { carrier_id: Number(carrierId), tracking_number: tracking.trim(), lines: chosen },
        idempotencyKey,
      })
      toast.success(
        i18n.t(
          updated.status === 'shipped' ? 'orders:shipments.doneShipped' : 'orders:shipments.done',
          { number: updated.number },
        ),
      )
      setIdempotencyKey(crypto.randomUUID())
      close()
    } catch (refusal) {
      setIdempotencyKey(crypto.randomUUID())
      if (isApiError(refusal) && refusal.status === 422) {
        setErrors({
          carrier_id: refusal.fieldError('carrier_id') ?? '',
          tracking_number: refusal.fieldError('tracking_number') ?? '',
          lines:
            chosen.map((_, index) => refusal.fieldError(`lines.${index}.quantity`)).find(Boolean) ??
            refusal.fieldError('lines') ??
            '',
        })
      } else {
        setErrors({ form: apiErrorMessage(refusal) })
      }
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !next && !ship.isPending && close()}>
      <DialogContent dir={dir} className="sm:max-w-lg" data-lenis-prevent>
        <DialogHeader>
          <DialogTitle>{t('shipments.dialogTitle', { number: order.number })}</DialogTitle>
          <DialogDescription>{t('shipments.dialogDescription')}</DialogDescription>
        </DialogHeader>
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-2">
            <Label htmlFor="shipment-carrier">{t('shipments.carrier')}</Label>
            {carriers.isPending ? (
              <Skeleton className="h-9 w-full" />
            ) : carriers.isError ? (
              <ErrorState
                title={t('shipments.carriersError')}
                onRetry={() => void carriers.refetch()}
              />
            ) : (
              <NativeSelect
                id="shipment-carrier"
                className="w-full"
                value={carrierId}
                aria-invalid={Boolean(errors.carrier_id)}
                onChange={(event) => setCarrierId(event.target.value)}
              >
                <NativeSelectOption value="">{t('shipments.chooseCarrier')}</NativeSelectOption>
                {carriers.data.data.map((carrier) => (
                  <NativeSelectOption key={carrier.id} value={String(carrier.id)}>
                    {carrier.name}
                  </NativeSelectOption>
                ))}
              </NativeSelect>
            )}
            {errors.carrier_id && <p className="text-sm text-destructive">{errors.carrier_id}</p>}
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="shipment-tracking">{t('shipments.trackingNumber')}</Label>
            <Input
              id="shipment-tracking"
              dir="ltr"
              maxLength={100}
              value={tracking}
              aria-invalid={Boolean(errors.tracking_number)}
              onChange={(event) => setTracking(event.target.value)}
            />
            {errors.tracking_number && (
              <p className="text-sm text-destructive">{errors.tracking_number}</p>
            )}
          </div>
        </div>
        <ul className="flex flex-col divide-y rounded-xl border">
          {lines.map((line) => (
            <li key={line.id} className="flex items-center gap-3 p-3">
              <div className="flex min-w-0 flex-1 flex-col">
                <span dir="auto" className="truncate font-medium">
                  {line.product_name}
                </span>
                <span className="text-xs text-muted-foreground">
                  <span dir="ltr" className="font-mono">
                    {line.sku}
                  </span>{' '}
                  ·{' '}
                  {t('shipments.leftToShip', {
                    count: unshipped(line.id),
                    formatted: number(unshipped(line.id)),
                  })}
                </span>
              </div>
              <div className="flex w-24 flex-col gap-1">
                <Label htmlFor={`ship-line-${line.id}`} className="sr-only">
                  {t('shipments.quantityFor', { sku: line.sku })}
                </Label>
                <Input
                  id={`ship-line-${line.id}`}
                  inputMode="numeric"
                  placeholder="0"
                  value={quantityOf(line.id)}
                  aria-invalid={invalidQuantity(line.id)}
                  onChange={(event) =>
                    setQuantities((current) => ({ ...current, [line.id]: event.target.value }))
                  }
                />
              </div>
            </li>
          ))}
        </ul>
        {(errors.lines || errors.form) && (
          <p role="alert" className="text-sm text-destructive">
            {errors.lines || errors.form}
          </p>
        )}
        <DialogFooter>
          <Button variant="outline" disabled={ship.isPending} onClick={close}>
            {t('shipments.keep')}
          </Button>
          <Button className="gap-2" disabled={ship.isPending} onClick={() => void submit()}>
            {ship.isPending ? (
              <InlineSpinner className="size-4" />
            ) : (
              <TruckIcon className="size-4" aria-hidden="true" />
            )}
            {t('shipments.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
