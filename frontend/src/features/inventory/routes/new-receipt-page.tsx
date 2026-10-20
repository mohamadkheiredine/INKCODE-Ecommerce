import { zodResolver } from '@hookform/resolvers/zod'
import { PlusIcon, SearchIcon, Trash2Icon, TriangleAlertIcon } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { type FieldPath, useFieldArray, useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { toast } from 'sonner'
import { SEARCH_DELAY_MS } from '@/components/data-table/data-table-toolbar'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { SelectField, TextareaField, TextField } from '@/components/forms/form-fields'
import { PageHeader } from '@/components/layout/page-header'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { FieldError, FieldGroup } from '@/components/ui/field'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useEntranceAnimation } from '@/lib/motion'
import { useAllWarehouses, useCreateReceipt, useStockLevels } from '../hooks/use-inventory'
import {
  EMPTY_RECEIPT,
  type ReceiptFormValues,
  receiptSchema,
  toReceiptInput,
} from '../schemas/inventory-schemas'
import { optionsLabel, type StockLevel } from '../types'

/** Records stock arriving from a supplier into one Warehouse. */
export function NewReceiptPage() {
  const { t } = useTranslation('inventory')
  const navigate = useNavigate()
  const warehouses = useAllWarehouses()
  const create = useCreateReceipt()
  const { money } = useFormatters()
  // One key per form: a retried submit (lost answer, double click) records the Receipt once.
  const [idempotencyKey] = useState(() => crypto.randomUUID())
  const [formError, setFormError] = useState<string | null>(null)
  const container = useRef<HTMLDivElement>(null)
  const form = useForm<ReceiptFormValues>({
    resolver: zodResolver(receiptSchema),
    defaultValues: EMPTY_RECEIPT,
  })
  const lines = useFieldArray({ control: form.control, name: 'lines', keyName: 'key' })
  const warehouseId = useWatch({ control: form.control, name: 'warehouse_id' })
  const watchedLines = useWatch({ control: form.control, name: 'lines' })

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const totalCents = watchedLines.reduce((sum, line) => {
    const cents = Math.round(Number(line.quantity) * Number(line.unit_cost) * 100)
    return Number.isFinite(cents) ? sum + cents : sum
  }, 0)

  const submit = async (values: ReceiptFormValues) => {
    setFormError(null)
    try {
      const receipt = await create.mutateAsync({ input: toReceiptInput(values), idempotencyKey })
      toast.success(i18n.t('inventory:receipts.created', { number: receipt.number }))
      await navigate(`/inventory/receipts/${receipt.id}`)
    } catch (error) {
      const fields: FieldPath<ReceiptFormValues>[] = [
        'warehouse_id',
        'supplier_reference',
        'notes',
        'lines',
        ...values.lines.flatMap((_, index) => [
          `lines.${index}.quantity` as const,
          `lines.${index}.unit_cost` as const,
        ]),
      ]
      if (!showServerErrors(form.setError, error, fields)) setFormError(apiErrorMessage(error))
    }
  }

  const addLine = (level: StockLevel) => {
    if (watchedLines.some((line) => line.variant_id === level.variant.id)) return
    lines.append({
      variant_id: level.variant.id,
      sku: level.variant.sku,
      label: [level.product.name, optionsLabel(level.variant.options)].filter(Boolean).join(' · '),
      quantity: '1',
      unit_cost: level.average_cost === '0.0000' ? '' : String(Number(level.average_cost)),
    })
    form.clearErrors('lines')
  }

  const busy = create.isPending
  const linesError =
    form.formState.errors.lines?.root?.message ?? form.formState.errors.lines?.message

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader title={t('common:pages.newReceipt')} description={t('receipts.newDescription')} />
      <form noValidate onSubmit={form.handleSubmit(submit)} className="flex flex-col gap-6">
        {formError && (
          <Alert variant="destructive" role="alert">
            <TriangleAlertIcon aria-hidden="true" />
            <AlertDescription>{formError}</AlertDescription>
          </Alert>
        )}
        <fieldset disabled={busy} className="contents">
          <Card className="js-reveal rounded-2xl">
            <CardContent>
              <FieldGroup className="grid gap-4 sm:grid-cols-2">
                <SelectField
                  control={form.control}
                  name="warehouse_id"
                  idPrefix="receipt"
                  label={t('fields.warehouse')}
                  options={[
                    { value: '', label: t('receipts.chooseWarehouse') },
                    ...(warehouses.data?.data ?? [])
                      .filter((warehouse) => warehouse.is_active)
                      .map((warehouse) => ({
                        value: String(warehouse.id),
                        label: `${warehouse.code} · ${warehouse.name}`,
                      })),
                  ]}
                />
                <TextField
                  control={form.control}
                  name="supplier_reference"
                  idPrefix="receipt"
                  label={t('fields.supplierReference')}
                  maxLength={100}
                  autoComplete="off"
                />
                <div className="sm:col-span-2">
                  <TextareaField
                    control={form.control}
                    name="notes"
                    idPrefix="receipt"
                    label={t('fields.notes')}
                    maxLength={1000}
                    rows={2}
                  />
                </div>
              </FieldGroup>
            </CardContent>
          </Card>

          <Card className="js-reveal rounded-2xl">
            <CardHeader>
              <CardTitle className="text-base">{t('receipts.lines')}</CardTitle>
              <CardDescription>{t('receipts.findVariant')}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
              <VariantPicker warehouseId={warehouseId} onPick={addLine} />
              {lines.fields.length > 0 && (
                <ul className="flex flex-col divide-y rounded-xl border">
                  {lines.fields.map((line, index) => (
                    <li
                      key={line.key}
                      className="grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_7rem_9rem_auto] sm:items-start"
                    >
                      <div className="flex min-w-0 flex-col pt-1">
                        <span dir="auto" className="truncate font-medium">
                          {line.label}
                        </span>
                        <span
                          dir="ltr"
                          className="text-start font-mono text-xs text-muted-foreground"
                        >
                          {line.sku}
                        </span>
                      </div>
                      <TextField
                        control={form.control}
                        name={`lines.${index}.quantity`}
                        idPrefix={`receipt-line-${line.variant_id}`}
                        label={t('fields.quantity')}
                        inputMode="numeric"
                        autoComplete="off"
                      />
                      <TextField
                        control={form.control}
                        name={`lines.${index}.unit_cost`}
                        idPrefix={`receipt-line-${line.variant_id}`}
                        label={t('fields.unitCost')}
                        inputMode="decimal"
                        autoComplete="off"
                        dir="ltr"
                        className="text-start"
                      />
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        className="sm:mt-7"
                        aria-label={t('receipts.removeLine', { sku: line.sku })}
                        onClick={() => lines.remove(index)}
                      >
                        <Trash2Icon aria-hidden="true" />
                      </Button>
                    </li>
                  ))}
                </ul>
              )}
              {linesError && (
                <FieldError>
                  {form.formState.errors.lines?.type === 'server'
                    ? linesError
                    : t(linesError as never)}
                </FieldError>
              )}
              <div className="flex items-center justify-between border-t pt-4">
                <span className="text-sm text-muted-foreground">{t('receipts.total')}</span>
                <span className="text-lg font-semibold tabular-nums" data-testid="receipt-total">
                  {money(totalCents / 100)}
                </span>
              </div>
            </CardContent>
          </Card>

          <div className="js-reveal flex justify-end">
            <Button type="submit" className="gap-2">
              {busy && <InlineSpinner className="size-4" />}
              {t('receipts.submit')}
            </Button>
          </div>
        </fieldset>
      </form>
    </div>
  )
}

/** Finds Variants by SKU or Product name in the chosen Warehouse, showing their On-hand. */
function VariantPicker({
  warehouseId,
  onPick,
}: {
  warehouseId: string
  onPick: (level: StockLevel) => void
}) {
  const { t } = useTranslation('inventory')
  const { number } = useFormatters()
  const [value, setValue] = useState('')
  const [search, setSearch] = useState('')

  useEffect(() => {
    const timer = window.setTimeout(() => setSearch(value.trim()), SEARCH_DELAY_MS)
    return () => window.clearTimeout(timer)
  }, [value])

  const results = useStockLevels(
    {
      search,
      filters: { warehouse: warehouseId },
      sort: { field: 'product_name', direction: 'asc' },
      page: 1,
      perPage: 8,
    },
    warehouseId !== '' && search !== '',
  )

  return (
    <div className="flex flex-col gap-2">
      <InputGroup className="h-10 rounded-lg bg-background">
        <InputGroupAddon>
          <SearchIcon aria-hidden="true" />
        </InputGroupAddon>
        <InputGroupInput
          type="search"
          aria-label={t('receipts.findVariant')}
          placeholder={warehouseId ? t('receipts.findVariant') : t('receipts.chooseWarehouse')}
          value={value}
          disabled={warehouseId === ''}
          maxLength={100}
          onChange={(event) => setValue(event.target.value)}
        />
        {results.isFetching && (
          <InputGroupAddon align="inline-end">
            <InlineSpinner className="size-4" />
          </InputGroupAddon>
        )}
      </InputGroup>
      {search !== '' && warehouseId !== '' && results.data && (
        <ul
          className="flex flex-col rounded-xl border bg-card"
          aria-label={t('receipts.addVariant')}
        >
          {results.data.data.length === 0 && (
            <li className="p-3 text-sm text-muted-foreground">{t('receipts.noMatches')}</li>
          )}
          {results.data.data.map((level) => (
            <li key={level.variant.id}>
              <button
                type="button"
                className="flex w-full items-center justify-between gap-3 p-3 text-start hover:bg-muted focus-visible:bg-muted focus-visible:outline-none"
                aria-label={`${t('receipts.addVariant')}: ${level.variant.sku}`}
                onClick={() => {
                  onPick(level)
                  setValue('')
                  setSearch('')
                }}
              >
                <span className="flex min-w-0 flex-col">
                  <span dir="auto" className="truncate text-sm font-medium">
                    {level.product.name}
                    {level.variant.options.length > 0 &&
                      ` · ${optionsLabel(level.variant.options)}`}
                  </span>
                  <span dir="ltr" className="text-start font-mono text-xs text-muted-foreground">
                    {level.variant.sku}
                  </span>
                </span>
                <span className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                  {t('receipts.onHandNow', {
                    count: level.on_hand,
                    formatted: number(level.on_hand),
                  })}
                  <PlusIcon className="size-4" aria-hidden="true" />
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
