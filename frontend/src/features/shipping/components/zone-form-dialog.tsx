import { zodResolver } from '@hookform/resolvers/zod'
import { PlusIcon, TriangleAlertIcon, XIcon } from 'lucide-react'
import { useState } from 'react'
import {
  Controller,
  type FieldPath,
  type UseFormReturn,
  useFieldArray,
  useForm,
} from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { CountrySelect } from '@/components/forms/country-select'
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
  FieldDescription,
  FieldError,
  FieldGroup,
  FieldLegend,
  FieldSet,
} from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useSaveShippingZone } from '../hooks/use-shipping-zones'
import {
  EMPTY_LOCATION,
  type ZoneFormValues,
  toZoneInput,
  zoneFormValues,
  zoneSchema,
} from '../schemas/shipping-schemas'
import type { ShippingZone } from '../types'

type ZoneFormDialogProps = {
  open: boolean
  /** The zone to edit, or null to create one. */
  zone: ShippingZone | null
  onOpenChange: (open: boolean) => void
  onSaved?: (zone: ShippingZone) => void
}

/** Creates or edits a Shipping zone: its name and the countries or cities it covers. */
export function ZoneFormDialog({ open, zone, onOpenChange, onSaved }: ZoneFormDialogProps) {
  const { t } = useTranslation('shipping')
  const dir = useDirection()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        dir={dir}
        className="max-h-[90svh] overflow-y-auto sm:max-w-xl"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>{zone ? t('zones.editTitle') : t('zones.newTitle')}</DialogTitle>
          <DialogDescription>{t('zones.formDescription')}</DialogDescription>
        </DialogHeader>
        {open && (
          <ZoneForm
            zone={zone}
            onDone={(saved) => {
              onOpenChange(false)
              if (saved) onSaved?.(saved)
            }}
          />
        )}
      </DialogContent>
    </Dialog>
  )
}

function ZoneForm({
  zone,
  onDone,
}: {
  zone: ShippingZone | null
  onDone: (saved?: ShippingZone) => void
}) {
  const { t } = useTranslation('shipping')
  const save = useSaveShippingZone()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<ZoneFormValues>({
    resolver: zodResolver(zoneSchema),
    defaultValues: zoneFormValues(zone),
  })

  const submit = async (values: ZoneFormValues) => {
    setFormError(null)
    // The API names a refused place by its row ("locations.2").
    const fields: FieldPath<ZoneFormValues>[] = [
      'name',
      'locations',
      ...values.locations.map((_, index) => `locations.${index}` as const),
    ]
    try {
      const saved = await save.mutateAsync({ id: zone?.id ?? null, input: toZoneInput(values) })
      // i18n.t, not t: the language may have switched while the request ran.
      toast.success(i18n.t(zone ? 'shipping:zones.saved' : 'shipping:zones.created'))
      onDone(saved)
    } catch (error) {
      if (!showServerErrors(form.setError, error, fields)) setFormError(apiErrorMessage(error))
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
        <FieldGroup className="gap-5">
          <TextField
            control={form.control}
            name="name"
            idPrefix="zone"
            label={t('fields.name')}
            maxLength={120}
            autoComplete="off"
          />
          <PlacesField form={form} />
        </FieldGroup>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onDone()}>
            {t('common:actions.cancel')}
          </Button>
          <Button type="submit" className="gap-2">
            {busy && <InlineSpinner className="size-4" />}
            {zone ? t('common:actions.saveChanges') : t('zones.create')}
          </Button>
        </DialogFooter>
      </fieldset>
    </form>
  )
}

/** One row per place: a country and, optionally, a city in it. */
function PlacesField({ form }: { form: UseFormReturn<ZoneFormValues> }) {
  const { t } = useTranslation('shipping')
  const places = useFieldArray({ control: form.control, name: 'locations' })
  const errors = form.formState.errors.locations
  const message = (text: string | undefined, type: string | undefined) =>
    text && (type === 'server' ? text : t(text as never))

  return (
    <FieldSet>
      <FieldLegend variant="label">{t('zones.places')}</FieldLegend>
      <FieldDescription>{t('zones.placesHint')}</FieldDescription>
      <ul className="flex flex-col gap-3">
        {places.fields.map((place, index) => {
          const rowError = errors?.[index]
          const countryError = rowError?.country_code
          const cityError = rowError?.city
          const rowMessage =
            message(rowError?.message, rowError?.type) ??
            message(countryError?.message, countryError?.type) ??
            message(cityError?.message, cityError?.type)
          return (
            <li key={place.id} className="flex flex-col gap-1.5" data-place-row>
              <div className="flex items-start gap-2">
                <div className="grid flex-1 gap-2 sm:grid-cols-2">
                  <Controller
                    control={form.control}
                    name={`locations.${index}.country_code`}
                    render={({ field }) => (
                      <CountrySelect
                        {...field}
                        aria-label={t('fields.country')}
                        aria-invalid={Boolean(rowError?.message || countryError)}
                        placeholder={t('zones.chooseCountry')}
                        className="[&_select]:bg-background"
                      />
                    )}
                  />
                  <Controller
                    control={form.control}
                    name={`locations.${index}.city`}
                    render={({ field }) => (
                      <Input
                        {...field}
                        aria-label={t('fields.city')}
                        aria-invalid={Boolean(cityError)}
                        placeholder={t('zones.wholeCountry')}
                        maxLength={120}
                        autoComplete="off"
                        dir="auto"
                        className="h-10 rounded-lg bg-background"
                      />
                    )}
                  />
                </div>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="mt-0.5 shrink-0"
                  disabled={places.fields.length === 1}
                  aria-label={t('zones.removePlace', { number: index + 1 })}
                  onClick={() => places.remove(index)}
                >
                  <XIcon className="size-4" aria-hidden="true" />
                </Button>
              </div>
              {rowMessage && <FieldError>{rowMessage}</FieldError>}
            </li>
          )
        })}
      </ul>
      <FieldError>{message(errors?.message ?? errors?.root?.message, errors?.type)}</FieldError>
      <Button
        type="button"
        variant="outline"
        size="sm"
        className="gap-2 self-start"
        onClick={() => places.append(EMPTY_LOCATION)}
      >
        <PlusIcon className="size-4" aria-hidden="true" />
        {t('zones.addPlace')}
      </Button>
    </FieldSet>
  )
}
