import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowDownIcon, ArrowUpIcon, PlusIcon, TriangleAlertIcon, XIcon } from 'lucide-react'
import { useState } from 'react'
import { type FieldError, useFieldArray, useForm, useWatch } from 'react-hook-form'
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
import { FieldDescription, FieldGroup, FieldLegend, FieldSet } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { isApiError } from '@/lib/api/api-error'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useSaveAttribute } from '../hooks/use-attributes'
import {
  type AttributeFormValues,
  attributeFormValues,
  attributeSchema,
} from '../schemas/attribute-schema'
import { codeFrom } from '../schemas/money'
import type { Attribute } from '../types'

type AttributeFormDialogProps = {
  open: boolean
  /** The Attribute to edit, or null to create one. */
  attribute: Attribute | null
  onOpenChange: (open: boolean) => void
}

/** Creates or edits an Attribute and its whole list of values (name + SKU code each). */
export function AttributeFormDialog({ open, attribute, onOpenChange }: AttributeFormDialogProps) {
  const { t } = useTranslation('catalog')
  const dir = useDirection()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        dir={dir}
        className="max-h-[90svh] overflow-y-auto sm:max-w-xl"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>
            {attribute ? t('attributes.editTitle') : t('attributes.newTitle')}
          </DialogTitle>
          <DialogDescription>{t('attributes.formDescription')}</DialogDescription>
        </DialogHeader>
        {open && <AttributeForm attribute={attribute} onDone={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  )
}

function AttributeForm({ attribute, onDone }: { attribute: Attribute | null; onDone: () => void }) {
  const { t } = useTranslation('catalog')
  const save = useSaveAttribute()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<AttributeFormValues>({
    resolver: zodResolver(attributeSchema),
    defaultValues: attributeFormValues(attribute),
  })
  const values = useFieldArray({ control: form.control, name: 'values', keyName: 'key' })
  const names = useWatch({ control: form.control, name: 'values' }).map((value) => value.name)

  const submit = async (input: AttributeFormValues) => {
    setFormError(null)
    try {
      await save.mutateAsync({ id: attribute?.id ?? null, input })
      toast.success(i18n.t(attribute ? 'catalog:attributes.saved' : 'catalog:attributes.created'))
      onDone()
    } catch (error) {
      const valueFields = input.values.flatMap((_, index) => [
        `values.${index}.name` as const,
        `values.${index}.code` as const,
        `values.${index}.id` as const,
      ])
      // A refused removal (409) is about the list as a whole: show it above the form.
      if (isApiError(error) && error.status === 409) setFormError(error.message)
      else if (!showServerErrors(form.setError, error, ['name', ...valueFields]))
        setFormError(apiErrorMessage(error))
    }
  }

  const fieldMessage = (error: FieldError | undefined) =>
    error?.message && (error.type === 'server' ? error.message : t(error.message as never))

  return (
    <form noValidate onSubmit={form.handleSubmit(submit)} className="flex flex-col gap-6">
      {formError && (
        <Alert variant="destructive" role="alert">
          <TriangleAlertIcon aria-hidden="true" />
          <AlertDescription>{formError}</AlertDescription>
        </Alert>
      )}
      <fieldset disabled={save.isPending} className="contents">
        <FieldGroup className="gap-5">
          <TextField
            control={form.control}
            name="name"
            idPrefix="attribute"
            label={t('fields.name')}
            maxLength={60}
            autoComplete="off"
          />
          <FieldSet className="gap-3">
            <FieldLegend variant="label">{t('attributes.values')}</FieldLegend>
            <FieldDescription>{t('attributes.valuesHint')}</FieldDescription>
            {values.fields.length === 0 && (
              <p className="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
                {t('attributes.noValues')}
              </p>
            )}
            <ol className="flex flex-col gap-2">
              {values.fields.map((field, index) => {
                const errors = form.formState.errors.values?.[index]
                const label = names[index] || t('attributes.unnamedValue')
                return (
                  <li key={field.key} className="flex flex-col gap-1">
                    <div className="flex items-start gap-2">
                      <Input
                        {...form.register(`values.${index}.name`, {
                          onBlur: (event: { target: { value: string } }) => {
                            const code = form.getValues(`values.${index}.code`)
                            if (!code)
                              form.setValue(`values.${index}.code`, codeFrom(event.target.value))
                          },
                        })}
                        aria-label={`${t('attributes.valueName')} ${index + 1}`}
                        aria-invalid={Boolean(errors?.name)}
                        maxLength={60}
                        autoComplete="off"
                        dir="auto"
                        className="h-9 min-w-0 flex-1 bg-background"
                      />
                      <Input
                        {...form.register(`values.${index}.code`, {
                          setValueAs: (value: string) => value.toUpperCase(),
                        })}
                        aria-label={`${t('attributes.valueCode')} ${index + 1}`}
                        aria-invalid={Boolean(errors?.code)}
                        maxLength={20}
                        autoComplete="off"
                        dir="ltr"
                        className="h-9 w-28 bg-background font-mono text-xs uppercase"
                      />
                      <div className="flex shrink-0">
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-sm"
                          disabled={index === 0}
                          aria-label={t('attributes.moveUp', { name: label })}
                          onClick={() => values.move(index, index - 1)}
                        >
                          <ArrowUpIcon aria-hidden="true" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-sm"
                          disabled={index === values.fields.length - 1}
                          aria-label={t('attributes.moveDown', { name: label })}
                          onClick={() => values.move(index, index + 1)}
                        >
                          <ArrowDownIcon aria-hidden="true" />
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('attributes.removeValue', { name: label })}
                          onClick={() => values.remove(index)}
                        >
                          <XIcon aria-hidden="true" />
                        </Button>
                      </div>
                    </div>
                    {[errors?.name, errors?.code].map(
                      (error, i) =>
                        error && (
                          <p key={i} role="alert" className="text-sm text-destructive">
                            {fieldMessage(error)}
                          </p>
                        ),
                    )}
                  </li>
                )
              })}
            </ol>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="gap-2 self-start"
              onClick={() => values.append({ id: null, name: '', code: '' })}
            >
              <PlusIcon className="size-4" aria-hidden="true" />
              {t('attributes.addValue')}
            </Button>
          </FieldSet>
        </FieldGroup>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onDone}>
            {t('common:actions.cancel')}
          </Button>
          <Button type="submit" className="gap-2">
            {save.isPending && <InlineSpinner className="size-4" />}
            {attribute ? t('common:actions.saveChanges') : t('attributes.create')}
          </Button>
        </DialogFooter>
      </fieldset>
    </form>
  )
}
