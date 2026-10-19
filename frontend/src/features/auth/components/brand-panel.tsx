import { CheckIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Logo } from '@/components/brand/logo'
import { ROLES } from '../types'
import { ROLE_ICONS } from './role-icon'

const FEATURES = ['stock', 'access', 'languages', 'reports'] as const

/** The brand half of the sign-in card: what Inkcode Commerce is, at a glance. */
export function BrandPanel() {
  const { t } = useTranslation('auth')

  return (
    <aside className="bg-brand-gradient relative isolate hidden flex-col overflow-hidden p-10 text-brand-foreground lg:flex xl:p-12">
      <div
        aria-hidden="true"
        className="absolute inset-0 -z-10 [background-image:radial-gradient(color-mix(in_oklch,var(--brand-foreground)_22%,transparent)_1px,transparent_1px)] [mask-image:linear-gradient(to_bottom,black,transparent_85%)] [background-size:22px_22px] opacity-40"
      />
      <div
        aria-hidden="true"
        className="absolute -end-24 -top-24 -z-10 size-72 rounded-full bg-brand-foreground/15 blur-3xl"
      />
      <div
        aria-hidden="true"
        className="absolute -start-20 -bottom-32 -z-10 size-80 rounded-full bg-brand-to/50 blur-3xl"
      />

      <Logo inverted className="js-brand-item" />

      <div className="mt-auto flex flex-col gap-5 pt-16">
        <h2 className="js-brand-item max-w-[18ch] text-[2rem] leading-[1.15] font-semibold tracking-tight text-balance xl:text-[2.25rem]">
          {t('brand.headline')}
        </h2>
        <p className="js-brand-item max-w-[46ch] text-[0.95rem] leading-relaxed text-brand-foreground/80">
          {t('brand.subheadline')}
        </p>
        <ul className="mt-2 grid gap-3">
          {FEATURES.map((feature) => (
            <li key={feature} className="js-brand-item flex items-center gap-3 text-sm font-medium">
              <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-foreground/18 ring-1 ring-brand-foreground/30">
                <CheckIcon className="size-3.5" aria-hidden="true" />
              </span>
              {t(`brand.features.${feature}`)}
            </li>
          ))}
        </ul>
      </div>

      <div className="js-brand-item mt-12 flex items-center gap-4 border-t border-brand-foreground/20 pt-6">
        <div className="flex -space-x-2 rtl:space-x-reverse" aria-hidden="true">
          {ROLES.map((role) => {
            const Icon = ROLE_ICONS[role]
            return (
              <span
                key={role}
                className="flex size-8 items-center justify-center rounded-full bg-brand-foreground/20 ring-2 ring-[color-mix(in_oklch,var(--brand-to)_70%,var(--brand-from))] backdrop-blur-sm"
              >
                <Icon className="size-3.5" />
              </span>
            )
          })}
        </div>
        <p className="text-sm text-brand-foreground/85">{t('brand.rolesCaption')}</p>
      </div>
    </aside>
  )
}
