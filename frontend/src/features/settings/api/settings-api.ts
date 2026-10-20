import { http } from '@/lib/api/client'
import type { StoreProfile, StoreSettings, StoreSettingsInput } from '../types'

export const settingsKeys = {
  all: ['settings'] as const,
  detail: () => [...settingsKeys.all, 'detail'] as const,
  storeProfile: () => [...settingsKeys.all, 'store-profile'] as const,
}

export const settingsApi = {
  /** Every setting; the Admin's only (settings.manage). */
  async get(): Promise<StoreSettings> {
    const { data } = await http.get<{ data: StoreSettings }>('/settings')
    return data.data
  },

  async update(input: StoreSettingsInput): Promise<StoreSettings> {
    const { data } = await http.put<{ data: StoreSettings }>('/settings', input)
    return data.data
  },

  async uploadLogo(logo: File): Promise<StoreSettings> {
    const body = new FormData()
    body.append('logo', logo)
    const { data } = await http.post<{ data: StoreSettings }>('/settings/logo', body)
    return data.data
  },

  async removeLogo(): Promise<StoreSettings> {
    const { data } = await http.delete<{ data: StoreSettings }>('/settings/logo')
    return data.data
  },

  /** The store name, logo and Store currency; every signed-in User may read them. */
  async storeProfile(): Promise<StoreProfile> {
    const { data } = await http.get<{ data: StoreProfile }>('/store')
    return data.data
  },
}
