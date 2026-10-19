import { MoonIcon, SunIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { type Theme, useTheme } from '@/components/theme/theme-context'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

type ThemeToggleProps = {
  className?: string
  /** Called after the switch, for example to keep the choice on the User's profile. */
  onChange?: (theme: Theme) => void
}

export function ThemeToggle({ className, onChange }: ThemeToggleProps) {
  const { t } = useTranslation()
  const { theme, setTheme } = useTheme()
  const isDark = theme === 'dark'

  const toggle = () => {
    const next: Theme = isDark ? 'light' : 'dark'
    setTheme(next)
    onChange?.(next)
  }

  return (
    <Button
      type="button"
      variant="ghost"
      size="icon"
      className={cn('size-9 rounded-full', className)}
      onClick={toggle}
      aria-label={isDark ? t('theme.switchToLight') : t('theme.switchToDark')}
    >
      {isDark ? (
        <SunIcon className="size-4" aria-hidden="true" />
      ) : (
        <MoonIcon className="size-4" aria-hidden="true" />
      )}
    </Button>
  )
}
