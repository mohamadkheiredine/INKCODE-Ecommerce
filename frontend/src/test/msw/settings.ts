import { HttpResponse, http } from 'msw/http'
import type { StoreProfile, StoreSettings, StoreSettingsInput } from '@/features/settings/types'
import { apiError } from './handlers'
import { server } from './server'

/** A new store's settings: the defaults the API's migration gives every store. */
export function aStoreSettings(overrides: Partial<StoreSettings> = {}): StoreSettings {
  return {
    name: 'Inkcode Demo Store',
    logo_url: null,
    contact_email: 'hello@inkcode-demo.test',
    contact_phone: null,
    address: null,
    currency_code: 'USD',
    currency_symbol: '$',
    vat_rate: '5.00',
    deduction_stage: 'shipped',
    return_window_days: 14,
    low_stock_threshold: 5,
    default_locale: 'en',
    default_theme: 'light',
    updated_at: '2026-10-20T09:00:00+00:00',
    ...overrides,
  }
}

export const toStoreProfile = (settings: StoreSettings): StoreProfile => ({
  name: settings.name,
  logo_url: settings.logo_url,
  contact_email: settings.contact_email,
  contact_phone: settings.contact_phone,
  address: settings.address,
  currency: { code: settings.currency_code, symbol: settings.currency_symbol },
})

/**
 * The Settings API, kept in memory: saving answers like the real one (VAT is kept to
 * two decimals, a rate over 100 is refused with 422), and the public store profile
 * always reflects what was saved. Every saved form is recorded.
 */
export function settingsStore(initial: StoreSettings = aStoreSettings()) {
  let settings = initial
  const saved: StoreSettingsInput[] = []

  server.use(
    http.get('*/api/v1/settings', () => HttpResponse.json({ data: settings })),
    http.put('*/api/v1/settings', async ({ request }) => {
      const input = (await request.json()) as StoreSettingsInput
      saved.push(input)
      if (Number(input.vat_rate) > 100) {
        return apiError(422, 'validation_failed', 'The VAT rate field must be between 0 and 100.', {
          vat_rate: ['The VAT rate field must be between 0 and 100.'],
        })
      }
      settings = { ...settings, ...input, vat_rate: Number(input.vat_rate).toFixed(2) }
      return HttpResponse.json({ data: settings })
    }),
    http.get('*/api/v1/store', () => HttpResponse.json({ data: toStoreProfile(settings) })),
  )

  return { saved, current: () => settings }
}
