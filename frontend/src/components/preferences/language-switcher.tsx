import { LanguagesIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import type { Language } from '@/lib/i18n'
import { cn } from '@/lib/utils'

/** Flips the whole interface between English (LTR) and Arabic (RTL). */
export function LanguageSwitcher({ className }: { className?: string }) {
  const { t, i18n } = useTranslation()
  const next: Language = i18n.resolvedLanguage === 'ar' ? 'en' : 'ar'
  const nextLabel = t(`language.${next}`)

  return (
    <Button
      type="button"
      variant="ghost"
      size="sm"
      className={cn('h-9 gap-2 rounded-full px-3 font-medium', className)}
      onClick={() => void i18n.changeLanguage(next)}
      aria-label={t('language.switchTo', { language: nextLabel })}
    >
      <LanguagesIcon className="size-4" aria-hidden="true" />
      <span lang={next} className="hidden sm:inline">
        {nextLabel}
      </span>
    </Button>
  )
}
