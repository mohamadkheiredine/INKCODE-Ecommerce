import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { type Theme, useTheme } from '@/components/theme/theme-context'
import type { Language } from '@/lib/i18n'

export type Preferences = { locale: Language; theme: Theme }

/**
 * Applies a User's saved language and theme to this browser (and remembers them locally,
 * so the next page load paints them straight away).
 */
export function useApplyPreferences(): (preferences: Preferences) => void {
  const { i18n } = useTranslation()
  const { setTheme } = useTheme()

  return useCallback(
    ({ locale, theme }: Preferences) => {
      if (i18n.resolvedLanguage !== locale) void i18n.changeLanguage(locale)
      setTheme(theme)
    },
    [i18n, setTheme],
  )
}
