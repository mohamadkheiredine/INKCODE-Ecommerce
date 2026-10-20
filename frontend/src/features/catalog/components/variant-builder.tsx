import { SparklesIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { cn } from '@/lib/utils'
import { useAttributeLibrary } from '../hooks/use-attributes'
import { useGenerateVariants } from '../hooks/use-products'
import type { Product } from '../types'
import { type Selection, countMissing } from '../variant-combinations'

/**
 * Pick Attribute values and generate the missing Variants. The values the Product's
 * Variants already use start selected; once it has Variants, only their Attributes can
 * be extended (the API refuses others).
 */
export function VariantBuilder({ product }: { product: Product }) {
  const { t } = useTranslation('catalog')
  const library = useAttributeLibrary()
  const generate = useGenerateVariants(product.id)
  const [selection, setSelection] = useState<Selection>(() =>
    Object.fromEntries(product.attributes.map((attribute) => [attribute.id, attribute.value_ids])),
  )

  const hasVariants = product.variants.length > 0
  const lockedIds = new Set(product.attributes.map((attribute) => attribute.id))
  const attributes = (library.data ?? []).filter(
    (attribute) => !hasVariants || lockedIds.has(attribute.id),
  )
  const missing = countMissing(selection, product.variants)
  const incomplete = hasVariants && [...lockedIds].some((id) => !selection[id]?.length)

  const toggle = (attributeId: number, valueId: number) =>
    setSelection((current) => {
      const values = current[attributeId] ?? []
      return {
        ...current,
        [attributeId]: values.includes(valueId)
          ? values.filter((id) => id !== valueId)
          : [...values, valueId],
      }
    })

  const run = () =>
    generate.mutate(Object.values(selection).flat(), {
      onSuccess: ({ generated }) =>
        toast.success(i18n.t('catalog:variants.generated', { count: generated })),
      onError: (error) => toast.error(apiErrorMessage(error)),
    })

  return (
    <section
      aria-labelledby="variant-builder"
      className="flex flex-col gap-4 rounded-xl border bg-muted/30 p-4"
    >
      <div className="flex flex-col gap-1">
        <h3 id="variant-builder" className="text-sm font-semibold">
          {t('variants.builderTitle')}
        </h3>
        <p className="text-sm text-muted-foreground">
          {hasVariants && product.attributes.length > 0
            ? t('variants.lockedAttributes', {
                attributes: product.attributes.map((attribute) => attribute.name).join(', '),
              })
            : t('variants.builderDescription')}
        </p>
      </div>

      {library.isPending ? (
        <Skeleton className="h-16 w-full" />
      ) : library.isError ? (
        <p className="text-sm text-destructive">{apiErrorMessage(library.error)}</p>
      ) : (library.data ?? []).length === 0 ? (
        <p className="text-sm text-muted-foreground">
          {t('variants.noAttributes')}{' '}
          <Link to="/catalog/attributes" className="font-medium text-primary hover:underline">
            {t('variants.manageAttributes')}
          </Link>
        </p>
      ) : (
        attributes.map((attribute) => (
          <fieldset key={attribute.id} className="flex flex-col gap-2">
            <legend className="mb-2 text-xs font-medium text-muted-foreground" dir="auto">
              {attribute.name}
            </legend>
            <div className="flex flex-wrap gap-2">
              {attribute.values.map((value) => {
                const pressed = selection[attribute.id]?.includes(value.id) ?? false
                return (
                  <button
                    key={value.id}
                    type="button"
                    aria-pressed={pressed}
                    onClick={() => toggle(attribute.id, value.id)}
                    className={cn(
                      'rounded-full border px-3 py-1 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                      pressed
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'bg-background hover:bg-muted',
                    )}
                  >
                    <span dir="auto">{value.name}</span>
                  </button>
                )
              })}
            </div>
          </fieldset>
        ))
      )}

      <div className="flex flex-wrap items-center gap-3">
        <Button
          type="button"
          className="gap-2"
          disabled={missing === 0 || incomplete || generate.isPending}
          onClick={run}
        >
          {generate.isPending ? (
            <InlineSpinner className="size-4" />
          ) : (
            <SparklesIcon className="size-4" aria-hidden="true" />
          )}
          {missing === 0 ? t('variants.nothingNew') : t('variants.generate', { count: missing })}
        </Button>
        {!hasVariants && Object.values(selection).every((values) => values.length === 0) && (
          <span className="text-xs text-muted-foreground">{t('variants.simpleProduct')}</span>
        )}
      </div>
    </section>
  )
}
