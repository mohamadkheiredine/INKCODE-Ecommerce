import { ImageIcon } from 'lucide-react'
import { cn, initials } from '@/lib/utils'

/** A logo (a Brand's, the store's) on a neutral tile, or the name's initials without one. */
export function LogoTile({
  name,
  src,
  alt,
  className,
}: {
  name: string
  src: string | null
  /** What the image shows, for screen readers ("Atlas logo"). */
  alt: string
  className?: string
}) {
  return (
    <span
      className={cn(
        'flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-xl border bg-muted text-xs font-semibold text-muted-foreground',
        className,
      )}
    >
      {src ? (
        <img
          src={src}
          alt={alt}
          className="size-full bg-background object-contain p-1"
          loading="lazy"
        />
      ) : initials(name) ? (
        <span aria-hidden="true">{initials(name)}</span>
      ) : (
        <ImageIcon className="size-1/3" aria-hidden="true" />
      )}
    </span>
  )
}
