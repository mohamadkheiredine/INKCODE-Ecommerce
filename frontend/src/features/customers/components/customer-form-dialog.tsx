import { zodResolver } from '@hookform/resolvers/zod'
import { TriangleAlertIcon } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
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
import { useSaveCustomer } from '../hooks/use-customers'
import {
  type CustomerFormValues,
  customerFormValues,
  customerSchema,
  toCustomerInput,
} from '../schemas/customer-schema'
import type { Customer, CustomerProfile } from '../types'
import { TagField } from './tag-field'

const FIELDS = ['name', 'email', 'phone', 'tags'] as const

type CustomerFormDialogProps = {
  open: boolean
  /** The Customer to edit, or null to create one. */
  customer: Customer | null
  onOpenChange: (open: boolean) => void
  /** Called with the saved Customer, for example to open a new Customer's profile. */
  onSaved?: (customer: CustomerProfile) => void
}

/** Creates or edits a Customer: name, email and/or phone, and Tags. */
export function CustomerFormDialog({
  open,
  customer,
  onOpenChange,
  onSaved,
}: CustomerFormDialogProps) {
  const { t } = useTranslation('customers')
  const dir = useDirection()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        dir={dir}
        className="max-h-[90svh] overflow-y-auto sm:max-w-lg"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>{customer ? t('form.editTitle') : t('form.newTitle')}</DialogTitle>
          <DialogDescription>{t('form.description')}</DialogDescription>
        </DialogHeader>
        {open && (
          <CustomerForm
            customer={customer}
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

function CustomerForm({
  customer,
  onDone,
}: {
  customer: Customer | null
  onDone: (saved?: CustomerProfile) => void
}) {
  const { t } = useTranslation('customers')
  const save = useSaveCustomer()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<CustomerFormValues>({
    resolver: zodResolver(customerSchema),
    defaultValues: customerFormValues(customer),
  })

  const submit = async (values: CustomerFormValues) => {
    setFormError(null)
    try {
      const saved = await save.mutateAsync({
        id: customer?.id ?? null,
        input: toCustomerInput(values),
      })
      // i18n.t, not t: the language may have switched while the request ran.
      toast.success(i18n.t(customer ? 'customers:toasts.saved' : 'customers:toasts.created'))
      onDone(saved)
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
            idPrefix="customer"
            label={t('fields.name')}
            maxLength={120}
            autoComplete="off"
          />
          <div className="grid gap-4 sm:grid-cols-2">
            <TextField
              control={form.control}
              name="email"
              idPrefix="customer"
              label={t('fields.email')}
              type="email"
              inputMode="email"
              maxLength={255}
              autoComplete="off"
              dir="ltr"
              className="text-start"
            />
            <TextField
              control={form.control}
              name="phone"
              idPrefix="customer"
              label={t('fields.phone')}
              type="tel"
              inputMode="tel"
              maxLength={30}
              autoComplete="off"
              dir="ltr"
              className="text-start"
            />
          </div>
          <p className="-mt-2 text-sm text-muted-foreground">{t('form.contactHint')}</p>
          <TagField control={form.control} />
        </FieldGroup>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onDone()}>
            {t('common:actions.cancel')}
          </Button>
          <Button type="submit" className="gap-2">
            {busy && <InlineSpinner className="size-4" />}
            {customer ? t('common:actions.saveChanges') : t('form.create')}
          </Button>
        </DialogFooter>
      </fieldset>
    </form>
  )
}
