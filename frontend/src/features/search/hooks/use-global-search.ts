import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { MIN_SEARCH_LENGTH, searchApi, searchKeys } from '../api/search-api'

/** How long typing must pause before the search is sent. */
const DEBOUNCE_MS = 250

/** The search for what the User typed, once they pause; idle below the minimum length. */
export function useGlobalSearch(input: string) {
  const [q, setQ] = useState(input.trim())

  useEffect(() => {
    const timer = setTimeout(() => setQ(input.trim()), DEBOUNCE_MS)
    return () => clearTimeout(timer)
  }, [input])

  const enabled = q.length >= MIN_SEARCH_LENGTH
  const query = useQuery({
    queryKey: searchKeys.results(q),
    queryFn: ({ signal }) => searchApi.search(q, signal),
    enabled,
    placeholderData: keepPreviousData,
    staleTime: 30_000,
  })

  return { ...query, enabled, q, settling: input.trim() !== q }
}
