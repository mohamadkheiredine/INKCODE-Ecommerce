import { zodResolver } from '@hookform/resolvers/zod'
import { TriangleAlertIcon } from 'lucide-react'
import { useState } from 'react'
import { Controller, type FieldPath, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { SelectField, TextField } from '@/components/forms/form-fields'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useDirection } from '@/components/ui/direction'
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field'
import { useCountryOptions } from '@/lib/countries'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useSaveAddress } from '../hooks/use-customers'
import {
  type AddressFormValues,
  addressFormValues,
  addressSchema,
  toAddressInput,
} from '../schemas/address-schema'
import type { AddressPurpose, CustomerAddress } from '../types'

const FIELDS = [
  'label',
  'recipient_name',
  'phone',
  'country_code',
  'city',
  'line1',
  'line2',
  'postal_code',
  'is_default_billing',
  'is_default_shipping',
] as const satisfies readonly FieldPath<AddressFormValues>[]

type AddressFormDialogProps = {
  customerId: number
  /** The address to edit, `'new'` to add one, or null when closed. */
  address: CustomerAddress | 'new' | null
  /** Whether the Customer has no address yet (the first one becomes both defaults). */
  isFirst: boolean
  /** The country a new address starts in. */
  defaultCountry: string
  onClose: () => void
}

/** Adds or edits one of a Customer's addresses, optionally making it a default. */
export function AddressFormDialog({
  customerId,
  address,
  isFirst,
  defaultCountry,
  onClose,
}: AddressFormDialogProps) {
  const { t } = useTranslation('customers')
  const dir = useDirection()
  const editing = address !== 'new' && address !== null ? address : null

  return (
    <Dialog open={address !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent
        dir={dir}
        className="max-h-[90svh] overflow-y-auto sm:max-w-xl"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>{editing ? t('addresses.editTitle') : t('addresses.newTitle')}</DialogTitle>
          <DialogDescription>
            {isFirst ? t('addresses.firstDescription') : t('addresses.formDescription')}
          </DialogDescription>
        </DialogHeader>
        {address !== null && (
          <AddressForm
            customerId={customerId}
            address={editing}
            isFirst={isFirst}
            defaultCountry={defaultCountry}
            onDone={onClose}
          />
        )}
      </DialogContent>
    </Dialog>
  )
}

function AddressForm({
  customerId,
  address,
  isFirst,
  defaultCountry,
  onDone,
}: {
  customerId: number
  address: CustomerAddress | null
  isFirst: boolean
  defaultCountry: string
  onDone: () => void
}) {
  const { t } = useTranslation('customers')
  const save = useSaveAddress(customerId)
  const countries = useCountryOptions()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<AddressFormValues>({
    resolver: zodResolver(addressSchema),
    defaultValues: addressFormValues(address, defaultCountry),
  })

  const submit = async (values: AddressFormValues) => {
    setFormError(null)
    try {
      await save.mutateAsync({ id: address?.id ?? null, input: toAddressInput(values) })
      toast.success(i18n.t(address ? 'customers:addresses.saved' : 'customers:addresses.added'))
      onDone()
    } catch (error) {
      if (!showServerErrors(form.setError, error, FIELDS)) setFormError(apiErrorMessage(error))
    }
  }

  const busy = save.isPending
  const text = (name: FieldPath<AddressFormValues>, max: number, ltr = false) => (
    <TextField
      control={form.control}
      name={name}
      idPrefix="address"
      label={t(`addresses.fields.${name}` as never)}
      maxLength={max}
      autoComplete="off"
      {...(ltr && { dir: 'ltr', className: 'text-start' })}
    />
  )

  return (
    <form noValidate onSubmit={form.handleSubmit(submit)} className="flex flex-col gap-6">
      {formError && (
        <Alert variant="destructive" role="alert">
          <TriangleAlertIcon aria-hidden="true" />
          <AlertDescription>{formError}</AlertDescription>
        </Alert>
      )}
      <fieldset disabled={busy} className="contents">
        <FieldGroup className="gap-4">
          <div className="grid gap-4 sm:grid-cols-2">
            {text('label', 50)}
            {text('recipient_name', 120)}
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <SelectField
              control={form.control}
              name="country_code"
              idPrefix="address"
              label={t('addresses.fields.country_code')}
              options={[{ value: '', label: t('addresses.chooseCountry') }, ...countries]}
            />
            {text('city', 100)}
          </div>
          {text('line1', 150)}
          {text('line2', 150)}
          <div className="grid gap-4 sm:grid-cols-2">
            {text('postal_code', 20, true)}
            {text('phone', 30, true)}
          </div>
          {!isFirst && (
            <div className="flex flex-col gap-3 rounded-lg border bg-muted/40 p-3">
              <DefaultCheckbox form={form} purpose="billing" address={address} />
              <DefaultCheckbox form={form} purpose="shipping" address={address} />
            </div>
          )}
        </FieldGroup>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onDone}>
            {t('common:actions.cancel')}
          </Button>
          <Button type="submit" className="gap-2">
            {busy && <InlineSpinner className="size-4" />}
            {address ? t('common:actions.saveChanges') : t('addresses.add')}
          </Button>
        </DialogFooter>
      </fieldset>
    </form>
  )
}

/**
 * "Use as the default billing address". An address that already is the default stays
 * ticked: the default moves by choosing another address, never by switching it off.
 */
function DefaultCheckbox({
  form,
  purpose,
  address,
}: {
  form: ReturnType<typeof useForm<AddressFormValues>>
  purpose: AddressPurpose
  address: CustomerAddress | null
}) {
  const { t } = useTranslation('customers')
  const name = `is_default_${purpose}` as const
  const isCurrentDefault = address?.[name] ?? false
  const id = `address-${name}`

  return (
    <Controller
      name={name}
      control={form.control}
      render={({ field }) => (
        <Field orientation="horizontal">
          <Checkbox
            id={id}
            checked={field.value}
            disabled={isCurrentDefault}
            onCheckedChange={(checked) => field.onChange(checked === true)}
            aria-describedby={isCurrentDefault ? `${id}-description` : undefined}
          />
          <div className="flex flex-col gap-0.5">
            <FieldLabel htmlFor={id} className="font-normal">
              {t(`addresses.useAsDefault.${purpose}`)}
            </FieldLabel>
            {isCurrentDefault && (
              <FieldDescription id={`${id}-description`}>
                {t('addresses.keepsDefault')}
              </FieldDescription>
            )}
          </div>
        </Field>
      )}
    />
  )
}
