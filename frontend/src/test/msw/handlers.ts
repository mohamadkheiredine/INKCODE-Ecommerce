import { HttpResponse, http } from 'msw/http'
import type { CurrentUser, DemoAccount } from '@/features/auth/types'

/** Error bodies exactly as the API's envelope shapes them. */
export function apiError(
  status: number,
  code: string,
  message: string,
  errors?: Record<string, string[]>,
) {
  return HttpResponse.json({ message, code, ...(errors ? { errors } : {}) }, { status })
}

export const adminUser: CurrentUser = {
  id: 1,
  name: 'Ahmed Ali',
  email: 'admin@inkcode.test',
  role: 'admin',
  permissions: ['dashboard.view', 'users.manage'],
  preferences: { locale: 'en', theme: 'light' },
}

export const viewerUser: CurrentUser = {
  id: 7,
  name: 'Youssef Nasser',
  email: 'viewer@inkcode.test',
  role: 'viewer',
  permissions: ['catalog.view', 'dashboard.view', 'orders.view'],
  preferences: { locale: 'en', theme: 'light' },
}

export const demoAccounts: DemoAccount[] = [
  { role: 'admin', name: 'Ahmed Ali', email: 'admin@inkcode.test' },
  { role: 'operations', name: 'Rania Khoury', email: 'operations@inkcode.test' },
  { role: 'inventory', name: 'Omar Haddad', email: 'inventory@inkcode.test' },
  { role: 'sales', name: 'Lina Saleh', email: 'sales@inkcode.test' },
  { role: 'support', name: 'Karim Mansour', email: 'support@inkcode.test' },
  { role: 'finance', name: 'Nadia Farouk', email: 'finance@inkcode.test' },
  { role: 'viewer', name: 'Youssef Nasser', email: 'viewer@inkcode.test' },
]

/** Default network: a signed-out visitor, outside demo mode. Tests override per case. */
export const handlers = [
  http.get('*/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 })),
  http.get('*/api/v1/auth/me', () => apiError(401, 'unauthenticated', 'Unauthenticated.')),
  http.get('*/api/v1/auth/demo-accounts', () =>
    apiError(404, 'not_found', 'The requested resource was not found.'),
  ),
  http.post('*/api/v1/auth/logout', () => new HttpResponse(null, { status: 204 })),
]
