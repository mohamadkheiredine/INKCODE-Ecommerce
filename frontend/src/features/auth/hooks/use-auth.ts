import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { authApi, authKeys } from '../api/auth-api'
import type { CurrentUser, SignInInput } from '../types'

export function useCurrentUser() {
  return useQuery({
    queryKey: authKeys.currentUser(),
    queryFn: authApi.currentUser,
    staleTime: 5 * 60_000,
    // Coming back to the tab re-checks the session, so a session that expired or was
    // ended in another tab sends the User to sign-in instead of failing later.
    refetchOnWindowFocus: 'always',
  })
}

export function useDemoAccounts() {
  return useQuery({
    queryKey: authKeys.demoAccounts(),
    queryFn: authApi.demoAccounts,
    staleTime: Infinity,
    retry: false,
  })
}

export function useSignIn() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (input: SignInInput) => authApi.signIn(input),
    onSuccess: (user: CurrentUser) => {
      queryClient.setQueryData(authKeys.currentUser(), user)
    },
  })
}

export function useSignOut() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: authApi.signOut,
    // Only once the server has ended the session: nothing cached for this User may
    // leak into the next session. A failed sign-out leaves everything as it was.
    onSuccess: () => queryClient.clear(),
  })
}
