import { isApiError } from '@/lib/api/api-error'
import { ensureCsrfCookie, http } from '@/lib/api/client'
import type { CurrentUser, DemoAccount, SignInInput } from '../types'

/** The documented, local-only password shared by every demo account (see the README). */
export const DEMO_PASSWORD = 'password'

export const authKeys = {
  all: ['auth'] as const,
  currentUser: () => [...authKeys.all, 'current-user'] as const,
  demoAccounts: () => [...authKeys.all, 'demo-accounts'] as const,
}

export const authApi = {
  async currentUser(): Promise<CurrentUser> {
    const { data } = await http.get<{ data: CurrentUser }>('/auth/me')
    return data.data
  },

  async signIn(input: SignInInput): Promise<CurrentUser> {
    await ensureCsrfCookie()
    const { data } = await http.post<{ data: CurrentUser }>('/auth/login', input)
    return data.data
  },

  /** Signing out of a session that has already ended counts as signed out. */
  async signOut(): Promise<void> {
    try {
      await http.post('/auth/logout')
    } catch (error) {
      if (isApiError(error) && error.status === 401) return
      throw error
    }
  },

  /** Empty outside demo mode, where the endpoint answers 404 on purpose. */
  async demoAccounts(): Promise<DemoAccount[]> {
    try {
      const { data } = await http.get<{ data: DemoAccount[] }>('/auth/demo-accounts')
      return data.data
    } catch (error) {
      if (isApiError(error) && error.status === 404) return []
      throw error
    }
  },
}
