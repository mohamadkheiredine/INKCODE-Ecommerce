import { zodResolver } from '@hookform/resolvers/zod'
import { CheckIcon, CircleIcon, EyeIcon, EyeOffIcon, TriangleAlertIcon } from 'lucide-react'
import { useState } from 'react'
import { Controller, type FieldError as FormFieldError, useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { isApiError } from '@/lib/api/api-error'
import { cn } from '@/lib/utils'
import { useChangePassword } from '../hooks/use-profile'
import {
  type ChangePasswordValues,
  changePasswordSchema,
  PASSWORD_MAX_LENGTH,
  PASSWORD_RULES,
  type PasswordRule,
} from '../schemas/change-password-schema'

const FIELDS = ['current_password', 'password', 'password_confirmation'] as const

const EMPTY: ChangePasswordValues = {
  current_password: '',
  password: '',
  password_confirmation: '',
}

/** Replaces the User's password: the current one first, then a new one that meets every rule. */
export function ChangePasswordCard() {
  const { t } = useTranslation('profile')
  const changePassword = useChangePassword()
  const [showPasswords, setShowPasswords] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  const form = useForm<ChangePasswordValues>({
    resolver: zodResolver(changePasswordSchema),
    defaultValues: EMPTY,
  })
  const newPassword = useWatch({ control: form.control, name: 'password' })

  const submit = async (values: ChangePasswordValues) => {
    setFormError(null)
    try {
      await changePassword.mutateAsync(values)
      toast.success(t('password.changed'))
      form.reset(EMPTY)
    } catch (error) {
      if (!isApiError(error)) {
        setFormError(t('errors:unknown'))
        return
      }
      const rejected = FIELDS.filter((field) => error.fieldError(field))
      if (error.status === 422 && rejected.length > 0) {
        rejected.forEach((field) =>
          form.setError(field, { type: 'server', message: error.fieldError(field) }),
        )
        return
      }
      // 429 and 422 already arrive in the interface language.
      setFormError(
        error.status === 429
          ? error.message
          : t(`errors:${error.code}`, { defaultValue: t('errors:unknown') }),
      )
    }
  }

  const busy = changePassword.isPending

  return (
    <Card className="js-reveal rounded-2xl">
      <CardHeader>
        <CardTitle>{t('password.title')}</CardTitle>
        <CardDescription>{t('password.description')}</CardDescription>
      </CardHeader>
      <CardContent>
        <form noValidate onSubmit={form.handleSubmit(submit)} className="flex flex-col gap-5">
          {formError && (
            <Alert variant="destructive" role="alert">
              <TriangleAlertIcon aria-hidden="true" />
              <AlertDescription>{formError}</AlertDescription>
            </Alert>
          )}
          <fieldset disabled={busy} className="contents">
            <FieldGroup className="gap-4">
              {FIELDS.map((name) => (
                <Controller
                  key={name}
                  name={name}
                  control={form.control}
                  render={({ field, fieldState }) => (
                    <Field data-invalid={fieldState.invalid}>
                      <FieldLabel htmlFor={`profile-${name}`}>{t(LABELS[name])}</FieldLabel>
                      <Input
                        {...field}
                        id={`profile-${name}`}
                        type={showPasswords ? 'text' : 'password'}
                        autoComplete={
                          name === 'current_password' ? 'current-password' : 'new-password'
                        }
                        maxLength={PASSWORD_MAX_LENGTH + 1}
                        dir="ltr"
                        aria-invalid={fieldState.invalid}
                        aria-describedby={
                          cn(
                            fieldState.error && `profile-${name}-error`,
                            name === 'password' && 'profile-password-rules',
                          ) || undefined
                        }
                        className="h-10 rounded-lg bg-background text-start"
                      />
                      <FieldError id={`profile-${name}-error`}>
                        {fieldMessage(fieldState.error, t)}
                      </FieldError>
                      {name === 'password' && <PasswordRules password={newPassword} />}
                    </Field>
                  )}
                />
              ))}
            </FieldGroup>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="w-fit gap-2"
                onClick={() => setShowPasswords((shown) => !shown)}
                aria-pressed={showPasswords}
              >
                {showPasswords ? (
                  <EyeOffIcon className="size-4" aria-hidden="true" />
                ) : (
                  <EyeIcon className="size-4" aria-hidden="true" />
                )}
                {showPasswords ? t('password.hide') : t('password.show')}
              </Button>
              <Button type="submit" disabled={busy} className="gap-2 rounded-lg">
                {busy && <InlineSpinner className="size-4" />}
                {busy ? t('password.submitting') : t('password.submit')}
              </Button>
            </div>
          </fieldset>
        </form>
      </CardContent>
    </Card>
  )
}

const LABELS = {
  current_password: 'password.current',
  password: 'password.new',
  password_confirmation: 'password.confirm',
} as const

const RULES: PasswordRule[] = ['length', 'case', 'number']

/** Each strength rule turns green as soon as the new password meets it. */
function PasswordRules({ password }: { password: string }) {
  const { t } = useTranslation('profile')

  return (
    <div id="profile-password-rules" className="rounded-lg bg-muted/60 px-3 py-2.5">
      <p className="mb-1.5 text-xs font-medium text-muted-foreground">
        {t('password.rules.title')}
      </p>
      <ul className="grid gap-1 text-xs sm:grid-cols-3">
        {RULES.map((rule) => {
          const met = PASSWORD_RULES[rule](password)
          return (
            <li
              key={rule}
              className={cn(
                'flex items-center gap-1.5 transition-colors',
                met ? 'text-success' : 'text-muted-foreground',
              )}
            >
              {met ? (
                <CheckIcon className="size-3.5 shrink-0" aria-hidden="true" />
              ) : (
                <CircleIcon className="size-3 shrink-0" aria-hidden="true" />
              )}
              <span>{t(`password.rules.${rule}`)}</span>
              <span className="sr-only">
                ({met ? t('password.rules.met') : t('password.rules.notMet')})
              </span>
            </li>
          )
        })}
      </ul>
    </div>
  )
}

type Translate = ReturnType<typeof useTranslation<'profile'>>['t']

/** Errors set from the API arrive already translated; client-side ones are i18n keys. */
function fieldMessage(error: FormFieldError | undefined, t: Translate): string | undefined {
  if (!error?.message) return undefined
  return error.type === 'server'
    ? error.message
    : t(error.message as 'password.validation.currentRequired')
}
