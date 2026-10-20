import i18n from 'i18next'
import LanguageDetector from 'i18next-browser-languagedetector'
import { initReactI18next } from 'react-i18next'
import arAuth from '@/locales/ar/auth.json'
import arCatalog from '@/locales/ar/catalog.json'
import arCommon from '@/locales/ar/common.json'
import arCustomers from '@/locales/ar/customers.json'
import arErrors from '@/locales/ar/errors.json'
import arHome from '@/locales/ar/home.json'
import arNotifications from '@/locales/ar/notifications.json'
import arProfile from '@/locales/ar/profile.json'
import arSettings from '@/locales/ar/settings.json'
import enAuth from '@/locales/en/auth.json'
import enCatalog from '@/locales/en/catalog.json'
import enCommon from '@/locales/en/common.json'
import enCustomers from '@/locales/en/customers.json'
import enErrors from '@/locales/en/errors.json'
import enHome from '@/locales/en/home.json'
import enNotifications from '@/locales/en/notifications.json'
import enProfile from '@/locales/en/profile.json'
import enSettings from '@/locales/en/settings.json'
import { setApiLanguage } from './api/client'

export const LANGUAGES = ['en', 'ar'] as const
export type Language = (typeof LANGUAGES)[number]

/** Any language tag as one of the interface languages; English unless it is Arabic. */
export function asLanguage(value: string | undefined): Language {
  return value === 'ar' ? 'ar' : 'en'
}

export const LANGUAGE_STORAGE_KEY = 'inkcode.language'

export const resources = {
  en: {
    common: enCommon,
    auth: enAuth,
    catalog: enCatalog,
    customers: enCustomers,
    errors: enErrors,
    home: enHome,
    notifications: enNotifications,
    profile: enProfile,
    settings: enSettings,
  },
  ar: {
    common: arCommon,
    auth: arAuth,
    catalog: arCatalog,
    customers: arCustomers,
    errors: arErrors,
    home: arHome,
    notifications: arNotifications,
    profile: arProfile,
    settings: arSettings,
  },
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
    ns: [
      'common',
      'auth',
      'catalog',
      'customers',
      'errors',
      'home',
      'notifications',
      'profile',
      'settings',
    ],
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
