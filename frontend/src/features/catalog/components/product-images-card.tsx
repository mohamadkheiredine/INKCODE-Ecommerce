import {
  ChevronLeftIcon,
  ChevronRightIcon,
  ImageIcon,
  ImageUpIcon,
  StarIcon,
  XIcon,
} from 'lucide-react'
import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useProductImages } from '../hooks/use-products'
import type { Product } from '../types'

/** What the API accepts (StoreProductImagesRequest): PNG, JPEG or WebP, 2 MB each, 10 in all. */
const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp']
const IMAGE_MAX_BYTES = 2 * 1024 * 1024
const MAX_IMAGES = 10

/** A Product's images: add several, reorder them, choose the main one (the first), remove. */
export function ProductImagesCard({ product, readOnly }: { product: Product; readOnly: boolean }) {
  const { t } = useTranslation('catalog')
  const images = useProductImages(product.id)
  const input = useRef<HTMLInputElement>(null)
  const busy = images.add.isPending || images.reorder.isPending || images.remove.isPending
  const ids = product.images.map((image) => image.id)

  const onError = (error: unknown) => toast.error(apiErrorMessage(error))

  const upload = (files: File[]) => {
    if (files.some((file) => !IMAGE_TYPES.includes(file.type)))
      return toast.error(t('validation.imageType'))
    if (files.some((file) => file.size > IMAGE_MAX_BYTES))
      return toast.error(t('validation.imageTooLarge'))
    images.add.mutate(files, {
      onSuccess: () => toast.success(i18n.t('catalog:images.added', { count: files.length })),
      onError,
    })
  }

  const move = (from: number, to: number) => {
    const order = [...ids]
    const [moved] = order.splice(from, 1)
    order.splice(to, 0, moved!)
    images.reorder.mutate(order, {
      onSuccess: () => toast.success(i18n.t('catalog:images.reordered')),
      onError,
    })
  }

  return (
    <Card className="rounded-2xl">
      <CardHeader>
        <CardTitle className="text-base">{t('images.title')}</CardTitle>
        <CardDescription>{t('images.description')}</CardDescription>
        {!readOnly && (
          <CardAction>
            <Button
              type="button"
              size="sm"
              variant="outline"
              className="gap-2"
              disabled={busy || ids.length >= MAX_IMAGES}
              onClick={() => input.current?.click()}
            >
              {images.add.isPending ? (
                <InlineSpinner className="size-4" />
              ) : (
                <ImageUpIcon className="size-4" aria-hidden="true" />
              )}
              {images.add.isPending ? t('images.uploading') : t('images.add')}
            </Button>
            <input
              ref={input}
              type="file"
              multiple
              accept={IMAGE_TYPES.join(',')}
              tabIndex={-1}
              aria-label={t('images.add')}
              className="sr-only"
              onChange={(event) => {
                const files = [...(event.target.files ?? [])]
                event.target.value = ''
                if (files.length) upload(files)
              }}
            />
          </CardAction>
        )}
      </CardHeader>
      <CardContent>
        {product.images.length === 0 ? (
          <div className="flex flex-col items-center gap-1 rounded-xl border border-dashed p-6 text-center">
            <ImageIcon className="size-5 text-muted-foreground" aria-hidden="true" />
            <p className="text-sm font-medium">{t('images.emptyTitle')}</p>
            <p className="text-xs text-muted-foreground">{t('images.emptyDescription')}</p>
          </div>
        ) : (
          <ol className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {product.images.map((image, index) => {
              const position = index + 1
              return (
                <li
                  key={image.id}
                  className="group relative overflow-hidden rounded-xl border bg-muted"
                >
                  <img
                    src={image.url}
                    alt={t('images.alt', { position, name: product.name })}
                    className="aspect-square w-full object-cover"
                  />
                  {index === 0 && (
                    <Badge className="absolute start-2 top-2 gap-1">
                      <StarIcon className="size-3" aria-hidden="true" />
                      {t('images.main')}
                    </Badge>
                  )}
                  {!readOnly && (
                    <div className="absolute inset-x-0 bottom-0 flex justify-between gap-1 bg-background/85 p-1 backdrop-blur-sm">
                      <div className="flex">
                        <Button
                          type="button"
                          size="icon-sm"
                          variant="ghost"
                          disabled={busy || index === 0}
                          aria-label={t('images.moveEarlier', { position })}
                          onClick={() => move(index, index - 1)}
                        >
                          <ChevronLeftIcon className="rtl:rotate-180" aria-hidden="true" />
                        </Button>
                        <Button
                          type="button"
                          size="icon-sm"
                          variant="ghost"
                          disabled={busy || index === ids.length - 1}
                          aria-label={t('images.moveLater', { position })}
                          onClick={() => move(index, index + 1)}
                        >
                          <ChevronRightIcon className="rtl:rotate-180" aria-hidden="true" />
                        </Button>
                        {index > 0 && (
                          <Button
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            disabled={busy}
                            aria-label={t('images.makeMain', { position })}
                            onClick={() => move(index, 0)}
                          >
                            <StarIcon aria-hidden="true" />
                          </Button>
                        )}
                      </div>
                      <Button
                        type="button"
                        size="icon-sm"
                        variant="ghost"
                        disabled={busy}
                        aria-label={t('images.remove', { position })}
                        onClick={() =>
                          images.remove.mutate(image.id, {
                            onSuccess: () => toast.success(i18n.t('catalog:images.removed')),
                            onError,
                          })
                        }
                      >
                        <XIcon aria-hidden="true" />
                      </Button>
                    </div>
                  )}
                </li>
              )
            })}
          </ol>
        )}
      </CardContent>
    </Card>
  )
}
