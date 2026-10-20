import { zodResolver } from '@hookform/resolvers/zod'
import { TriangleAlertIcon } from 'lucide-react'
import { type ReactNode, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { SelectField, TextareaField, TextField } from '@/components/forms/form-fields'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
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
import { useBrandOptions } from '../hooks/use-brands'
import { useCategoryOptions } from '../hooks/use-categories'
import { useSaveProduct } from '../hooks/use-products'
import {
  type ProductFormValues,
  productFormValues,
  productSchema,
  toProductInput,
} from '../schemas/product-schema'
import { PRODUCT_STATUSES, type Product } from '../types'

const FIELDS = [
  'name',
  'slug',
  'description',
  'category_id',
  'brand_id',
  'base_price',
  'taxable',
  'tags',
  'seo_title',
  'seo_description',
  'status',
] as const

type ProductDetailsFormProps = {
  /** The Product to edit, or null to create one. */
  product: Product | null
  readOnly: boolean
  /** After a save; the edit page shows the saved Product on its own. */
  onSaved?: (product: Product) => void
}

/** A Product's details in four cards: details, organization, pricing and search engines. */
export function ProductDetailsForm({ product, readOnly, onSaved }: ProductDetailsFormProps) {
  const { t } = useTranslation('catalog')
  const save = useSaveProduct()
  const categories = useCategoryOptions()
  const brands = useBrandOptions()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<ProductFormValues>({
    resolver: zodResolver(productSchema),
    defaultValues: productFormValues(product),
  })

  const submit = async (values: ProductFormValues) => {
    setFormError(null)
    try {
      const saved = await save.mutateAsync({
        id: product?.id ?? null,
        input: toProductInput(values),
      })
      toast.success(i18n.t(product ? 'catalog:products.saved' : 'catalog:products.created'))
      onSaved?.(saved)
    } catch (error) {
      if (!showServerErrors(form.setError, error, FIELDS)) setFormError(apiErrorMessage(error))
    }
  }

  const field = { control: form.control, idPrefix: 'product' }

  return (
    <form noValidate onSubmit={form.handleSubmit(submit)} className="flex flex-col gap-4">
      {formError && (
        <Alert variant="destructive" role="alert">
          <TriangleAlertIcon aria-hidden="true" />
          <AlertDescription>{formError}</AlertDescription>
        </Alert>
      )}
      <fieldset disabled={readOnly || save.isPending} className="contents">
        <Section title={t('products.details')}>
          <TextField
            {...field}
            name="name"
            label={t('fields.name')}
            maxLength={160}
            autoComplete="off"
            dir="auto"
          />
          <TextField
            {...field}
            name="slug"
            label={t('fields.slug')}
            description={t('fields.slugHint')}
            maxLength={180}
            autoComplete="off"
            dir="ltr"
            className="text-start"
          />
          <TextareaField
            {...field}
            name="description"
            label={t('fields.description')}
            maxLength={5000}
            dir="auto"
          />
        </Section>

        <Section title={t('products.organization')}>
          <div className="grid gap-4 sm:grid-cols-2">
            <SelectField
              {...field}
              name="category_id"
              label={t('fields.category')}
              options={[
                { value: '', label: t('products.chooseCategory') },
                ...(categories.data ?? []).map((category) => ({
                  value: String(category.id),
                  label: category.parent
                    ? `${category.parent.name} › ${category.name}`
                    : category.name,
                })),
              ]}
            />
            <SelectField
              {...field}
              name="brand_id"
              label={t('fields.brand')}
              options={[
                { value: '', label: t('products.noBrand') },
                ...(brands.data ?? []).map((brand) => ({
                  value: String(brand.id),
                  label: brand.name,
                })),
              ]}
            />
            <SelectField
              {...field}
              name="status"
              label={t('fields.status')}
              options={PRODUCT_STATUSES.map((status) => ({
                value: status,
                label: t(`products.status.${status}`),
              }))}
            />
            <TextField
              {...field}
              name="tags"
              label={t('products.tags')}
              description={t('products.tagsHint')}
              autoComplete="off"
              dir="auto"
            />
          </div>
        </Section>

        <Section title={t('products.pricing')}>
          <div className="grid gap-4 sm:grid-cols-2 sm:items-start">
            <TextField
              {...field}
              name="base_price"
              label={t('products.basePrice')}
              description={t('products.basePriceHint')}
              inputMode="decimal"
              autoComplete="off"
              dir="ltr"
              className="text-start tabular-nums"
            />
            <Controller
              name="taxable"
              control={form.control}
              render={({ field: taxable }) => (
                <Field orientation="horizontal" className="rounded-lg border p-3">
                  <FieldContent>
                    <FieldLabel htmlFor="product-taxable">{t('products.taxable')}</FieldLabel>
                    <FieldDescription>{t('products.taxableHint')}</FieldDescription>
                  </FieldContent>
                  <Switch
                    id="product-taxable"
                    checked={taxable.value}
                    onCheckedChange={taxable.onChange}
                  />
                </Field>
              )}
            />
          </div>
        </Section>

        <Section title={t('products.seo')} description={t('products.seoHint')}>
          <TextField
            {...field}
            name="seo_title"
            label={t('products.seoTitle')}
            maxLength={70}
            autoComplete="off"
            dir="auto"
          />
          <TextareaField
            {...field}
            name="seo_description"
            label={t('products.seoDescription')}
            maxLength={160}
            dir="auto"
          />
        </Section>

        {!readOnly && (
          <div className="flex justify-end">
            <Button type="submit" className="gap-2">
              {save.isPending && <InlineSpinner className="size-4" />}
              {product ? t('common:actions.saveChanges') : t('products.create')}
            </Button>
          </div>
        )}
      </fieldset>
    </form>
  )
}

function Section({
  title,
  description,
  children,
}: {
  title: string
  description?: string
  children: ReactNode
}) {
  return (
    <Card className="rounded-2xl">
      <CardHeader>
        <CardTitle className="text-base">{title}</CardTitle>
        {description && <CardDescription>{description}</CardDescription>}
      </CardHeader>
      <CardContent>
        <FieldGroup className="gap-4">{children}</FieldGroup>
      </CardContent>
    </Card>
  )
}
