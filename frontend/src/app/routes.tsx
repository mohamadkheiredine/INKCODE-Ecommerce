import type { RouteObject } from 'react-router'
import { PageSkeleton } from '@/components/feedback/page-skeleton'
import { RequireAuth } from '@/features/auth/components/require-auth'
import { SIGN_IN_PATH } from '@/features/auth/redirect'
import type { RouteHandle } from '@/lib/navigation'

const crumb = (page: NonNullable<RouteHandle['crumb']>): RouteHandle => ({ crumb: page })

/**
 * Route modules load lazily, so each page only ships when it is first visited. Each
 * page names itself in `handle.crumb`; the shell builds breadcrumbs and titles from it.
 */
export const routes: RouteObject[] = [
  {
    path: SIGN_IN_PATH,
    HydrateFallback: () => <div className="min-h-svh bg-background" />,
    lazy: async () => ({
      Component: (await import('@/features/auth/routes/sign-in-page')).SignInPage,
    }),
  },
  {
    Component: RequireAuth,
    HydrateFallback: PageSkeleton,
    children: [
      {
        handle: crumb('home'),
        lazy: async () => ({ Component: (await import('./shell/app-shell')).AppShell }),
        children: [
          {
            index: true,
            lazy: async () => ({
              Component: (await import('@/features/home/routes/home-page')).HomePage,
            }),
          },
          {
            path: 'profile',
            handle: crumb('profile'),
            lazy: async () => ({
              Component: (await import('@/features/profile/routes/profile-page')).ProfilePage,
            }),
          },
          {
            path: '*',
            handle: crumb('notFound'),
            lazy: async () => ({ Component: (await import('./not-found-page')).NotFoundPage }),
          },
        ],
      },
    ],
  },
]
