import { zodResolver } from '@hookform/resolvers/zod'
import { ImageUpIcon, TriangleAlertIcon, XIcon } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
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
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { LogoTile } from '@/components/media/logo-tile'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import { LOGO_TYPES } from '@/lib/logo-file'
import i18n from '@/lib/i18n'
import { useSaveBrand } from '../hooks/use-brands'
import {
  type BrandFormValues,
  brandFormValues,
  brandSchema,
  toBrandInput,
  toLogoChange,
} from '../schemas/brand-schema'
import type { Brand } from '../types'

const FIELDS = ['name', 'slug'] as const

type BrandFormDialogProps = {
  open: boolean
  /** The Brand to edit, or null to create one. */
  brand: Brand | null
  onOpenChange: (open: boolean) => void
}

/** Creates or edits a Brand: name, optional slug, and an optional logo. */
export function BrandFormDialog({ open, brand, onOpenChange }: BrandFormDialogProps) {
  const { t } = useTranslation('catalog')
  const dir = useDirection()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        dir={dir}
        className="max-h-[90svh] overflow-y-auto sm:max-w-lg"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>{brand ? t('brands.editTitle') : t('brands.newTitle')}</DialogTitle>
          <DialogDescription>{t('brands.formDescription')}</DialogDescription>
        </DialogHeader>
        {open && <BrandForm brand={brand} onDone={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  )
}

function BrandForm({ brand, onDone }: { brand: Brand | null; onDone: () => void }) {
  const { t } = useTranslation('catalog')
  const save = useSaveBrand()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<BrandFormValues>({
    resolver: zodResolver(brandSchema),
    defaultValues: brandFormValues(brand),
  })

  const submit = async (values: BrandFormValues) => {
    setFormError(null)
    try {
      const { logoError } = await save.mutateAsync({
        id: brand?.id ?? null,
        input: toBrandInput(values),
        logo: toLogoChange(values, brand),
      })
      // i18n.t, not t: the language may have switched while the request ran.
      if (logoError) {
        toast.error(i18n.t('catalog:brands.logoNotSaved', { reason: apiErrorMessage(logoError) }))
      } else {
        toast.success(i18n.t(brand ? 'catalog:brands.saved' : 'catalog:brands.created'))
      }
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
            idPrefix="brand"
            label={t('fields.name')}
            maxLength={120}
            autoComplete="off"
          />
          <TextField
            control={form.control}
            name="slug"
            idPrefix="brand"
            label={t('fields.slug')}
            description={t('fields.slugHint')}
            maxLength={140}
            autoComplete="off"
            dir="ltr"
            className="text-start"
          />
          <LogoField form={form} currentLogo={brand?.logo_url ?? null} />
        </FieldGroup>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onDone}>
            {t('common:actions.cancel')}
          </Button>
          <Button type="submit" className="gap-2">
            {busy && <InlineSpinner className="size-4" />}
            {brand ? t('common:actions.saveChanges') : t('brands.create')}
          </Button>
        </DialogFooter>
      </fieldset>
    </form>
  )
}

/** A preview of the logo the Brand will have, and buttons to choose or remove one. */
function LogoField({
  form,
  currentLogo,
}: {
  form: ReturnType<typeof useForm<BrandFormValues>>
  currentLogo: string | null
}) {
  const { t } = useTranslation('catalog')
  const input = useRef<HTMLInputElement>(null)
  const [name, logo, removeLogo] = useWatch({
    control: form.control,
    name: ['name', 'logo', 'removeLogo'],
  })
  const preview = useObjectUrl(logo)
  const shown = preview ?? (removeLogo ? null : currentLogo)

  return (
    <Controller
      name="logo"
      control={form.control}
      render={({ field, fieldState }) => (
        <Field data-invalid={fieldState.invalid}>
          <FieldLabel htmlFor="brand-logo">{t('brands.logo')}</FieldLabel>
          <div className="flex items-center gap-4">
            <LogoTile
              name={name}
              src={shown}
              alt={t('brands.logoOf', { name })}
              className="size-16 rounded-2xl text-base"
            />
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="gap-2"
                onClick={() => input.current?.click()}
              >
                <ImageUpIcon className="size-4" aria-hidden="true" />
                {shown ? t('brands.replaceLogo') : t('brands.chooseLogo')}
              </Button>
              {shown && (
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  className="gap-2"
                  onClick={() => {
                    field.onChange(null)
                    form.setValue('removeLogo', true)
                    if (input.current) input.current.value = ''
                  }}
                >
                  <XIcon className="size-4" aria-hidden="true" />
                  {t('brands.removeLogo')}
                </Button>
              )}
            </div>
          </div>
          <input
            ref={input}
            id="brand-logo"
            type="file"
            accept={LOGO_TYPES.join(',')}
            tabIndex={-1}
            className="sr-only"
            aria-invalid={fieldState.invalid}
            aria-describedby={fieldState.error ? 'brand-logo-error' : 'brand-logo-description'}
            onChange={(event) => {
              field.onChange(event.target.files?.[0] ?? null)
              form.setValue('removeLogo', false)
              void form.trigger('logo')
            }}
          />
          <FieldDescription id="brand-logo-description">{t('brands.logoHint')}</FieldDescription>
          <FieldError id="brand-logo-error">
            {fieldState.error?.message && t(fieldState.error.message as never)}
          </FieldError>
        </Field>
      )}
    />
  )
}

/** A temporary URL for previewing a chosen file, released when it changes. */
function useObjectUrl(file: File | null): string | null {
  const url = useMemo(() => (file ? URL.createObjectURL(file) : null), [file])
  useEffect(
    () => () => {
      if (url) URL.revokeObjectURL(url)
    },
    [url],
  )
  return url
}
