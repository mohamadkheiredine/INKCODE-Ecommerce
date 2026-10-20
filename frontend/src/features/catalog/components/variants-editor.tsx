import { zodResolver } from '@hookform/resolvers/zod'
import { LayersIcon, Trash2Icon } from 'lucide-react'
import { useState } from 'react'
import { type FieldPath, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { ConfirmDeleteDialog } from '@/components/feedback/confirm-delete-dialog'
import { EmptyState } from '@/components/feedback/empty-state'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { cn } from '@/lib/utils'
import { useRemoveVariant, useUpdateVariants } from '../hooks/use-products'
import {
  type VariantsFormValues,
  toVariantInputs,
  variantsFormValues,
  variantsSchema,
} from '../schemas/variants-schema'
import type { Product, Variant } from '../types'

type Editable = 'sku' | 'price' | 'average_cost' | 'low_stock_threshold'

/**
 * Every Variant of a Product in one table: SKU, price, Average cost and Low-stock
 * threshold are edited in place and saved together; a Variant can be removed.
 * Mount it with a `key` that changes with the saved Variants, so it starts from them.
 */
export function VariantsEditor({ product, readOnly }: { product: Product; readOnly: boolean }) {
  const { t } = useTranslation('catalog')
  const { money } = useFormatters()
  const update = useUpdateVariants(product.id)
  const removeVariant = useRemoveVariant(product.id)
  const [removing, setRemoving] = useState<Variant | null>(null)
  const form = useForm<VariantsFormValues>({
    resolver: zodResolver(variantsSchema),
    defaultValues: variantsFormValues(product.variants),
  })
  const { errors, isDirty } = form.formState

  if (product.variants.length === 0) {
    return (
      <EmptyState
        icon={LayersIcon}
        title={t('variants.emptyTitle')}
        description={
          readOnly ? t('variants.emptyDescriptionReadOnly') : t('variants.emptyDescription')
        }
        action={null}
      />
    )
  }

  const submit = (values: VariantsFormValues) =>
    update.mutate(toVariantInputs(values), {
      onSuccess: () => toast.success(i18n.t('catalog:variants.saved')),
      onError: (error) => {
        const fields = values.variants.flatMap((_, index) =>
          (['sku', 'price', 'average_cost', 'low_stock_threshold'] as const).map(
            (name) => `variants.${index}.${name}` as FieldPath<VariantsFormValues>,
          ),
        )
        if (!showServerErrors(form.setError, error, fields)) toast.error(apiErrorMessage(error))
      },
    })

  const confirmRemove = () => {
    if (!removing) return
    removeVariant.mutate(removing.id, {
      onSuccess: () => toast.success(i18n.t('catalog:variants.removed')),
      onError: (error) => toast.error(apiErrorMessage(error)),
      onSettled: () => setRemoving(null),
    })
  }

  const cell = (index: number, name: Editable, label: string, className: string) => {
    const error = errors.variants?.[index]?.[name]
    const message =
      error?.message && (error.type === 'server' ? error.message : t(error.message as never))
    const id = `variant-${index}-${name}`
    return (
      <div className="flex flex-col gap-1">
        <Input
          {...form.register(`variants.${index}.${name}`)}
          id={id}
          aria-label={`${label} ${product.variants[index]!.sku}`}
          aria-invalid={Boolean(error)}
          aria-describedby={error ? `${id}-error` : undefined}
          inputMode={
            name === 'sku' ? undefined : name === 'low_stock_threshold' ? 'numeric' : 'decimal'
          }
          placeholder={name === 'low_stock_threshold' ? t('variants.storeDefault') : undefined}
          autoComplete="off"
          dir="ltr"
          className={cn(
            'h-8 bg-background text-start tabular-nums',
            name === 'sku' && 'font-mono text-xs uppercase',
            className,
          )}
        />
        {message && (
          <p id={`${id}-error`} className="text-xs text-destructive">
            {message}
          </p>
        )}
      </div>
    )
  }

  return (
    <form noValidate onSubmit={form.handleSubmit(submit)} className="flex flex-col gap-3">
      <div className="overflow-x-auto rounded-xl border bg-card" data-lenis-prevent>
        <Table aria-label={t('variants.title')}>
          <TableHeader>
            <TableRow className="hover:bg-transparent">
              <TableHead className="px-3 text-xs">{t('variants.options')}</TableHead>
              <TableHead className="px-3 text-xs">{t('fields.sku')}</TableHead>
              <TableHead className="px-3 text-xs">{t('variants.price')}</TableHead>
              <TableHead className="px-3 text-xs">{t('variants.averageCost')}</TableHead>
              <TableHead className="px-3 text-xs">{t('variants.threshold')}</TableHead>
              {!readOnly && (
                <TableHead className="w-10 px-3">
                  <span className="sr-only">{t('common:dataTable.actions')}</span>
                </TableHead>
              )}
            </TableRow>
          </TableHeader>
          <TableBody>
            {product.variants.map((variant, index) => (
              <TableRow key={variant.id} className="align-top">
                <TableCell className="px-3 py-2.5 font-medium whitespace-nowrap" dir="auto">
                  {variant.options.length
                    ? variant.options.map((option) => option.value).join(' / ')
                    : t('variants.noOptions')}
                </TableCell>
                {readOnly ? (
                  <>
                    <TableCell className="px-3 font-mono text-xs" dir="ltr">
                      {variant.sku}
                    </TableCell>
                    <TableCell className="px-3 tabular-nums">{money(variant.price)}</TableCell>
                    <TableCell className="px-3 tabular-nums">
                      {money(variant.average_cost)}
                    </TableCell>
                    <TableCell className="px-3 tabular-nums">
                      {variant.low_stock_threshold ?? t('variants.storeDefault')}
                    </TableCell>
                  </>
                ) : (
                  <>
                    <TableCell className="px-3 py-2">
                      {cell(index, 'sku', t('fields.sku'), 'min-w-44')}
                    </TableCell>
                    <TableCell className="px-3 py-2">
                      {cell(index, 'price', t('variants.price'), 'w-28')}
                    </TableCell>
                    <TableCell className="px-3 py-2">
                      {cell(index, 'average_cost', t('variants.averageCost'), 'w-28')}
                    </TableCell>
                    <TableCell className="px-3 py-2">
                      {cell(index, 'low_stock_threshold', t('variants.threshold'), 'w-32')}
                    </TableCell>
                    <TableCell className="px-3 py-2">
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t('variants.remove', { sku: variant.sku })}
                        onClick={() => setRemoving(variant)}
                      >
                        <Trash2Icon aria-hidden="true" />
                      </Button>
                    </TableCell>
                  </>
                )}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
      {!readOnly && (
        <div className="flex flex-wrap items-center justify-end gap-2">
          {isDirty && (
            <span className="me-auto text-sm text-muted-foreground">{t('variants.unsaved')}</span>
          )}
          <Button
            type="button"
            variant="ghost"
            disabled={!isDirty || update.isPending}
            onClick={() => form.reset()}
          >
            {t('variants.reset')}
          </Button>
          <Button type="submit" className="gap-2" disabled={!isDirty || update.isPending}>
            {update.isPending && <InlineSpinner className="size-4" />}
            {t('variants.save')}
          </Button>
        </div>
      )}
      <ConfirmDeleteDialog
        name={removing?.sku ?? null}
        description={t('variants.removeDescription')}
        pending={removeVariant.isPending}
        onConfirm={confirmRemove}
        onCancel={() => setRemoving(null)}
      />
    </form>
  )
}
