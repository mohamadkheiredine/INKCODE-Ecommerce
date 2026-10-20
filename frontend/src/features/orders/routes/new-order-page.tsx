import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery } from '@tanstack/react-query'
import { MapPinPlusIcon, Trash2Icon, TriangleAlertIcon, UserPlusIcon, XIcon } from 'lucide-react'
import { type ReactNode, useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { type FieldPath, useFieldArray, useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { SelectField, TextareaField, TextField } from '@/components/forms/form-fields'
import { PageHeader } from '@/components/layout/page-header'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { FieldError, FieldGroup } from '@/components/ui/field'
import { useCan } from '@/features/auth/hooks/use-can'
import { AddressFormDialog } from '@/features/customers/components/address-form-dialog'
import { CustomerFormDialog } from '@/features/customers/components/customer-form-dialog'
import { customerKeys, customersApi } from '@/features/customers/api/customers-api'
import { useCustomers } from '@/features/customers/hooks/use-customers'
import type { Customer, CustomerAddress } from '@/features/customers/types'
import { optionsLabel } from '@/features/inventory/types'
import { useShippingQuote } from '@/features/shipping/hooks/use-shipping-zones'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useEntranceAnimation } from '@/lib/motion'
import { OrderTotals } from '../components/order-totals'
import { SearchPicker } from '../components/search-picker'
import { useCreateOrder, useOrderQuote, useSellableVariants } from '../hooks/use-orders'
import {
  EMPTY_ORDER,
  type OrderFormValues,
  orderSchema,
  quotableLines,
  toOrderInput,
} from '../schemas/order-schema'
import type { SellableVariant } from '../types'

const SERVER_FIELDS = [
  'customer_id',
  'billing_address_id',
  'shipping_address_id',
  'warehouse_id',
  'shipping_method_id',
  'notes',
  'lines',
] as const satisfies readonly FieldPath<OrderFormValues>[]

/**
 * Enters a Pending Order: the Customer (or a new one) and their addresses, the Variants,
 * the Fulfilling warehouse (pre-selected to one with enough Available stock) and a
 * Shipping method, with a live quote priced by the API exactly as the Order will be.
 */
export function NewOrderPage() {
  const { t } = useTranslation('orders')
  const navigate = useNavigate()
  const create = useCreateOrder()
  const { money, number } = useFormatters()
  // One key per form: a retried submit (lost answer, double click) creates the Order once.
  const [idempotencyKey] = useState(() => crypto.randomUUID())
  const [formError, setFormError] = useState<string | null>(null)
  const [warehouseChosen, setWarehouseChosen] = useState(false)
  const container = useRef<HTMLDivElement>(null)
  const form = useForm<OrderFormValues>({
    resolver: zodResolver(orderSchema),
    defaultValues: EMPTY_ORDER,
  })
  const lines = useFieldArray({ control: form.control, name: 'lines', keyName: 'key' })
  const watched = useWatch({ control: form.control })
  const customerId = Number(watched.customer_id || 0)
  const shippingMethodId = Number(watched.shipping_method_id || 0) || null
  const watchedLines = useMemo(
    () => (watched.lines ?? []) as OrderFormValues['lines'],
    [watched.lines],
  )
  const quoteLines = useMemo(() => quotableLines(watchedLines), [watchedLines])
  const quote = useOrderQuote(quoteLines, shippingMethodId)
  const addresses = useCustomerProfile(customerId).data?.addresses
  const chooseShippingMethod = useCallback(
    (id: string) => form.setValue('shipping_method_id', id),
    [form],
  )

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  // The Customer's default addresses, unless an address of theirs is already chosen.
  useEffect(() => {
    if (!addresses) return
    const values = form.getValues()
    const has = (id: string) => addresses.some((address) => String(address.id) === id)
    const billing = addresses.find((address) => address.is_default_billing)
    const shipping = addresses.find((address) => address.is_default_shipping)
    if (!has(values.billing_address_id) && billing) {
      form.setValue('billing_address_id', String(billing.id))
    }
    if (!has(values.shipping_address_id) && shipping) {
      form.setValue('shipping_address_id', String(shipping.id))
    }
  }, [form, addresses])

  // Pre-select a Warehouse that can fulfil every line, until the User picks one.
  const suggestedWarehouse = quote.data?.warehouses.find((warehouse) => warehouse.is_sufficient)
  useEffect(() => {
    if (!warehouseChosen && suggestedWarehouse) {
      form.setValue('warehouse_id', String(suggestedWarehouse.id))
    }
  }, [form, suggestedWarehouse, warehouseChosen])

  const submit = async (values: OrderFormValues) => {
    setFormError(null)
    try {
      const order = await create.mutateAsync({ input: toOrderInput(values), idempotencyKey })
      toast.success(i18n.t('orders:created', { number: order.number }))
      await navigate(`/orders/${order.id}`)
    } catch (error) {
      if (!showServerErrors(form.setError, error, SERVER_FIELDS))
        setFormError(apiErrorMessage(error))
    }
  }

  const addLine = (variant: SellableVariant) => {
    if (watchedLines.some((line) => line.variant_id === variant.id)) return
    lines.append({
      variant_id: variant.id,
      sku: variant.sku,
      label: [variant.product.name, optionsLabel(variant.options)].filter(Boolean).join(' · '),
      quantity: '1',
    })
    form.clearErrors('lines')
  }

  const pricedLine = (variantId: number) =>
    quote.data?.lines.find((line) => line.variant_id === variantId)
  const selectedWarehouse = quote.data?.warehouses.find(
    (warehouse) => String(warehouse.id) === watched.warehouse_id,
  )
  const busy = create.isPending
  const linesError =
    form.formState.errors.lines?.root?.message ?? form.formState.errors.lines?.message

  return (
    <div ref={container} className="flex flex-col gap-6">
      <PageHeader title={t('common:pages.newOrder')} description={t('newDescription')} />
      <form noValidate onSubmit={form.handleSubmit(submit)} className="flex flex-col gap-6">
        {formError && (
          <Alert variant="destructive" role="alert">
            <TriangleAlertIcon aria-hidden="true" />
            <AlertDescription>{formError}</AlertDescription>
          </Alert>
        )}
        <fieldset disabled={busy} className="grid items-start gap-6 lg:grid-cols-3">
          <div className="flex flex-col gap-6 lg:col-span-2">
            <CustomerSection
              customerId={customerId}
              error={form.formState.errors.customer_id?.message}
              onChoose={(customer) => {
                form.setValue('customer_id', customer ? String(customer.id) : '')
                form.setValue('billing_address_id', '')
                form.setValue('shipping_address_id', '')
                form.setValue('shipping_method_id', '')
                form.clearErrors('customer_id')
              }}
              addressFields={(customerAddresses) => (
                <FieldGroup className="grid gap-4 sm:grid-cols-2">
                  <SelectField
                    control={form.control}
                    name="shipping_address_id"
                    idPrefix="order"
                    label={t('fields.shippingAddress')}
                    options={addressOptions(customerAddresses, t('chooseAddress'))}
                  />
                  <SelectField
                    control={form.control}
                    name="billing_address_id"
                    idPrefix="order"
                    label={t('fields.billingAddress')}
                    options={addressOptions(customerAddresses, t('chooseAddress'))}
                  />
                </FieldGroup>
              )}
            />

            <Card className="js-reveal rounded-2xl">
              <CardHeader>
                <CardTitle className="text-base">{t('lines')}</CardTitle>
                <CardDescription>{t('findVariantHint')}</CardDescription>
              </CardHeader>
              <CardContent className="flex flex-col gap-4">
                <SearchPicker<SellableVariant>
                  label={t('findVariant')}
                  placeholder={t('findVariant')}
                  noMatches={t('noVariantMatches')}
                  useResults={useVariantSearch}
                  getKey={(variant) => variant.id}
                  getLabel={(variant) => t('addVariant', { sku: variant.sku })}
                  renderItem={(variant) => (
                    <span className="flex min-w-0 flex-1 items-center justify-between gap-3">
                      <span className="flex min-w-0 flex-col">
                        <span dir="auto" className="truncate text-sm font-medium">
                          {variant.product.name}
                          {variant.options.length > 0 && ` · ${optionsLabel(variant.options)}`}
                        </span>
                        <span
                          dir="ltr"
                          className="text-start font-mono text-xs text-muted-foreground"
                        >
                          {variant.sku}
                        </span>
                      </span>
                      <span className="flex shrink-0 flex-col items-end text-xs">
                        <span className="font-medium tabular-nums">{money(variant.price)}</span>
                        <span className="text-muted-foreground">
                          {t('availableNow', {
                            count: variant.available,
                            formatted: number(variant.available),
                          })}
                        </span>
                      </span>
                    </span>
                  )}
                  onPick={addLine}
                />
                {lines.fields.length > 0 && (
                  <ul className="flex flex-col divide-y rounded-xl border" aria-label={t('lines')}>
                    {lines.fields.map((line, index) => {
                      const priced = pricedLine(line.variant_id)
                      const shortage = selectedWarehouse?.shortages.find(
                        (item) => item.variant_id === line.variant_id,
                      )
                      return (
                        <li
                          key={line.key}
                          className="grid gap-3 p-3 sm:grid-cols-[minmax(0,1fr)_6.5rem_7.5rem_auto] sm:items-start"
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
                            {shortage && (
                              <span className="mt-1 text-xs text-destructive">
                                {t('shortHere', { available: number(shortage.available) })}
                              </span>
                            )}
                          </div>
                          <TextField
                            control={form.control}
                            name={`lines.${index}.quantity`}
                            idPrefix={`order-line-${line.variant_id}`}
                            label={t('fields.quantity')}
                            inputMode="numeric"
                            autoComplete="off"
                          />
                          <div className="flex flex-col gap-1 sm:items-end sm:pt-7">
                            <span className="font-medium tabular-nums">
                              {priced ? money(priced.line_total) : '—'}
                            </span>
                            {priced && (
                              <span className="text-xs text-muted-foreground tabular-nums">
                                {priced.taxable
                                  ? t('includesVat', { amount: money(priced.vat_amount) })
                                  : t('untaxed')}
                              </span>
                            )}
                          </div>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            className="sm:mt-7"
                            aria-label={t('removeLine', { sku: line.sku })}
                            onClick={() => lines.remove(index)}
                          >
                            <Trash2Icon aria-hidden="true" />
                          </Button>
                        </li>
                      )
                    })}
                  </ul>
                )}
                {linesError && (
                  <FieldError>
                    {form.formState.errors.lines?.type === 'server'
                      ? linesError
                      : t(linesError as never)}
                  </FieldError>
                )}
              </CardContent>
            </Card>

            <Card className="js-reveal rounded-2xl">
              <CardHeader>
                <CardTitle className="text-base">{t('fulfilment')}</CardTitle>
                <CardDescription>{t('fulfilmentHint')}</CardDescription>
              </CardHeader>
              <CardContent>
                <FieldGroup className="grid gap-4 sm:grid-cols-2">
                  <div onChangeCapture={() => setWarehouseChosen(true)} className="flex flex-col">
                    <SelectField
                      control={form.control}
                      name="warehouse_id"
                      idPrefix="order"
                      label={t('fields.warehouse')}
                      options={[
                        { value: '', label: t('chooseWarehouse') },
                        ...(quote.data?.warehouses ?? []).map((warehouse) => ({
                          value: String(warehouse.id),
                          label: `${warehouse.code} · ${warehouse.name} — ${
                            warehouse.is_sufficient
                              ? t('warehouseSufficient')
                              : t('warehouseShort', { count: warehouse.shortages.length })
                          }`,
                        })),
                      ]}
                    />
                  </div>
                  <ShippingMethodField
                    customerId={customerId}
                    shippingAddressId={watched.shipping_address_id ?? ''}
                    current={watched.shipping_method_id ?? ''}
                    onDefault={chooseShippingMethod}
                    field={(options, hint) => (
                      <div className="flex flex-col gap-1">
                        <SelectField
                          control={form.control}
                          name="shipping_method_id"
                          idPrefix="order"
                          label={t('fields.shippingMethod')}
                          options={options}
                        />
                        {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
                      </div>
                    )}
                  />
                  <div className="sm:col-span-2">
                    <TextareaField
                      control={form.control}
                      name="notes"
                      idPrefix="order"
                      label={t('fields.notes')}
                      maxLength={1000}
                      rows={2}
                    />
                  </div>
                </FieldGroup>
              </CardContent>
            </Card>
          </div>

          <Card className="js-reveal rounded-2xl lg:sticky lg:top-20">
            <CardHeader>
              <CardTitle className="text-base">{t('summary')}</CardTitle>
              <CardDescription>
                {quote.data
                  ? t('vatRateNote', { rate: number(Number(quote.data.vat_rate)) })
                  : t('summaryEmpty')}
              </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
              <div aria-live="polite" aria-busy={quote.isFetching}>
                <OrderTotals
                  totals={
                    quote.data ?? {
                      items_total: '0.00',
                      discount_total: '0.00',
                      vat_total: '0.00',
                      shipping_total: '0.00',
                      total: '0.00',
                    }
                  }
                />
              </div>
              {quote.isError && (
                <p role="alert" className="text-sm text-destructive">
                  {apiErrorMessage(quote.error)}
                </p>
              )}
              <Button type="submit" className="w-full gap-2">
                {busy && <InlineSpinner className="size-4" />}
                {t('submit')}
              </Button>
              <p className="text-xs text-muted-foreground">{t('submitHint')}</p>
            </CardContent>
          </Card>
        </fieldset>
      </form>
    </div>
  )
}

function addressOptions(addresses: CustomerAddress[], placeholder: string) {
  return [
    { value: '', label: placeholder },
    ...addresses.map((address) => ({
      value: String(address.id),
      label: `${address.label} · ${address.line1}, ${address.city}`,
    })),
  ]
}

/** Who the Order is for: find a Customer, or create one here (with their first address). */
function CustomerSection({
  customerId,
  error,
  onChoose,
  addressFields,
}: {
  customerId: number
  error: string | undefined
  onChoose: (customer: Customer | null) => void
  addressFields: (addresses: CustomerAddress[]) => ReactNode
}) {
  const { t } = useTranslation('orders')
  const canManageCustomers = useCan()('customers.manage')
  const [creating, setCreating] = useState(false)
  const [addingAddress, setAddingAddress] = useState(false)
  const customer = useCustomerProfile(customerId)

  return (
    <Card className="js-reveal rounded-2xl">
      <CardHeader className="flex flex-row items-start justify-between gap-3">
        <div className="flex flex-col gap-1.5">
          <CardTitle className="text-base">{t('fields.customer')}</CardTitle>
          <CardDescription>{t('customerHint')}</CardDescription>
        </div>
        {canManageCustomers && customerId === 0 && (
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="gap-2"
            onClick={() => setCreating(true)}
          >
            <UserPlusIcon className="size-4" aria-hidden="true" />
            {t('newCustomer')}
          </Button>
        )}
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {customerId === 0 ? (
          <SearchPicker<Customer>
            label={t('findCustomer')}
            placeholder={t('findCustomer')}
            noMatches={t('noCustomerMatches')}
            useResults={useCustomerSearch}
            getKey={(item) => item.id}
            getLabel={(item) => t('chooseCustomer', { name: item.name })}
            renderItem={(item) => (
              <span className="flex min-w-0 flex-col">
                <span dir="auto" className="truncate text-sm font-medium">
                  {item.name}
                </span>
                <span dir="ltr" className="text-start text-xs text-muted-foreground">
                  {item.email ?? item.phone}
                </span>
              </span>
            )}
            onPick={onChoose}
          />
        ) : (
          <div className="flex items-center justify-between gap-3 rounded-xl border bg-muted/40 p-3">
            <div className="flex min-w-0 flex-col">
              <span dir="auto" className="truncate font-medium">
                {customer.data?.name ?? '…'}
              </span>
              <span dir="ltr" className="text-start text-xs text-muted-foreground">
                {customer.data?.email ?? customer.data?.phone}
              </span>
            </div>
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              aria-label={t('changeCustomer')}
              onClick={() => onChoose(null)}
            >
              <XIcon aria-hidden="true" />
            </Button>
          </div>
        )}
        {error && <FieldError>{t(error as never)}</FieldError>}
        {customer.data &&
          customer.data.addresses.length > 0 &&
          addressFields(customer.data.addresses)}
        {customer.data && customer.data.addresses.length === 0 && (
          <div className="flex flex-col items-start gap-3 rounded-xl border border-dashed p-4">
            <p className="text-sm text-muted-foreground">{t('noAddresses')}</p>
            {canManageCustomers && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="gap-2"
                onClick={() => setAddingAddress(true)}
              >
                <MapPinPlusIcon className="size-4" aria-hidden="true" />
                {t('addAddress')}
              </Button>
            )}
          </div>
        )}
      </CardContent>
      <CustomerFormDialog
        open={creating}
        customer={null}
        onOpenChange={setCreating}
        onSaved={onChoose}
      />
      {customer.data && (
        <AddressFormDialog
          customerId={customer.data.id}
          address={addingAddress ? 'new' : null}
          isFirst={customer.data.addresses.length === 0}
          defaultCountry="LB"
          onClose={() => setAddingAddress(false)}
        />
      )}
    </Card>
  )
}

/** The chosen Customer's profile (with addresses); idle until one is chosen. */
function useCustomerProfile(customerId: number) {
  return useQuery({
    queryKey: customerKeys.detail(customerId),
    queryFn: () => customersApi.get(customerId),
    enabled: customerId !== 0,
  })
}

function useVariantSearch(search: string) {
  const variants = useSellableVariants(search)
  return { data: variants.data, isFetching: variants.isFetching }
}

function useCustomerSearch(search: string) {
  const customers = useCustomers({
    search,
    filters: {},
    sort: { field: 'name', direction: 'asc' },
    page: 1,
    perPage: 6,
  })
  return {
    data: search === '' ? undefined : customers.data?.data,
    isFetching: customers.isFetching && search !== '',
  }
}

/**
 * The Shipping methods that deliver to the chosen shipping address (cheapest first), the
 * cheapest pre-selected; explains when none does.
 */
function ShippingMethodField({
  customerId,
  shippingAddressId,
  current,
  onDefault,
  field,
}: {
  customerId: number
  shippingAddressId: string
  current: string
  onDefault: (id: string) => void
  field: (options: { value: string; label: string }[], hint: string | null) => ReactNode
}) {
  const { t } = useTranslation('orders')
  const { money } = useFormatters()
  const customer = useCustomerProfile(customerId)
  const address = customer.data?.addresses.find((item) => String(item.id) === shippingAddressId)
  const quote = useShippingQuote(
    address ? { country_code: address.country_code, city: address.city } : null,
  )
  const methods = address ? (quote.data?.methods ?? []) : []

  const cheapest = methods.at(0)
  const currentOffered = methods.some((method) => String(method.id) === current)
  useEffect(() => {
    if (cheapest && !currentOffered) onDefault(String(cheapest.id))
  }, [cheapest, currentOffered, onDefault])

  return field(
    [
      { value: '', label: address ? t('chooseShippingMethod') : t('chooseAddressFirst') },
      ...methods.map((method) => ({
        value: String(method.id),
        label: `${method.name} · ${money(method.price)}`,
      })),
    ],
    address && quote.data && methods.length === 0
      ? (quote.data.message ?? t('noShippingMethods'))
      : null,
  )
}
