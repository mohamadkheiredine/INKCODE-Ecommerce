import { zodResolver } from '@hookform/resolvers/zod'
import { TriangleAlertIcon } from 'lucide-react'
import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
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
import { FieldGroup } from '@/components/ui/field'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useSaveCarrier } from '../hooks/use-carriers'
import {
  type CarrierFormValues,
  carrierFormValues,
  carrierSchema,
  isTrackingTemplate,
  trackingUrl,
} from '../schemas/shipping-schemas'
import type { Carrier } from '../types'

const FIELDS = ['name', 'tracking_url_template'] as const

/** A tracking number to show what the link will look like. */
const EXAMPLE_TRACKING_NUMBER = '1234567890'

type CarrierFormDialogProps = {
  open: boolean
  /** The Carrier to edit, or null to create one. */
  carrier: Carrier | null
  onOpenChange: (open: boolean) => void
}

/** Creates or edits a Carrier: its name and an optional tracking link template. */
export function CarrierFormDialog({ open, carrier, onOpenChange }: CarrierFormDialogProps) {
  const { t } = useTranslation('shipping')
  const dir = useDirection()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        dir={dir}
        className="max-h-[90svh] overflow-y-auto sm:max-w-lg"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>{carrier ? t('carriers.editTitle') : t('carriers.newTitle')}</DialogTitle>
          <DialogDescription>{t('carriers.formDescription')}</DialogDescription>
        </DialogHeader>
        {open && <CarrierForm carrier={carrier} onDone={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  )
}

function CarrierForm({ carrier, onDone }: { carrier: Carrier | null; onDone: () => void }) {
  const { t } = useTranslation('shipping')
  const save = useSaveCarrier()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<CarrierFormValues>({
    resolver: zodResolver(carrierSchema),
    defaultValues: carrierFormValues(carrier),
  })
  const template = useWatch({ control: form.control, name: 'tracking_url_template' }).trim()

  const submit = async (values: CarrierFormValues) => {
    setFormError(null)
    try {
      await save.mutateAsync({ id: carrier?.id ?? null, input: values })
      toast.success(i18n.t(carrier ? 'shipping:carriers.saved' : 'shipping:carriers.created'))
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
            idPrefix="carrier"
            label={t('fields.name')}
            maxLength={120}
            autoComplete="off"
          />
          <TextField
            control={form.control}
            name="tracking_url_template"
            idPrefix="carrier"
            label={t('fields.trackingLink')}
            description={
              isTrackingTemplate(template) ? (
                <span className="break-all">
                  {t('carriers.example', {
                    url: trackingUrl(template, EXAMPLE_TRACKING_NUMBER),
                  })}
                </span>
              ) : (
                t('carriers.trackingHint')
              )
            }
            placeholder="https://…/track?id={tracking_number}"
            maxLength={500}
            inputMode="url"
            autoComplete="off"
            dir="ltr"
            className="text-start"
          />
        </FieldGroup>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onDone}>
            {t('common:actions.cancel')}
          </Button>
          <Button type="submit" className="gap-2">
            {busy && <InlineSpinner className="size-4" />}
            {carrier ? t('common:actions.saveChanges') : t('carriers.create')}
          </Button>
        </DialogFooter>
      </fieldset>
    </form>
  )
}
