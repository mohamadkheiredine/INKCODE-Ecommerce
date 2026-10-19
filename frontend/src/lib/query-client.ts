import { MutationCache, QueryCache, QueryClient, type QueryKey } from '@tanstack/react-query'
import { isApiError } from './api/api-error'

/** Errors where asking again cannot help: the answer will be the same. */
const FINAL_STATUSES = new Set([401, 403, 404, 409, 422, 429])

type QueryClientOptions = {
  /** Called whenever any query or mutation is answered 401 (the session has ended). */
  onUnauthenticated?: (client: QueryClient, failedQueryKey: QueryKey | undefined) => void
}

export function createQueryClient({ onUnauthenticated }: QueryClientOptions = {}): QueryClient {
  const reportUnauthenticated = (error: unknown, queryKey?: QueryKey) => {
    if (isApiError(error) && error.status === 401) onUnauthenticated?.(client, queryKey)
  }

  const client: QueryClient = new QueryClient({
    queryCache: new QueryCache({
      onError: (error, query) => reportUnauthenticated(error, query.queryKey),
    }),
    mutationCache: new MutationCache({ onError: (error) => reportUnauthenticated(error) }),
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        refetchOnWindowFocus: false,
        retry: (failureCount, error) =>
          !(isApiError(error) && FINAL_STATUSES.has(error.status)) && failureCount < 2,
      },
      mutations: { retry: false },
    },
  })

  return client
}
