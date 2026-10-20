import { zodResolver } from '@hookform/resolvers/zod'
import { TriangleAlertIcon } from 'lucide-react'
import { useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { TextField } from '@/components/forms/form-fields'
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
import {
  Field,
  FieldContent,
  FieldDescription,
  FieldGroup,
  FieldLabel,
} from '@/components/ui/field'
import { Switch } from '@/components/ui/switch'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useSaveShippingMethod } from '../hooks/use-shipping-zones'
import {
  type MethodFormValues,
  methodFormValues,
  methodSchema,
  toMethodInput,
} from '../schemas/shipping-schemas'
import type { ShippingMethod } from '../types'

const FIELDS = ['name', 'price', 'min_delivery_days', 'max_delivery_days', 'is_active'] as const

type MethodFormDialogProps = {
  zoneId: number
  /** The method being edited, null to add one, undefined when the dialog is closed. */
  method: ShippingMethod | null | undefined
  onOpenChange: (open: boolean) => void
}

/** Adds a Shipping method to a zone or edits one: name, flat price, delivery days, active. */
export function MethodFormDialog({ zoneId, method, onOpenChange }: MethodFormDialogProps) {
  const { t } = useTranslation('shipping')
  const dir = useDirection()
  const open = method !== undefined

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        dir={dir}
        className="max-h-[90svh] overflow-y-auto sm:max-w-lg"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>{method ? t('methods.editTitle') : t('methods.newTitle')}</DialogTitle>
          <DialogDescription>{t('methods.formDescription')}</DialogDescription>
        </DialogHeader>
        {open && <MethodForm zoneId={zoneId} method={method} onDone={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  )
}

function MethodForm({
  zoneId,
  method,
  onDone,
}: {
  zoneId: number
  method: ShippingMethod | null
  onDone: () => void
}) {
  const { t } = useTranslation('shipping')
  const save = useSaveShippingMethod()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<MethodFormValues>({
    resolver: zodResolver(methodSchema),
    defaultValues: methodFormValues(method),
  })

  const submit = async (values: MethodFormValues) => {
    setFormError(null)
    try {
      await save.mutateAsync({ zoneId, id: method?.id ?? null, input: toMethodInput(values) })
      toast.success(i18n.t(method ? 'shipping:methods.saved' : 'shipping:methods.created'))
      onDone()
    } catch (error) {
      if (!showServerErrors(form.setError, error, FIELDS)) setFormError(apiErrorMessage(error))
    }
  }

  const busy = save.isPending

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
          <TextField
            control={form.control}
            name="name"
            idPrefix="method"
            label={t('fields.name')}
            maxLength={120}
            autoComplete="off"
          />
          <TextField
            control={form.control}
            name="price"
            idPrefix="method"
            label={t('fields.price')}
            inputMode="decimal"
            autoComplete="off"
            dir="ltr"
            className="text-start tabular-nums"
          />
          <div className="grid gap-4 sm:grid-cols-2">
            <TextField
              control={form.control}
              name="min_delivery_days"
              idPrefix="method"
              label={t('methods.minDays')}
              description={t('methods.daysHint')}
              inputMode="numeric"
              autoComplete="off"
              className="tabular-nums"
            />
            <TextField
              control={form.control}
              name="max_delivery_days"
              idPrefix="method"
              label={t('methods.maxDays')}
              inputMode="numeric"
              autoComplete="off"
              className="tabular-nums"
            />
          </div>
          <Controller
            control={form.control}
            name="is_active"
            render={({ field }) => (
              <Field orientation="horizontal" className="rounded-lg border p-3">
                <FieldContent>
                  <FieldLabel htmlFor="method-is_active">{t('methods.active')}</FieldLabel>
                  <FieldDescription>{t('methods.activeHint')}</FieldDescription>
                </FieldContent>
                <Switch
                  id="method-is_active"
                  checked={field.value}
                  onCheckedChange={field.onChange}
                  onBlur={field.onBlur}
                />
              </Field>
            )}
          />
        </FieldGroup>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onDone}>
            {t('common:actions.cancel')}
          </Button>
          <Button type="submit" className="gap-2">
            {busy && <InlineSpinner className="size-4" />}
            {method ? t('common:actions.saveChanges') : t('methods.create')}
          </Button>
        </DialogFooter>
      </fieldset>
    </form>
  )
}
