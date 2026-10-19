import type { RouteObject } from 'react-router'
import { RequireAuth } from '@/features/auth/components/require-auth'
import { SIGN_IN_PATH } from '@/features/auth/redirect'

/** Route modules load lazily, so each page only ships when it is first visited. */
export const routes: RouteObject[] = [
  {
    path: SIGN_IN_PATH,
    lazy: async () => ({
      Component: (await import('@/features/auth/routes/sign-in-page')).SignInPage,
    }),
  },
  {
    Component: RequireAuth,
    children: [
      {
        index: true,
        lazy: async () => ({
          Component: (await import('@/features/auth/routes/home-page')).HomePage,
        }),
      },
      {
        path: '*',
        lazy: async () => ({ Component: (await import('./not-found-page')).NotFoundPage }),
      },
    ],
  },
]
