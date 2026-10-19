import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useApplyPreferences } from '@/components/preferences/use-apply-preferences'
import { authKeys } from '@/features/auth/api/auth-api'
import type { CurrentUser } from '@/features/auth/types'
import { profileApi } from '../api/profile-api'
import type { ChangePasswordValues } from '../schemas/change-password-schema'
import type { PreferencesInput } from '../types'

/**
 * Keeps a preference the User just applied on their profile, so it follows them to any
 * device. Says so either way; the cached User becomes the server's answer.
 */
export function useSavePreferences(): (input: PreferencesInput) => void {
  const queryClient = useQueryClient()
  const { i18n } = useTranslation()
  const { mutate } = useMutation({
    mutationFn: (input: PreferencesInput) => profileApi.savePreferences(input),
    onSuccess: (user) => queryClient.setQueryData(authKeys.currentUser(), user),
  })

  return useCallback(
    (input: PreferencesInput) =>
      mutate(input, {
        // i18n.t, not t: after a language switch the message is in the new language.
        onSuccess: () => toast.success(i18n.t('profile:preferences.saved')),
        onError: () => toast.error(i18n.t('profile:preferences.notSaved')),
      }),
    [mutate, i18n],
  )
}

export function useChangePassword() {
  return useMutation({
    mutationFn: (input: ChangePasswordValues) => profileApi.changePassword(input),
  })
}

/**
 * When a User's session starts in this browser, the language and theme saved on their
 * profile apply, whatever this browser used before. Later changes are the User's own
 * choices, so this runs once per User, not on every refresh of their details.
 */
export function useApplySavedPreferences(user: CurrentUser) {
  const applyPreferences = useApplyPreferences()
  const appliedFor = useRef<number | null>(null)

  useEffect(() => {
    if (appliedFor.current === user.id) return
    appliedFor.current = user.id
    applyPreferences(user.preferences)
  }, [user.id, user.preferences, applyPreferences])
}
