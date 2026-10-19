import { Navigate, Outlet, useLocation } from 'react-router'
import { ErrorState } from '@/components/feedback/error-state'
import { PageSkeleton } from '@/components/feedback/page-skeleton'
import { isApiError } from '@/lib/api/api-error'
import { useCurrentUser } from '../hooks/use-auth'
import { signInPathFor } from '../redirect'

/**
 * Guards every app page: signed-out visitors go to sign-in and come back to the
 * page they asked for. The API stays the real guard; this only shapes navigation.
 */
export function RequireAuth() {
  const currentUser = useCurrentUser()
  const location = useLocation()

  if (currentUser.isPending) return <PageSkeleton />

  if (currentUser.isError) {
    if (isApiError(currentUser.error) && currentUser.error.status === 401) {
      return <Navigate to={signInPathFor(location.pathname + location.search)} replace />
    }
    return (
      <div className="grid min-h-svh place-items-center bg-background p-6">
        <ErrorState onRetry={() => void currentUser.refetch()} retrying={currentUser.isFetching} />
      </div>
    )
  }

  return <Outlet />
}
