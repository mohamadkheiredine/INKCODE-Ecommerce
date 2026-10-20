import { ArrowLeftIcon, PackageSearchIcon } from 'lucide-react'
import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { EmptyState } from '@/components/feedback/empty-state'
import { ErrorState } from '@/components/feedback/error-state'
import { PageHeader } from '@/components/layout/page-header'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useCan } from '@/features/auth/hooks/use-can'
import { isApiError } from '@/lib/api/api-error'
import { useEntranceAnimation } from '@/lib/motion'
import { ProductDetailsForm } from '../components/product-details-form'
import { ProductImagesCard } from '../components/product-images-card'
import { ProductStatusBadge } from '../components/product-status-badge'
import { VariantBuilder } from '../components/variant-builder'
import { VariantsEditor } from '../components/variants-editor'
import { useProduct } from '../hooks/use-products'
import type { Product } from '../types'

function BackLink() {
  const { t } = useTranslation('catalog')
  return (
    <Button asChild variant="ghost" size="sm" className="-ms-2 gap-1.5 self-start">
      <Link to="/catalog/products">
        <ArrowLeftIcon className="size-4 rtl:rotate-180" aria-hidden="true" />
        {t('products.backToList')}
      </Link>
    </Button>
  )
}

/** A new Product: its details first; once created, its page opens for images and Variants. */
export function NewProductPage() {
  const { t } = useTranslation('catalog')
  const navigate = useNavigate()
  const container = useRef<HTMLDivElement>(null)
  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  return (
    <div ref={container} className="mx-auto flex w-full max-w-3xl flex-col gap-6">
      <BackLink />
      <PageHeader title={t('products.newTitle')} description={t('products.newDescription')} />
      <div className="js-reveal">
        <ProductDetailsForm
          product={null}
          readOnly={false}
          onSaved={(product) => void navigate(`/catalog/products/${product.id}`, { replace: true })}
        />
      </div>
    </div>
  )
}

/** One Product: details, images and Variants (read-only for Roles that only look). */
export function EditProductPage() {
  const { t } = useTranslation('catalog')
  const productId = Number(useParams().productId)
  const product = useProduct(productId)

  if (product.isPending) return <EditorSkeleton label={t('products.loadingOne')} />
  if (product.isError) {
    return isApiError(product.error) && product.error.status === 404 ? (
      <div className="flex flex-col gap-6">
        <BackLink />
        <EmptyState
          icon={PackageSearchIcon}
          title={t('products.notFoundTitle')}
          description={t('products.notFoundDescription')}
          action={
            <Button asChild variant="outline">
              <Link to="/catalog/products">{t('products.backToList')}</Link>
            </Button>
          }
        />
      </div>
    ) : (
      <ErrorState onRetry={() => void product.refetch()} retrying={product.isFetching} />
    )
  }

  return <ProductEditor product={product.data} />
}

function ProductEditor({ product }: { product: Product }) {
  const { t } = useTranslation('catalog')
  const readOnly = !useCan()('catalog.manage')
  const container = useRef<HTMLDivElement>(null)
  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  return (
    <div ref={container} className="flex flex-col gap-6">
      <BackLink />
      <PageHeader
        title={product.name}
        description={readOnly ? t('products.readOnly') : t('products.editDescription')}
        actions={<ProductStatusBadge status={product.status} />}
      />
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
        <div className="js-reveal">
          <ProductDetailsForm key={product.updated_at} product={product} readOnly={readOnly} />
        </div>
        <div className="js-reveal lg:sticky lg:top-20">
          <ProductImagesCard product={product} readOnly={readOnly} />
        </div>
      </div>
      <Card className="js-reveal rounded-2xl">
        <CardHeader>
          <CardTitle className="text-base">{t('variants.title')}</CardTitle>
          <CardDescription>{t('variants.description')}</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {!readOnly && <VariantBuilder key={variantsKey(product)} product={product} />}
          <VariantsEditor key={variantsKey(product)} product={product} readOnly={readOnly} />
        </CardContent>
      </Card>
    </div>
  )
}

/** Changes whenever the saved Variants change, so their editors start again from them. */
function variantsKey(product: Product): string {
  return product.variants
    .map(
      (variant) =>
        `${variant.id}:${variant.sku}:${variant.price}:${variant.average_cost}:${variant.low_stock_threshold}`,
    )
    .join('|')
}

function EditorSkeleton({ label }: { label: string }) {
  return (
    <div role="status" aria-label={label} className="flex flex-col gap-6">
      <Skeleton className="h-8 w-40" />
      <Skeleton className="h-10 w-72" />
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <Skeleton className="h-96 rounded-2xl" />
        <Skeleton className="h-64 rounded-2xl" />
      </div>
      <Skeleton className="h-72 rounded-2xl" />
    </div>
  )
}
