import { zodResolver } from '@hookform/resolvers/zod'
import { EyeIcon, EyeOffIcon, LockKeyholeIcon, MailIcon, TriangleAlertIcon } from 'lucide-react'
import { useState } from 'react'
import { Controller, type FieldError as FormFieldError, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from '@/components/ui/input-group'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { type ApiError, isApiError } from '@/lib/api/api-error'
import { DEMO_PASSWORD } from '../api/auth-api'
import { useSignIn } from '../hooks/use-auth'
import { type SignInValues, signInSchema } from '../schemas/sign-in-schema'
import type { CurrentUser, DemoAccount, RoleKey } from '../types'
import { DemoAccounts } from './demo-accounts'

const FIELDS = ['email', 'password'] as const

type SignInPanelProps = { onSignedIn: (user: CurrentUser) => void }

/** The sign-in form plus the demo-account shortcuts, sharing one sign-in request. */
export function SignInPanel({ onSignedIn }: SignInPanelProps) {
  const { t } = useTranslation('auth')
  const signIn = useSignIn()
  const [showPassword, setShowPassword] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [pendingRole, setPendingRole] = useState<RoleKey | null>(null)

  const form = useForm<SignInValues>({
    resolver: zodResolver(signInSchema),
    defaultValues: { email: '', password: '', remember: false },
  })

  const showFailure = (error: unknown) => {
    if (!isApiError(error)) {
      setFormError(t('errors:unknown'))
      return
    }
    const fieldMessages = FIELDS.filter((field) => error.fieldError(field))
    if (error.status === 422 && fieldMessages.length > 0) {
      fieldMessages.forEach((field) =>
        form.setError(field, { type: 'server', message: error.fieldError(field) }),
      )
      return
    }
    setFormError(messageFor(error, t))
  }

  const submit = async (values: SignInValues) => {
    setFormError(null)
    try {
      onSignedIn(await signIn.mutateAsync(values))
    } catch (error) {
      showFailure(error)
    } finally {
      setPendingRole(null)
    }
  }

  const signInAs = (account: DemoAccount) => {
    setPendingRole(account.role)
    form.clearErrors()
    form.setValue('email', account.email)
    form.setValue('password', DEMO_PASSWORD)
    void submit({ email: account.email, password: DEMO_PASSWORD, remember: false })
  }

  const busy = signIn.isPending

  return (
    <div className="flex flex-col gap-7">
      <form
        noValidate
        onSubmit={form.handleSubmit(submit)}
        className="js-form-item flex flex-col gap-5"
      >
        {formError && (
          <Alert variant="destructive" role="alert">
            <TriangleAlertIcon aria-hidden="true" />
            <AlertDescription>{formError}</AlertDescription>
          </Alert>
        )}

        <fieldset disabled={busy} className="contents">
          <FieldGroup className="gap-4">
            <Controller
              name="email"
              control={form.control}
              render={({ field, fieldState }) => (
                <Field data-invalid={fieldState.invalid}>
                  <FieldLabel htmlFor="sign-in-email">{t('signIn.email')}</FieldLabel>
                  <InputGroup className="h-11 rounded-xl bg-background">
                    <InputGroupAddon>
                      <MailIcon aria-hidden="true" />
                    </InputGroupAddon>
                    <InputGroupInput
                      {...field}
                      id="sign-in-email"
                      disabled={busy}
                      type="email"
                      inputMode="email"
                      autoComplete="username"
                      dir="ltr"
                      placeholder={t('signIn.emailPlaceholder')}
                      aria-invalid={fieldState.invalid}
                      aria-describedby={fieldState.error ? 'sign-in-email-error' : undefined}
                      className="text-start"
                    />
                  </InputGroup>
                  <FieldError id="sign-in-email-error">
                    {fieldMessage(fieldState.error, t)}
                  </FieldError>
                </Field>
              )}
            />

            <Controller
              name="password"
              control={form.control}
              render={({ field, fieldState }) => (
                <Field data-invalid={fieldState.invalid}>
                  <FieldLabel htmlFor="sign-in-password">{t('signIn.password')}</FieldLabel>
                  <InputGroup className="h-11 rounded-xl bg-background">
                    <InputGroupAddon>
                      <LockKeyholeIcon aria-hidden="true" />
                    </InputGroupAddon>
                    <InputGroupInput
                      {...field}
                      id="sign-in-password"
                      disabled={busy}
                      type={showPassword ? 'text' : 'password'}
                      autoComplete="current-password"
                      dir="ltr"
                      aria-invalid={fieldState.invalid}
                      aria-describedby={fieldState.error ? 'sign-in-password-error' : undefined}
                      className="text-start"
                    />
                    <InputGroupAddon align="inline-end">
                      <InputGroupButton
                        size="icon-xs"
                        disabled={busy}
                        onClick={() => setShowPassword((shown) => !shown)}
                        aria-label={
                          showPassword ? t('signIn.hidePassword') : t('signIn.showPassword')
                        }
                        aria-pressed={showPassword}
                      >
                        {showPassword ? (
                          <EyeOffIcon aria-hidden="true" />
                        ) : (
                          <EyeIcon aria-hidden="true" />
                        )}
                      </InputGroupButton>
                    </InputGroupAddon>
                  </InputGroup>
                  <FieldError id="sign-in-password-error">
                    {fieldMessage(fieldState.error, t)}
                  </FieldError>
                </Field>
              )}
            />

            <Controller
              name="remember"
              control={form.control}
              render={({ field }) => (
                <Field orientation="horizontal" className="gap-2.5">
                  <Checkbox
                    id="sign-in-remember"
                    disabled={busy}
                    checked={field.value}
                    onCheckedChange={(checked) => field.onChange(checked === true)}
                  />
                  <FieldLabel
                    htmlFor="sign-in-remember"
                    className="font-normal text-muted-foreground"
                  >
                    {t('signIn.remember')}
                  </FieldLabel>
                </Field>
              )}
            />
          </FieldGroup>

          <Button
            type="submit"
            disabled={busy}
            className="bg-brand-gradient h-11 w-full rounded-xl text-[0.95rem] font-semibold text-brand-foreground shadow-lg shadow-primary/25 transition-[filter,box-shadow] hover:shadow-primary/35 hover:brightness-110"
          >
            {busy && pendingRole === null ? (
              <>
                <InlineSpinner className="size-4" />
                {t('signIn.submitting')}
              </>
            ) : (
              t('signIn.submit')
            )}
          </Button>
        </fieldset>

        <p className="text-center text-xs text-muted-foreground">{t('signIn.forgotHint')}</p>
      </form>

      <DemoAccounts onPick={signInAs} pendingRole={pendingRole} disabled={busy} />
    </div>
  )
}

type Translate = ReturnType<typeof useTranslation<'auth'>>['t']

/** Errors set from the API arrive already translated; client-side ones are i18n keys. */
function fieldMessage(error: FormFieldError | undefined, t: Translate): string | undefined {
  if (!error?.message) return undefined
  return error.type === 'server' ? error.message : t(error.message as 'validation.emailRequired')
}

function messageFor(error: ApiError, t: Translate): string {
  // The API already answers in the interface language for these.
  if (error.status === 429 || error.status === 422) return error.message
  return t(`errors:${error.code}`, { defaultValue: t('errors:unknown') })
}
