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
import { useSaveWarehouse } from '../hooks/use-inventory'
import {
  type WarehouseFormOutput,
  type WarehouseFormValues,
  warehouseFormValues,
  warehouseSchema,
} from '../schemas/inventory-schemas'
import type { Warehouse } from '../types'

const FIELDS = ['name', 'code', 'address', 'is_active'] as const

type WarehouseFormDialogProps = {
  open: boolean
  /** The Warehouse to edit, or null to create one. */
  warehouse: Warehouse | null
  onOpenChange: (open: boolean) => void
}

/** Creates or edits a Warehouse: name, code, address and whether it is active. */
export function WarehouseFormDialog({ open, warehouse, onOpenChange }: WarehouseFormDialogProps) {
  const { t } = useTranslation('inventory')
  const dir = useDirection()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        dir={dir}
        className="max-h-[90svh] overflow-y-auto sm:max-w-lg"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>
            {warehouse ? t('warehouses.editTitle') : t('warehouses.newTitle')}
          </DialogTitle>
          <DialogDescription>{t('warehouses.formDescription')}</DialogDescription>
        </DialogHeader>
        {open && <WarehouseForm warehouse={warehouse} onDone={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  )
}

function WarehouseForm({ warehouse, onDone }: { warehouse: Warehouse | null; onDone: () => void }) {
  const { t } = useTranslation('inventory')
  const save = useSaveWarehouse()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<WarehouseFormValues, unknown, WarehouseFormOutput>({
    resolver: zodResolver(warehouseSchema),
    defaultValues: warehouseFormValues(warehouse),
  })

  const submit = async (values: WarehouseFormOutput) => {
    setFormError(null)
    try {
      await save.mutateAsync({ id: warehouse?.id ?? null, input: values })
      toast.success(
        i18n.t(warehouse ? 'inventory:warehouses.saved' : 'inventory:warehouses.created'),
      )
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
            idPrefix="warehouse"
            label={t('fields.name')}
            maxLength={120}
            autoComplete="off"
          />
          <TextField
            control={form.control}
            name="code"
            idPrefix="warehouse"
            label={t('fields.code')}
            maxLength={16}
            autoComplete="off"
            dir="ltr"
            className="text-start font-mono uppercase"
          />
          <TextField
            control={form.control}
            name="address"
            idPrefix="warehouse"
            label={t('fields.address')}
            maxLength={255}
            autoComplete="street-address"
          />
          {warehouse && (
            <Controller
              control={form.control}
              name="is_active"
              render={({ field }) => (
                <Field orientation="horizontal" className="rounded-lg border p-3">
                  <FieldContent>
                    <FieldLabel htmlFor="warehouse-is_active">
                      {t('warehouses.activeLabel')}
                    </FieldLabel>
                    <FieldDescription>{t('warehouses.activeHint')}</FieldDescription>
                  </FieldContent>
                  <Switch
                    id="warehouse-is_active"
                    checked={field.value}
                    onCheckedChange={field.onChange}
                    onBlur={field.onBlur}
                  />
                </Field>
              )}
            />
          )}
        </FieldGroup>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onDone}>
            {t('common:actions.cancel')}
          </Button>
          <Button type="submit" className="gap-2">
            {busy && <InlineSpinner className="size-4" />}
            {warehouse ? t('common:actions.saveChanges') : t('warehouses.create')}
          </Button>
        </DialogFooter>
      </fieldset>
    </form>
  )
}
