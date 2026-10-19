import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { settingsApi, settingsKeys } from '../api/settings-api'
import type { StoreSettings, StoreSettingsInput } from '../types'

/** The store profile changes rarely; screens share one copy for a few minutes. */
const STORE_PROFILE_STALE_MS = 5 * 60 * 1000

export function useStoreSettings() {
  return useQuery({ queryKey: settingsKeys.detail(), queryFn: settingsApi.get })
}

/** The public store profile (name, logo, Store currency) every screen may show. */
export function useStoreProfile() {
  return useQuery({
    queryKey: settingsKeys.storeProfile(),
    queryFn: settingsApi.storeProfile,
    staleTime: STORE_PROFILE_STALE_MS,
  })
}

/** Keeps the saved settings as the cached ones, and refreshes what every screen shows. */
function useApplySavedSettings() {
  const queryClient = useQueryClient()
  return (settings: StoreSettings) => {
    queryClient.setQueryData(settingsKeys.detail(), settings)
    return queryClient.invalidateQueries({ queryKey: settingsKeys.storeProfile() })
  }
}

export function useSaveStoreSettings() {
  const applySaved = useApplySavedSettings()
  return useMutation({
    mutationFn: (input: StoreSettingsInput) => settingsApi.update(input),
    onSuccess: applySaved,
  })
}

/** Uploads a new store logo (a File), or removes it (null). */
export function useChangeStoreLogo() {
  const applySaved = useApplySavedSettings()
  return useMutation({
    mutationFn: (logo: File | null) =>
      logo ? settingsApi.uploadLogo(logo) : settingsApi.removeLogo(),
    onSuccess: applySaved,
  })
}
