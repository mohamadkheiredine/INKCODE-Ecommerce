import { HttpResponse, http } from 'msw/http'
import type { CurrentUser } from '@/features/auth/types'
import type { PreferencesInput } from '@/features/profile/types'
import { notificationInbox } from './notifications'
import { server } from './server'
import { aStoreSettings, toStoreProfile } from './settings'

/**
 * A browser already signed in as this User, in a store with the default settings, with no
 * notifications yet (call `notificationInbox()` afterwards to give them some). Saved preferences are kept like the
 * API keeps them; every saved preference is recorded so tests can check what was sent.
 */
export function signedInAs(user: CurrentUser) {
  let current = user
  const savedPreferences: PreferencesInput[] = []

  notificationInbox()
  server.use(
    http.get('*/api/v1/store', () => HttpResponse.json({ data: toStoreProfile(aStoreSettings()) })),
    http.get('*/api/v1/auth/me', () => HttpResponse.json({ data: current })),
    http.patch('*/api/v1/auth/me/preferences', async ({ request }) => {
      const input = (await request.json()) as PreferencesInput
      savedPreferences.push(input)
      current = { ...current, preferences: { ...current.preferences, ...input } }
      return HttpResponse.json({ data: current })
    }),
  )

  return { savedPreferences }
}
