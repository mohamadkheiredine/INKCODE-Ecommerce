import { z } from 'zod'
import { LANGUAGES } from '@/lib/i18n'
import { DEDUCTION_STAGES, type StoreSettings, type StoreSettingsInput } from '../types'

/** Whole numbers typed into a field, within the API's limits (UpdateStoreSettingsRequest). */
const wholeNumber = (min: number, max: number, message: string) =>
  z
    .string()
    .trim()
    .regex(/^\d+$/, message)
    .refine((value) => Number(value) >= min && Number(value) <= max, message)

/** The same limits the API applies, checked before saving. Messages are i18n keys. */
export const settingsSchema = z.object({
  name: z.string().trim().min(1, 'settings:validation.nameRequired').max(120),
  contact_email: z
    .string()
    .trim()
    .max(255)
    .refine(
      (value) => value === '' || z.email().safeParse(value).success,
      'settings:validation.email',
    ),
  contact_phone: z.string().trim().max(40),
  address: z.string().trim().max(500),
  currency_code: z
    .string()
    .trim()
    .toUpperCase()
    .regex(/^[A-Z]{3}$/, 'settings:validation.currencyCode'),
  currency_symbol: z
    .string()
    .trim()
    .min(1, 'settings:validation.currencySymbol')
    .max(8, 'settings:validation.currencySymbol'),
  vat_rate: z
    .string()
    .trim()
    .regex(/^\d{1,3}(\.\d{1,2})?$/, 'settings:validation.vatRate')
    .refine((value) => Number(value) <= 100, 'settings:validation.vatRate'),
  deduction_stage: z.enum(DEDUCTION_STAGES),
  return_window_days: wholeNumber(1, 365, 'settings:validation.returnWindow'),
  low_stock_threshold: wholeNumber(0, 100000, 'settings:validation.lowStockThreshold'),
  default_locale: z.enum(LANGUAGES),
  default_theme: z.enum(['light', 'dark']),
})

export type SettingsFormValues = z.infer<typeof settingsSchema>

export function settingsFormValues(settings: StoreSettings): SettingsFormValues {
  return {
    name: settings.name,
    contact_email: settings.contact_email ?? '',
    contact_phone: settings.contact_phone ?? '',
    address: settings.address ?? '',
    currency_code: settings.currency_code,
    currency_symbol: settings.currency_symbol,
    vat_rate: settings.vat_rate,
    deduction_stage: settings.deduction_stage,
    return_window_days: String(settings.return_window_days),
    low_stock_threshold: String(settings.low_stock_threshold),
    default_locale: settings.default_locale,
    default_theme: settings.default_theme,
  }
}

const optional = (value: string): string | null => value || null

export function toSettingsInput(values: SettingsFormValues): StoreSettingsInput {
  return {
    ...values,
    contact_email: optional(values.contact_email),
    contact_phone: optional(values.contact_phone),
    address: optional(values.address),
    return_window_days: Number(values.return_window_days),
    low_stock_threshold: Number(values.low_stock_threshold),
  }
}
