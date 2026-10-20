import type { ComponentProps, ReactNode } from 'react'
import {
  type Control,
  Controller,
  type FieldError as FormFieldError,
  type FieldPath,
  type FieldValues,
} from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
  InputGroupText,
} from '@/components/ui/input-group'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'

/**
 * The form pattern every screen shares: React Hook Form + Zod, one of these fields per
 * value, server 422 errors placed under the matching field (`showServerErrors`).
 * Zod messages are full i18n keys ("catalog:validation.nameRequired"); server messages
 * arrive translated and are shown as they are.
 */
function useFieldMessage() {
  const { t } = useTranslation()
  return (error: FormFieldError | undefined): string | undefined => {
    if (!error?.message) return undefined
    return error.type === 'server' ? error.message : t(error.message as never)
  }
}

type BaseFieldProps<T extends FieldValues> = {
  control: Control<T>
  name: FieldPath<T>
  label: string
  description?: ReactNode
  /** Prefix for element ids, unique on the page ("category"). */
  idPrefix: string
}

function ids(idPrefix: string, name: string) {
  const id = `${idPrefix}-${name}`
  return { id, error: `${id}-error`, description: `${id}-description` }
}

export function TextField<T extends FieldValues>({
  control,
  name,
  label,
  description,
  idPrefix,
  className,
  unit,
  ...inputProps
}: BaseFieldProps<T> &
  Omit<ComponentProps<typeof Input>, 'name' | 'id'> & {
    /** Shown after the value, inside the field ("days", "%"). */
    unit?: string
  }) {
  const message = useFieldMessage()
  const id = ids(idPrefix, name)

  return (
    <Controller
      name={name}
      control={control}
      render={({ field, fieldState }) => {
        const input = {
          ...inputProps,
          ...field,
          value: field.value ?? '',
          id: id.id,
          'aria-invalid': fieldState.invalid,
          'aria-describedby':
            cn(fieldState.error && id.error, description && id.description) || undefined,
        }
        return (
          <Field data-invalid={fieldState.invalid}>
            <FieldLabel htmlFor={id.id}>{label}</FieldLabel>
            {unit ? (
              <InputGroup className="h-10 rounded-lg bg-background">
                <InputGroupInput {...input} className={className} />
                <InputGroupAddon align="inline-end">
                  <InputGroupText>{unit}</InputGroupText>
                </InputGroupAddon>
              </InputGroup>
            ) : (
              <Input {...input} className={cn('h-10 rounded-lg bg-background', className)} />
            )}
            {description && <FieldDescription id={id.description}>{description}</FieldDescription>}
            <FieldError id={id.error}>{message(fieldState.error)}</FieldError>
          </Field>
        )
      }}
    />
  )
}

export function TextareaField<T extends FieldValues>({
  control,
  name,
  label,
  description,
  idPrefix,
  className,
  ...textareaProps
}: BaseFieldProps<T> & Omit<ComponentProps<typeof Textarea>, 'name' | 'id'>) {
  const message = useFieldMessage()
  const id = ids(idPrefix, name)

  return (
    <Controller
      name={name}
      control={control}
      render={({ field, fieldState }) => (
        <Field data-invalid={fieldState.invalid}>
          <FieldLabel htmlFor={id.id}>{label}</FieldLabel>
          <Textarea
            {...textareaProps}
            {...field}
            value={field.value ?? ''}
            id={id.id}
            aria-invalid={fieldState.invalid}
            aria-describedby={
              cn(fieldState.error && id.error, description && id.description) || undefined
            }
            className={cn('min-h-24 rounded-lg bg-background', className)}
          />
          {description && <FieldDescription id={id.description}>{description}</FieldDescription>}
          <FieldError id={id.error}>{message(fieldState.error)}</FieldError>
        </Field>
      )}
    />
  )
}

/**
 * A choice from a short list, as a native select (the phone's own picker on mobile).
 * Values are strings in the form; convert them in the schema.
 */
export function SelectField<T extends FieldValues>({
  control,
  name,
  label,
  description,
  idPrefix,
  options,
  disabled,
}: BaseFieldProps<T> & {
  options: { value: string; label: string }[]
  disabled?: boolean
}) {
  const message = useFieldMessage()
  const id = ids(idPrefix, name)

  return (
    <Controller
      name={name}
      control={control}
      render={({ field, fieldState }) => (
        <Field data-invalid={fieldState.invalid}>
          <FieldLabel htmlFor={id.id}>{label}</FieldLabel>
          <NativeSelect
            {...field}
            value={field.value ?? ''}
            id={id.id}
            disabled={disabled}
            aria-invalid={fieldState.invalid}
            aria-describedby={
              cn(fieldState.error && id.error, description && id.description) || undefined
            }
            className="w-full [&_select]:h-10 [&_select]:bg-background"
          >
            {options.map((option) => (
              <NativeSelectOption key={option.value} value={option.value}>
                {option.label}
              </NativeSelectOption>
            ))}
          </NativeSelect>
          {description && <FieldDescription id={id.description}>{description}</FieldDescription>}
          <FieldError id={id.error}>{message(fieldState.error)}</FieldError>
        </Field>
      )}
    />
  )
}
