import i18n from 'i18next'
import LanguageDetector from 'i18next-browser-languagedetector'
import { initReactI18next } from 'react-i18next'
import arAuth from '@/locales/ar/auth.json'
import arCommon from '@/locales/ar/common.json'
import arErrors from '@/locales/ar/errors.json'
import arHome from '@/locales/ar/home.json'
import arProfile from '@/locales/ar/profile.json'
import enAuth from '@/locales/en/auth.json'
import enCommon from '@/locales/en/common.json'
import enErrors from '@/locales/en/errors.json'
import enHome from '@/locales/en/home.json'
import enProfile from '@/locales/en/profile.json'
import { setApiLanguage } from './api/client'

export const LANGUAGES = ['en', 'ar'] as const
export type Language = (typeof LANGUAGES)[number]

/** Any language tag as one of the interface languages; English unless it is Arabic. */
export function asLanguage(value: string | undefined): Language {
  return value === 'ar' ? 'ar' : 'en'
}

export const LANGUAGE_STORAGE_KEY = 'inkcode.language'

export const resources = {
  en: { common: enCommon, auth: enAuth, errors: enErrors, home: enHome, profile: enProfile },
  ar: { common: arCommon, auth: arAuth, errors: arErrors, home: arHome, profile: arProfile },
} as const

/** Mirrors the active language onto <html> and the API client. */
function applyLanguage(language: string): void {
  const lang = asLanguage(language)
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
    ns: ['common', 'auth', 'errors', 'home', 'profile'],
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
