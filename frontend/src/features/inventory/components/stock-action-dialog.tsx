import { zodResolver } from '@hookform/resolvers/zod'
import { TriangleAlertIcon } from 'lucide-react'
import { useState } from 'react'
import { type FieldPath, type FieldValues, type UseFormReturn, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { SelectField, TextareaField, TextField } from '@/components/forms/form-fields'
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
import { useAllWarehouses, useCreateAdjustment, useCreateTransfer } from '../hooks/use-inventory'
import {
  type AdjustmentFormValues,
  adjustmentSchema,
  type TransferFormValues,
  transferSchema,
} from '../schemas/inventory-schemas'
import { ADJUSTMENT_REASONS, type AdjustmentReason, type StockLevel } from '../types'

export type StockAction = { kind: 'adjust' | 'transfer'; level: StockLevel }

/** Adjusts or starts a Transfer of one Variant in one Warehouse, from a Stock levels row. */
export function StockActionDialog({
  action,
  onClose,
}: {
  action: StockAction | null
  onClose: () => void
}) {
  const { t } = useTranslation('inventory')
  const dir = useDirection()
  const level = action?.level
  const keys = action?.kind === 'transfer' ? 'transfers' : 'adjust'

  return (
    <Dialog open={action !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent
        dir={dir}
        className="max-h-[90svh] overflow-y-auto sm:max-w-lg"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>{t(`${keys}.title`)}</DialogTitle>
          <DialogDescription>
            {level &&
              t(keys === 'adjust' ? 'adjust.description' : 'transfers.formDescription', {
                sku: level.variant.sku,
                warehouse: level.warehouse.name,
              })}
          </DialogDescription>
        </DialogHeader>
        {action?.kind === 'adjust' && <AdjustmentForm level={action.level} onDone={onClose} />}
        {action?.kind === 'transfer' && <TransferForm level={action.level} onDone={onClose} />}
      </DialogContent>
    </Dialog>
  )
}

/** Shows 422s on their fields and anything else (a 409) above the form. */
function useSubmitErrors<T extends FieldValues>(form: UseFormReturn<T>, fields: FieldPath<T>[]) {
  const [formError, setFormError] = useState<string | null>(null)
  return {
    formError,
    clear: () => setFormError(null),
    show: (error: unknown) => {
      if (!showServerErrors(form.setError, error, fields)) setFormError(apiErrorMessage(error))
    },
  }
}

function FormError({ message }: { message: string | null }) {
  if (!message) return null
  return (
    <Alert variant="destructive" role="alert">
      <TriangleAlertIcon aria-hidden="true" />
      <AlertDescription>{message}</AlertDescription>
    </Alert>
  )
}

function Footer({ busy, label, onDone }: { busy: boolean; label: string; onDone: () => void }) {
  const { t } = useTranslation('inventory')
  return (
    <DialogFooter>
      <Button type="button" variant="outline" onClick={onDone}>
        {t('common:actions.cancel')}
      </Button>
      <Button type="submit" className="gap-2">
        {busy && <InlineSpinner className="size-4" />}
        {label}
      </Button>
    </DialogFooter>
  )
}

function AdjustmentForm({ level, onDone }: { level: StockLevel; onDone: () => void }) {
  const { t } = useTranslation('inventory')
  const create = useCreateAdjustment()
  const [idempotencyKey] = useState(() => crypto.randomUUID())
  const form = useForm<AdjustmentFormValues>({
    resolver: zodResolver(adjustmentSchema),
    defaultValues: { quantity: '', reason: '' as AdjustmentReason, notes: '' },
  })
  const errors = useSubmitErrors(form, ['quantity', 'reason', 'notes'])

  const submit = async (values: AdjustmentFormValues) => {
    errors.clear()
    try {
      const adjustment = await create.mutateAsync({
        idempotencyKey,
        input: {
          warehouse_id: level.warehouse.id,
          variant_id: level.variant.id,
          quantity_delta: Number(values.quantity),
          reason: values.reason,
          notes: values.notes || null,
        },
      })
      toast.success(i18n.t('inventory:adjust.created', { number: adjustment.number }))
      onDone()
    } catch (error) {
      errors.show(error)
    }
  }

  return (
    <form noValidate onSubmit={form.handleSubmit(submit)} className="flex flex-col gap-6">
      <FormError message={errors.formError} />
      <fieldset disabled={create.isPending} className="contents">
        <FieldGroup className="gap-4">
          <TextField
            control={form.control}
            name="quantity"
            idPrefix="adjustment"
            label={t('adjust.quantity')}
            inputMode="numeric"
            autoComplete="off"
            dir="ltr"
          />
          <SelectField
            control={form.control}
            name="reason"
            idPrefix="adjustment"
            label={t('adjust.reason')}
            options={[
              { value: '', label: t('adjust.reason') },
              ...ADJUSTMENT_REASONS.map((reason) => ({
                value: reason,
                label: t(`adjustmentReasons.${reason}`),
              })),
            ]}
          />
          <TextareaField
            control={form.control}
            name="notes"
            idPrefix="adjustment"
            label={t('adjust.notes')}
            maxLength={1000}
            rows={2}
          />
        </FieldGroup>
        <Footer busy={create.isPending} label={t('adjust.submit')} onDone={onDone} />
      </fieldset>
    </form>
  )
}

function TransferForm({ level, onDone }: { level: StockLevel; onDone: () => void }) {
  const { t } = useTranslation('inventory')
  const create = useCreateTransfer()
  const warehouses = useAllWarehouses()
  const [idempotencyKey] = useState(() => crypto.randomUUID())
  const form = useForm<TransferFormValues>({
    resolver: zodResolver(transferSchema),
    defaultValues: { destination_warehouse_id: '', quantity: '', notes: '' },
  })
  const errors = useSubmitErrors(form, ['destination_warehouse_id', 'notes'])

  const submit = async (values: TransferFormValues) => {
    errors.clear()
    try {
      const transfer = await create.mutateAsync({
        idempotencyKey,
        input: {
          source_warehouse_id: level.warehouse.id,
          destination_warehouse_id: Number(values.destination_warehouse_id),
          notes: values.notes || null,
          lines: [{ variant_id: level.variant.id, quantity: Number(values.quantity) }],
        },
      })
      toast.success(i18n.t('inventory:transfers.created', { number: transfer.number }))
      onDone()
    } catch (error) {
      errors.show(error)
    }
  }

  const destinations = (warehouses.data?.data ?? []).filter(
    (warehouse) => warehouse.is_active && warehouse.id !== level.warehouse.id,
  )

  return (
    <form noValidate onSubmit={form.handleSubmit(submit)} className="flex flex-col gap-6">
      <FormError message={errors.formError} />
      <fieldset disabled={create.isPending} className="contents">
        <FieldGroup className="gap-4">
          <SelectField
            control={form.control}
            name="destination_warehouse_id"
            idPrefix="transfer"
            label={t('transfers.destination')}
            options={[
              { value: '', label: t('transfers.destination') },
              ...destinations.map((warehouse) => ({
                value: String(warehouse.id),
                label: `${warehouse.code} · ${warehouse.name}`,
              })),
            ]}
          />
          <TextField
            control={form.control}
            name="quantity"
            idPrefix="transfer"
            label={t('transfers.quantity')}
            inputMode="numeric"
            autoComplete="off"
            dir="ltr"
          />
          <TextareaField
            control={form.control}
            name="notes"
            idPrefix="transfer"
            label={t('transfers.notes')}
            maxLength={1000}
            rows={2}
          />
        </FieldGroup>
        <Footer busy={create.isPending} label={t('transfers.submit')} onDone={onDone} />
      </fieldset>
    </form>
  )
}
