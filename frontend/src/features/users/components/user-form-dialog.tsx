import { zodResolver } from '@hookform/resolvers/zod'
import { TriangleAlertIcon } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
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
import { FieldGroup } from '@/components/ui/field'
import { ROLES } from '@/features/auth/types'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useSaveUser } from '../hooks/use-users'
import {
  type UserFormValues,
  editUserSchema,
  newUserSchema,
  userFormValues,
} from '../schemas/user-schema'
import type { User } from '../types'

const FIELDS = ['name', 'email', 'role', 'password', 'password_confirmation'] as const

type UserFormDialogProps = {
  open: boolean
  /** The User to edit, or null to create one. */
  user: User | null
  /** The Admin's own row: the API refuses a change of their own Role, so it is locked here. */
  isSelf?: boolean
  onOpenChange: (open: boolean) => void
}

/** Creates a User (with a temporary password) or edits one's name, email and Role. */
export function UserFormDialog({ open, user, isSelf = false, onOpenChange }: UserFormDialogProps) {
  const { t } = useTranslation('users')
  const dir = useDirection()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        dir={dir}
        className="max-h-[90svh] overflow-y-auto sm:max-w-lg"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>{user ? t('form.editTitle') : t('form.newTitle')}</DialogTitle>
          <DialogDescription>
            {user ? t('form.editDescription') : t('form.newDescription')}
          </DialogDescription>
        </DialogHeader>
        {open && <UserForm user={user} isSelf={isSelf} onDone={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  )
}

function UserForm({
  user,
  isSelf,
  onDone,
}: {
  user: User | null
  isSelf: boolean
  onDone: () => void
}) {
  const { t } = useTranslation('users')
  const save = useSaveUser()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<UserFormValues>({
    resolver: zodResolver(user ? editUserSchema : newUserSchema),
    defaultValues: userFormValues(user),
  })

  const submit = async (values: UserFormValues) => {
    setFormError(null)
    try {
      await save.mutateAsync({ id: user?.id ?? null, input: values })
      // i18n.t, not t: the language may have switched while the request ran.
      toast.success(i18n.t(user ? 'users:toasts.saved' : 'users:toasts.created'))
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
            idPrefix="user"
            label={t('fields.name')}
            maxLength={120}
            autoComplete="off"
          />
          <TextField
            control={form.control}
            name="email"
            idPrefix="user"
            label={t('fields.email')}
            type="email"
            inputMode="email"
            maxLength={255}
            autoComplete="off"
            dir="ltr"
            className="text-start"
          />
          <SelectField
            control={form.control}
            name="role"
            idPrefix="user"
            label={t('fields.role')}
            description={isSelf ? t('form.ownRole') : undefined}
            disabled={isSelf}
            options={ROLES.map((role) => ({ value: role, label: t(`common:roles.${role}`) }))}
          />
          {!user && (
            <>
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  control={form.control}
                  name="password"
                  idPrefix="user"
                  label={t('fields.password')}
                  type="password"
                  autoComplete="new-password"
                  dir="ltr"
                  className="text-start"
                />
                <TextField
                  control={form.control}
                  name="password_confirmation"
                  idPrefix="user"
                  label={t('fields.passwordConfirmation')}
                  type="password"
                  autoComplete="new-password"
                  dir="ltr"
                  className="text-start"
                />
              </div>
              <p className="-mt-2 text-sm text-muted-foreground">{t('form.passwordHint')}</p>
            </>
          )}
        </FieldGroup>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onDone}>
            {t('common:actions.cancel')}
          </Button>
          <Button type="submit" className="gap-2">
            {busy && <InlineSpinner className="size-4" />}
            {user ? t('common:actions.saveChanges') : t('form.create')}
          </Button>
        </DialogFooter>
      </fieldset>
    </form>
  )
}
