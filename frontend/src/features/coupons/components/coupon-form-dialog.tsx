import { zodResolver } from '@hookform/resolvers/zod'
import { TriangleAlertIcon, XIcon } from 'lucide-react'
import { useContext, useState } from 'react'
import { Controller, type FieldPath, useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { SelectField, TextField } from '@/components/forms/form-fields'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useDirection } from '@/components/ui/direction'
import { FieldGroup, FieldLegend, FieldSet } from '@/components/ui/field'
import { Label } from '@/components/ui/label'
import { useCategoryOptions } from '@/features/catalog/hooks/use-categories'
import { useProducts } from '@/features/catalog/hooks/use-products'
import type { ProductListItem } from '@/features/catalog/types'
import { SearchPicker } from '@/features/orders/components/search-picker'
import { CurrencyContext } from '@/lib/format'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useSaveCoupon } from '../hooks/use-coupons'
import {
  type CouponFormValues,
  couponFormValues,
  couponSchema,
  toCouponInput,
} from '../schemas/coupon-schema'
import type { Coupon } from '../types'

const SERVER_FIELDS = [
  'code',
  'type',
  'value',
  'starts_on',
  'ends_on',
  'usage_limit',
  'per_customer_limit',
  'minimum_order_amount',
] as const satisfies readonly FieldPath<CouponFormValues>[]

type CouponFormDialogProps = {
  open: boolean
  /** The Coupon to edit (with its eligible items), or null to create one. */
  coupon: Coupon | null
  onOpenChange: (open: boolean) => void
}

/** Creates or edits a Coupon: its code, discount, dates, limits, minimum and eligible items. */
export function CouponFormDialog({ open, coupon, onOpenChange }: CouponFormDialogProps) {
  const { t } = useTranslation('coupons')
  const dir = useDirection()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        dir={dir}
        className="max-h-[90svh] overflow-y-auto sm:max-w-2xl"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>{coupon ? t('editTitle') : t('newTitle')}</DialogTitle>
          <DialogDescription>{t('formDescription')}</DialogDescription>
        </DialogHeader>
        {open && <CouponForm coupon={coupon} onDone={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  )
}

/** Today in the browser's time zone, as `YYYY-MM-DD`. */
function today(): string {
  const now = new Date()
  return new Date(now.getTime() - now.getTimezoneOffset() * 60_000).toISOString().slice(0, 10)
}

function CouponForm({ coupon, onDone }: { coupon: Coupon | null; onDone: () => void }) {
  const { t } = useTranslation('coupons')
  const currency = useContext(CurrencyContext)
  const save = useSaveCoupon()
  const categories = useCategoryOptions()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<CouponFormValues>({
    resolver: zodResolver(couponSchema),
    defaultValues: couponFormValues(coupon, today()),
  })
  const type = useWatch({ control: form.control, name: 'type' })
  const products = useWatch({ control: form.control, name: 'products' })

  const submit = async (values: CouponFormValues) => {
    setFormError(null)
    try {
      const saved = await save.mutateAsync({ id: coupon?.id ?? null, input: toCouponInput(values) })
      toast.success(i18n.t(coupon ? 'coupons:saved' : 'coupons:created', { code: saved.code }))
      onDone()
    } catch (error) {
      if (!showServerErrors(form.setError, error, SERVER_FIELDS))
        setFormError(apiErrorMessage(error))
    }
  }

  const addProduct = (product: ProductListItem) => {
    if (products.some((item) => item.id === product.id)) return
    form.setValue('products', [...products, { id: product.id, name: product.name }])
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
        <FieldGroup className="grid gap-4 sm:grid-cols-2">
          <TextField
            control={form.control}
            name="code"
            idPrefix="coupon"
            label={t('fields.code')}
            description={t('codeHint')}
            maxLength={40}
            autoComplete="off"
            dir="ltr"
            className="text-start font-mono uppercase"
          />
          <div className="grid grid-cols-2 gap-4">
            <SelectField
              control={form.control}
              name="type"
              idPrefix="coupon"
              label={t('fields.type')}
              options={[
                { value: 'percentage', label: t('types.percentage') },
                { value: 'fixed', label: t('types.fixed') },
              ]}
            />
            <TextField
              control={form.control}
              name="value"
              idPrefix="coupon"
              label={t('fields.value')}
              inputMode="decimal"
              autoComplete="off"
              unit={type === 'percentage' ? '%' : currency.symbol}
            />
          </div>
          <TextField
            control={form.control}
            name="starts_on"
            idPrefix="coupon"
            label={t('fields.startsOn')}
            type="date"
          />
          <TextField
            control={form.control}
            name="ends_on"
            idPrefix="coupon"
            label={t('fields.endsOn')}
            description={t('endsOnHint')}
            type="date"
          />
          <TextField
            control={form.control}
            name="usage_limit"
            idPrefix="coupon"
            label={t('fields.usageLimit')}
            description={t('limitHint')}
            inputMode="numeric"
            autoComplete="off"
          />
          <TextField
            control={form.control}
            name="per_customer_limit"
            idPrefix="coupon"
            label={t('fields.perCustomerLimit')}
            description={t('limitHint')}
            inputMode="numeric"
            autoComplete="off"
          />
          <TextField
            control={form.control}
            name="minimum_order_amount"
            idPrefix="coupon"
            label={t('fields.minimumOrderAmount')}
            description={t('minimumHint')}
            inputMode="decimal"
            autoComplete="off"
            unit={currency.symbol}
          />
        </FieldGroup>

        <FieldSet className="gap-3 rounded-xl border p-4">
          <FieldLegend className="text-sm font-medium">{t('eligibleTitle')}</FieldLegend>
          <p className="text-xs text-muted-foreground">{t('eligibleHint')}</p>
          <SearchPicker<ProductListItem>
            label={t('findProduct')}
            placeholder={t('findProduct')}
            noMatches={t('noProductMatches')}
            useResults={useProductSearch}
            getKey={(product) => product.id}
            getLabel={(product) => t('addProduct', { name: product.name })}
            renderItem={(product) => (
              <span dir="auto" className="truncate text-sm font-medium">
                {product.name}
              </span>
            )}
            onPick={addProduct}
          />
          {products.length > 0 && (
            <ul className="flex flex-wrap gap-2" aria-label={t('fields.products')}>
              {products.map((product) => (
                <li key={product.id}>
                  <Badge variant="secondary" className="gap-1 ps-2.5 pe-1">
                    <span dir="auto">{product.name}</span>
                    <button
                      type="button"
                      className="rounded-full p-0.5 hover:bg-background/60"
                      aria-label={t('removeProduct', { name: product.name })}
                      onClick={() =>
                        form.setValue(
                          'products',
                          products.filter((item) => item.id !== product.id),
                        )
                      }
                    >
                      <XIcon className="size-3" aria-hidden="true" />
                    </button>
                  </Badge>
                </li>
              ))}
            </ul>
          )}
          <Controller
            control={form.control}
            name="category_ids"
            render={({ field }) => (
              <div className="flex flex-col gap-2">
                <span className="text-sm font-medium">{t('fields.categories')}</span>
                <div
                  className="grid max-h-44 gap-2 overflow-y-auto rounded-lg border p-3 sm:grid-cols-2"
                  data-lenis-prevent
                >
                  {(categories.data ?? []).map((category) => {
                    const id = `coupon-category-${category.id}`
                    const checked = field.value.includes(category.id)
                    return (
                      <div key={category.id} className="flex items-center gap-2">
                        <Checkbox
                          id={id}
                          checked={checked}
                          onCheckedChange={(next) =>
                            field.onChange(
                              next === true
                                ? [...field.value, category.id]
                                : field.value.filter((value) => value !== category.id),
                            )
                          }
                        />
                        <Label htmlFor={id} className="font-normal" dir="auto">
                          {category.parent ? `${category.parent.name} › ` : ''}
                          {category.name}
                        </Label>
                      </div>
                    )
                  })}
                  {categories.isPending && (
                    <span className="text-xs text-muted-foreground">
                      {t('common:states.loading')}
                    </span>
                  )}
                </div>
              </div>
            )}
          />
        </FieldSet>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onDone}>
            {t('common:actions.cancel')}
          </Button>
          <Button type="submit" className="gap-2">
            {busy && <InlineSpinner className="size-4" />}
            {coupon ? t('common:actions.saveChanges') : t('create')}
          </Button>
        </DialogFooter>
      </fieldset>
    </form>
  )
}

function useProductSearch(search: string) {
  const products = useProducts({
    search,
    filters: {},
    sort: { field: 'name', direction: 'asc' },
    page: 1,
    perPage: 6,
  })
  return {
    data: search === '' ? undefined : products.data?.data,
    isFetching: products.isFetching && search !== '',
  }
}
