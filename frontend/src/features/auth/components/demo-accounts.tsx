import { useTranslation } from 'react-i18next'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { cn } from '@/lib/utils'
import { useDemoAccounts } from '../hooks/use-auth'
import type { DemoAccount, RoleKey } from '../types'
import { ROLE_ICONS } from './role-icon'

type DemoAccountsProps = {
  onPick: (account: DemoAccount) => void
  pendingRole: RoleKey | null
  disabled: boolean
}

/**
 * One-click sign-in for each Role's demo account. Appears only once the API confirms
 * demo mode, so production never even flashes it.
 */
export function DemoAccounts({ onPick, pendingRole, disabled }: DemoAccountsProps) {
  const { t } = useTranslation()
  const demo = useDemoAccounts()

  if (!demo.isSuccess || demo.data.length === 0) return null

  return (
    <section
      aria-labelledby="demo-accounts-title"
      className="flex animate-in flex-col gap-3 duration-500 fade-in slide-in-from-bottom-1 motion-reduce:animate-none"
    >
      <div className="flex items-center gap-3">
        <span className="h-px flex-1 bg-border" />
        <h2
          id="demo-accounts-title"
          className="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
        >
          {t('auth:demo.title')}
        </h2>
        <span className="h-px flex-1 bg-border" />
      </div>
      <p className="text-center text-xs text-muted-foreground">{t('auth:demo.description')}</p>

      <ul className="flex flex-wrap justify-center gap-2">
        {demo.data.map((account) => {
          const Icon = ROLE_ICONS[account.role]
          const roleName = t(`roles.${account.role}`)
          const isPending = pendingRole === account.role

          return (
            <li key={account.role}>
              <button
                type="button"
                onClick={() => onPick(account)}
                disabled={disabled}
                aria-label={t('auth:demo.signInAs', { role: roleName })}
                title={t(`roleDescriptions.${account.role}`)}
                className={cn(
                  'group flex h-10 items-center gap-2 rounded-full border bg-card ps-1.5 pe-3.5 transition-[translate,background-color,border-color,box-shadow] duration-200',
                  'outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/40',
                  'hover:-translate-y-px hover:border-primary/40 hover:bg-accent/60 hover:shadow-sm',
                  'disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-60',
                  isPending && 'border-primary/50 bg-accent/70',
                )}
              >
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                  {isPending ? (
                    <InlineSpinner className="size-3.5" />
                  ) : (
                    <Icon className="size-3.5" aria-hidden="true" />
                  )}
                </span>
                <span className="text-sm font-medium whitespace-nowrap">{roleName}</span>
              </button>
            </li>
          )
        })}
      </ul>
    </section>
  )
}
