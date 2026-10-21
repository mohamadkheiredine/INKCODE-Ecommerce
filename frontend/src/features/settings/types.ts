import type { Theme } from '@/components/theme/theme-context'
import type { StoreCurrency } from '@/lib/format'
import type { Language } from '@/lib/i18n'

/** The Order statuses stock can be deducted at (`App\Enums\DeductionStage`), in flow order. */
export const DEDUCTION_STAGES = ['preparing', 'ready_for_shipping', 'shipped'] as const
export type DeductionStage = (typeof DEDUCTION_STAGES)[number]

/** Every setting, as the API sends it to the Admin. */
export type StoreSettings = {
  name: string
  logo_url: string | null
  contact_email: string | null
  contact_phone: string | null
  address: string | null
  currency_code: string
  currency_symbol: string
  /** A percentage as a decimal string ("5.00"). */
  vat_rate: string
  deduction_stage: DeductionStage
  return_window_days: number
  low_stock_threshold: number
  default_locale: Language
  default_theme: Theme
  updated_at: string | null
}

/** What the settings form sends; the logo travels separately (multipart). */
export type StoreSettingsInput = Omit<StoreSettings, 'logo_url' | 'updated_at'>

/** The public part of the settings, which every signed-in User's screens use. */
export type StoreProfile = {
  name: string
  logo_url: string | null
  contact_email: string | null
  contact_phone: string | null
  address: string | null
  currency: StoreCurrency
}
