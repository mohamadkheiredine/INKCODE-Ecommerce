import { useTranslation } from 'react-i18next'
import { cn, initials } from '@/lib/utils'

/** A Brand's logo on a neutral tile, or its initials when it has none. */
export function BrandLogo({
  name,
  src,
  className,
}: {
  name: string
  src: string | null
  className?: string
}) {
  const { t } = useTranslation('catalog')

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
          alt={t('brands.logoOf', { name })}
          className="size-full bg-background object-contain p-1"
          loading="lazy"
        />
      ) : (
        <span aria-hidden="true">{initials(name)}</span>
      )}
    </span>
  )
}
