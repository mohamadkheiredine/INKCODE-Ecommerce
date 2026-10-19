import { hashKey } from '@tanstack/react-query'
import { authKeys } from '@/features/auth/api/auth-api'
import { createQueryClient } from '@/lib/query-client'

/**
 * The app's query client. When any request reports that the session has ended, the
 * current User is re-checked, and the route guard sends the User to sign-in (and back).
 */
export function createAppQueryClient() {
  const currentUserKey = hashKey(authKeys.currentUser())

  return createQueryClient({
    onUnauthenticated: (client, failedQueryKey) => {
      // The guard already reacts to the current-User check itself failing.
      if (failedQueryKey && hashKey(failedQueryKey) === currentUserKey) return
      void client.resetQueries({ queryKey: authKeys.currentUser() })
    },
  })
}
