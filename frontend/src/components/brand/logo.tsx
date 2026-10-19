import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'

type LogoMarkProps = { className?: string; inverted?: boolean }

/** Inkcode's mark: an ink drop on the brand gradient. `inverted` for use on the gradient itself. */
export function LogoMark({ className, inverted = false }: LogoMarkProps) {
  const gradientId = useId()

  return (
    <svg viewBox="0 0 32 32" aria-hidden="true" className={cn('size-9 shrink-0', className)}>
      <defs>
        <linearGradient id={gradientId} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" style={{ stopColor: 'var(--brand-from)' }} />
          <stop offset="100%" style={{ stopColor: 'var(--brand-to)' }} />
        </linearGradient>
      </defs>
      <rect
        width="32"
        height="32"
        rx="9"
        fill={
          inverted
            ? 'color-mix(in oklch, var(--brand-foreground) 16%, transparent)'
            : `url(#${gradientId})`
        }
        stroke={inverted ? 'color-mix(in oklch, var(--brand-foreground) 30%, transparent)' : 'none'}
      />
      <path
        d="M16 6.5c0 0-6.5 7.4-6.5 12.1a6.5 6.5 0 0 0 13 0C22.5 13.9 16 6.5 16 6.5Z"
        fill="var(--brand-foreground)"
      />
      <circle
        cx="13.6"
        cy="19.4"
        r="1.7"
        fill={inverted ? 'var(--brand-to)' : `url(#${gradientId})`}
        opacity="0.85"
      />
    </svg>
  )
}

type LogoProps = LogoMarkProps & { showTagline?: boolean }

/** Mark + wordmark. The brand name stays in Latin script in both languages. */
export function Logo({ className, inverted = false, showTagline = true }: LogoProps) {
  const { t } = useTranslation()

  return (
    <div
      className={cn('flex items-center gap-2.5', className)}
      aria-label={t('appName')}
      role="img"
    >
      <LogoMark inverted={inverted} />
      <div className="flex flex-col leading-none" aria-hidden="true">
        <span
          className={cn(
            'text-[1.05rem] font-bold tracking-tight',
            inverted ? 'text-brand-foreground' : 'text-foreground',
          )}
        >
          Inkcode
        </span>
        {showTagline && (
          <span
            className={cn(
              'mt-1 text-[0.7rem] font-medium tracking-[0.18em] uppercase',
              inverted ? 'text-brand-foreground/70' : 'text-muted-foreground',
            )}
          >
            Commerce
          </span>
        )}
      </div>
    </div>
  )
}
