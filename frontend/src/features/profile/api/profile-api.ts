import type { CurrentUser } from '@/features/auth/types'
import { http } from '@/lib/api/client'
import type { ChangePasswordValues } from '../schemas/change-password-schema'
import type { PreferencesInput } from '../types'

export const profileApi = {
  /** Keeps the language and/or theme on the User's profile; answers with the updated User. */
  async savePreferences(input: PreferencesInput): Promise<CurrentUser> {
    const { data } = await http.patch<{ data: CurrentUser }>('/auth/me/preferences', input)
    return data.data
  },

  /** Ends the User's other sessions; this one stays signed in. */
  async changePassword(input: ChangePasswordValues): Promise<void> {
    await http.put('/auth/me/password', input)
  },
}
