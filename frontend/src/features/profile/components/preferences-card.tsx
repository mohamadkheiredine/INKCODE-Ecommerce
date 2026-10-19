import { type LucideIcon, MoonIcon, SunIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { type Theme, useTheme } from '@/components/theme/theme-context'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { asLanguage, LANGUAGES, type Language } from '@/lib/i18n'
import { useSavePreferences } from '../hooks/use-profile'

const THEMES: { value: Theme; icon: LucideIcon }[] = [
  { value: 'light', icon: SunIcon },
  { value: 'dark', icon: MoonIcon },
]

/** Language and theme: they apply at once and are kept on the profile for every device. */
export function PreferencesCard() {
  const { t, i18n } = useTranslation('profile')
  const { theme, setTheme } = useTheme()
  const savePreferences = useSavePreferences()
  const language = asLanguage(i18n.resolvedLanguage)

  const chooseLanguage = async (next: Language) => {
    await i18n.changeLanguage(next)
    savePreferences({ locale: next })
  }

  const chooseTheme = (next: Theme) => {
    setTheme(next)
    savePreferences({ theme: next })
  }

  return (
    <Card className="js-reveal rounded-2xl">
      <CardHeader>
        <CardTitle>{t('preferences.title')}</CardTitle>
        <CardDescription>{t('preferences.description')}</CardDescription>
      </CardHeader>
      <CardContent className="grid gap-6 sm:grid-cols-2">
        <fieldset className="flex flex-col gap-3">
          <legend className="mb-3 text-sm font-medium">{t('preferences.language')}</legend>
          <RadioGroup
            value={language}
            onValueChange={(value) => void chooseLanguage(value as Language)}
            className="grid grid-cols-2 gap-2"
          >
            {LANGUAGES.map((option) => (
              <ChoiceTile key={option} id={`language-${option}`} value={option} lang={option}>
                {t(`common:language.${option}`)}
              </ChoiceTile>
            ))}
          </RadioGroup>
        </fieldset>
        <fieldset className="flex flex-col gap-3">
          <legend className="mb-3 text-sm font-medium">{t('preferences.theme')}</legend>
          <RadioGroup
            value={theme}
            onValueChange={(value) => chooseTheme(value as Theme)}
            className="grid grid-cols-2 gap-2"
          >
            {THEMES.map(({ value, icon: Icon }) => (
              <ChoiceTile key={value} id={`theme-${value}`} value={value}>
                <Icon className="size-4" aria-hidden="true" />
                {t(`preferences.${value}`)}
              </ChoiceTile>
            ))}
          </RadioGroup>
        </fieldset>
      </CardContent>
    </Card>
  )
}

function ChoiceTile({
  id,
  value,
  lang,
  children,
}: {
  id: string
  value: string
  lang?: string
  children: React.ReactNode
}) {
  return (
    <Label
      htmlFor={id}
      className="flex cursor-pointer items-center gap-2.5 rounded-xl border bg-background px-3 py-2.5 font-medium transition-colors hover:bg-accent has-data-[state=checked]:border-primary has-data-[state=checked]:bg-primary/5 has-data-[state=checked]:text-primary"
    >
      <RadioGroupItem id={id} value={value} />
      <span lang={lang} className="flex items-center gap-1.5">
        {children}
      </span>
    </Label>
  )
}
