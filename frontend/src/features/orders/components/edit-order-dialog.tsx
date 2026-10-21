import { zodResolver } from '@hookform/resolvers/zod'
import { Trash2Icon } from 'lucide-react'
import { useCallback, useState } from 'react'
import { type FieldPath, useFieldArray, useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { z } from 'zod'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { SelectField, TextField } from '@/components/forms/form-fields'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useDirection } from '@/components/ui/direction'
import { FieldError } from '@/components/ui/field'
import type { CustomerAddress } from '@/features/customers/types'
import { optionsLabel } from '@/features/inventory/types'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useUpdateOrder } from '../hooks/use-orders'
import type { Order, OrderAddress, SellableVariant } from '../types'
import { addressOptions, useCustomerProfile, useVariantSearch } from '../hooks/use-order-form'
import { ShippingMethodField } from './order-form-parts'
import { SearchPicker } from './search-picker'

const QUANTITY = /^[1-9]\d{0,4}$/

/** As UpdateOrderRequest: two of the Customer's addresses, a Shipping method, the lines. */
const editSchema = z.object({
  billing_address_id: z.string().min(1, 'orders:validation.addressRequired'),
  shipping_address_id: z.string().min(1, 'orders:validation.addressRequired'),
  shipping_method_id: z.string().min(1, 'orders:validation.shippingMethodRequired'),
  lines: z
    .array(
      z.object({
        variant_id: z.number(),
        sku: z.string(),
        label: z.string(),
        quantity: z
          .string()
          .trim()
          .regex(QUANTITY, 'orders:validation.quantity')
          .refine((value) => Number(value) <= 10_000, 'orders:validation.quantity'),
      }),
    )
    .min(1, 'orders:validation.noLines'),
})

type EditValues = z.infer<typeof editSchema>

/** The Customer's address the Order's snapshot was taken from, when it still matches one. */
function matchingAddress(addresses: CustomerAddress[], snapshot: OrderAddress) {
  const match = addresses.find(
    (address) =>
      address.line1 === snapshot.line1 &&
      address.city === snapshot.city &&
      address.country_code === snapshot.country_code,
  )
  return match ? String(match.id) : ''
}

/**
 * Changes a Pending or Confirmed Order's lines, quantities, addresses and Shipping method.
 * The API re-reserves the differences of a Confirmed Order, all or nothing.
 */
export function EditOrderDialog({
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
  const addresses = useCustomerProfile(open ? order.customer.id : 0).data?.addresses

  return (
    <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
      <DialogContent
        dir={dir}
        className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>{t('edit.title', { number: order.number })}</DialogTitle>
          <DialogDescription>
            {order.status === 'confirmed' ? t('edit.descriptionReserved') : t('edit.description')}
          </DialogDescription>
        </DialogHeader>
        {addresses ? (
          <EditOrderForm order={order} addresses={addresses} onDone={onClose} />
        ) : (
          <div className="flex justify-center py-8">
            <InlineSpinner className="size-5" />
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}

function EditOrderForm({
  order,
  addresses,
  onDone,
}: {
  order: Order
  addresses: CustomerAddress[]
  onDone: () => void
}) {
  const { t } = useTranslation('orders')
  const update = useUpdateOrder()
  const [formError, setFormError] = useState<string | null>(null)
  const [idempotencyKey, setIdempotencyKey] = useState(() => crypto.randomUUID())
  const form = useForm<EditValues>({
    resolver: zodResolver(editSchema),
    defaultValues: {
      billing_address_id: matchingAddress(addresses, order.billing_address),
      shipping_address_id: matchingAddress(addresses, order.shipping_address),
      shipping_method_id: String(order.shipping_method.id),
      lines: (order.lines ?? [])
        .filter((line) => line.quantity > 0)
        .map((line) => ({
          variant_id: line.variant_id,
          sku: line.sku,
          label:
            line.product_name + (line.options.length > 0 ? ` · ${optionsLabel(line.options)}` : ''),
          quantity: String(line.quantity),
        })),
    },
  })
  const lines = useFieldArray({ control: form.control, name: 'lines', keyName: 'key' })
  const watched = useWatch({ control: form.control })
  const chooseShippingMethod = useCallback(
    (id: string) => form.setValue('shipping_method_id', id),
    [form],
  )

  const addLine = (variant: SellableVariant) => {
    const index = lines.fields.findIndex((line) => line.variant_id === variant.id)
    if (index >= 0) {
      form.setFocus(`lines.${index}.quantity`)
      return
    }
    lines.append({
      variant_id: variant.id,
      sku: variant.sku,
      label:
        variant.product.name +
        (variant.options.length > 0 ? ` · ${optionsLabel(variant.options)}` : ''),
      quantity: '1',
    })
  }

  const submit = form.handleSubmit(async (values) => {
    setFormError(null)
    try {
      const updated = await update.mutateAsync({
        id: order.id,
        idempotencyKey,
        input: {
          billing_address_id: Number(values.billing_address_id),
          shipping_address_id: Number(values.shipping_address_id),
          shipping_method_id: Number(values.shipping_method_id),
          lines: values.lines.map((line) => ({
            variant_id: line.variant_id,
            quantity: Number(line.quantity),
          })),
        },
      })
      toast.success(i18n.t('orders:edit.done', { number: updated.number }))
      onDone()
    } catch (error) {
      setIdempotencyKey(crypto.randomUUID())
      const fields: FieldPath<EditValues>[] = [
        'billing_address_id',
        'shipping_address_id',
        'shipping_method_id',
        'lines',
        ...values.lines.flatMap((_, index) => [
          `lines.${index}.variant_id` as const,
          `lines.${index}.quantity` as const,
        ]),
      ]
      if (!showServerErrors(form.setError, error, fields)) setFormError(apiErrorMessage(error))
    }
  })

  return (
    <form onSubmit={(event) => void submit(event)} noValidate className="flex flex-col gap-4">
      {formError && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{formError}</AlertDescription>
        </Alert>
      )}
      <div className="grid gap-4 sm:grid-cols-2">
        <SelectField
          control={form.control}
          name="shipping_address_id"
          idPrefix="edit-order"
          label={t('fields.shippingAddress')}
          options={addressOptions(addresses, t('chooseAddress'))}
        />
        <SelectField
          control={form.control}
          name="billing_address_id"
          idPrefix="edit-order"
          label={t('fields.billingAddress')}
          options={addressOptions(addresses, t('chooseAddress'))}
        />
        <div className="sm:col-span-2">
          <ShippingMethodField
            customerId={order.customer.id}
            shippingAddressId={watched.shipping_address_id ?? ''}
            current={watched.shipping_method_id ?? ''}
            onDefault={chooseShippingMethod}
            field={(options, hint) => (
              <div className="flex flex-col gap-1">
                <SelectField
                  control={form.control}
                  name="shipping_method_id"
                  idPrefix="edit-order"
                  label={t('fields.shippingMethod')}
                  options={options}
                />
                {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
              </div>
            )}
          />
        </div>
      </div>

      <SearchPicker<SellableVariant>
        label={t('findVariant')}
        placeholder={t('findVariant')}
        noMatches={t('noVariantMatches')}
        useResults={useVariantSearch}
        getKey={(variant) => variant.id}
        getLabel={(variant) => t('addVariant', { sku: variant.sku })}
        renderItem={(variant) => (
          <span className="flex min-w-0 flex-col">
            <span dir="auto" className="truncate text-sm font-medium">
              {variant.product.name}
              {variant.options.length > 0 && ` · ${optionsLabel(variant.options)}`}
            </span>
            <span dir="ltr" className="text-start font-mono text-xs text-muted-foreground">
              {variant.sku}
            </span>
          </span>
        )}
        onPick={addLine}
      />
      <ul className="flex flex-col divide-y rounded-xl border" aria-label={t('lines')}>
        {lines.fields.map((line, index) => (
          <li key={line.key} className="flex items-start gap-3 p-3">
            <div className="flex min-w-0 flex-1 flex-col pt-1">
              <span dir="auto" className="truncate font-medium">
                {line.label}
              </span>
              <span dir="ltr" className="text-start font-mono text-xs text-muted-foreground">
                {line.sku}
              </span>
            </div>
            <div className="w-24">
              <TextField
                control={form.control}
                name={`lines.${index}.quantity`}
                idPrefix="edit-order"
                label={t('fields.quantity')}
                inputMode="numeric"
              />
            </div>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="mt-6"
              aria-label={t('removeLine', { sku: line.sku })}
              onClick={() => lines.remove(index)}
            >
              <Trash2Icon className="size-4" aria-hidden="true" />
            </Button>
          </li>
        ))}
      </ul>
      {form.formState.errors.lines?.root?.message || form.formState.errors.lines?.message ? (
        <FieldError>
          {t(
            (form.formState.errors.lines?.root?.message ??
              form.formState.errors.lines?.message) as string,
          )}
        </FieldError>
      ) : null}

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onDone} disabled={update.isPending}>
          {t('edit.discard')}
        </Button>
        <Button type="submit" disabled={update.isPending} className="gap-2">
          {update.isPending && <InlineSpinner className="size-4" />}
          {t('edit.submit')}
        </Button>
      </DialogFooter>
    </form>
  )
}
