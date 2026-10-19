import i18n from 'i18next'
import LanguageDetector from 'i18next-browser-languagedetector'
import { initReactI18next } from 'react-i18next'
import arAuth from '@/locales/ar/auth.json'
import arCommon from '@/locales/ar/common.json'
import arErrors from '@/locales/ar/errors.json'
import enAuth from '@/locales/en/auth.json'
import enCommon from '@/locales/en/common.json'
import enErrors from '@/locales/en/errors.json'
import { setApiLanguage } from './api/client'

export const LANGUAGES = ['en', 'ar'] as const
export type Language = (typeof LANGUAGES)[number]

export const LANGUAGE_STORAGE_KEY = 'inkcode.language'

export const resources = {
  en: { common: enCommon, auth: enAuth, errors: enErrors },
  ar: { common: arCommon, auth: arAuth, errors: arErrors },
} as const

/** Mirrors the active language onto <html> and the API client. */
function applyLanguage(language: string): void {
  const lang: Language = language === 'ar' ? 'ar' : 'en'
  document.documentElement.lang = lang
  document.documentElement.dir = i18n.dir(lang)
  setApiLanguage(lang)
}

i18n.on('languageChanged', applyLanguage)

void i18n
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    resources,
    supportedLngs: LANGUAGES,
    nonExplicitSupportedLngs: true,
    load: 'languageOnly',
    fallbackLng: 'en',
    defaultNS: 'common',
    ns: ['common', 'auth', 'errors'],
    interpolation: { escapeValue: false },
    detection: {
      order: ['localStorage', 'navigator'],
      lookupLocalStorage: LANGUAGE_STORAGE_KEY,
      caches: ['localStorage'],
    },
    returnNull: false,
  })

applyLanguage(i18n.resolvedLanguage ?? 'en')

export default i18n
