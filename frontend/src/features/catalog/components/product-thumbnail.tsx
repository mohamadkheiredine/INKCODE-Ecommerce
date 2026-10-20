import { PackageIcon } from 'lucide-react'
import { cn } from '@/lib/utils'

/** A Product's main image, or a neutral tile while it has none. */
export function ProductThumbnail({
  src,
  alt,
  className,
}: {
  src: string | null
  alt: string
  className?: string
}) {
  return (
    <div
      className={cn(
        'flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted text-muted-foreground',
        className,
      )}
    >
      {src ? (
        <img src={src} alt={alt} loading="lazy" className="size-full object-cover" />
      ) : (
        <PackageIcon className="size-4" aria-hidden="true" />
      )}
    </div>
  )
}
