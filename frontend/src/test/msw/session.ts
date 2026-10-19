import { HttpResponse, http } from 'msw/http'
import type { CurrentUser } from '@/features/auth/types'
import type { PreferencesInput } from '@/features/profile/types'
import { server } from './server'

/**
 * A browser already signed in as this User. Saved preferences are kept like the API
 * keeps them; every saved preference is recorded so tests can check what was sent.
 */
export function signedInAs(user: CurrentUser) {
  let current = user
  const savedPreferences: PreferencesInput[] = []

  server.use(
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
