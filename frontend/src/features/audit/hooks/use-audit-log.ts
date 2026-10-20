import { keepPreviousData, useQuery } from '@tanstack/react-query'
import type { ListQuery } from '@/lib/list-query'
import { auditApi, auditKeys } from '../api/audit-api'

/** A page of the audit log; the previous page stays on screen while the next one loads. */
export function useAuditEntries(query: ListQuery) {
  return useQuery({
    queryKey: auditKeys.list(query),
    queryFn: () => auditApi.list(query),
    placeholderData: keepPreviousData,
  })
}

/** The Users who appear in the log, for the User filter. */
export function useAuditAuthors() {
  return useQuery({ queryKey: auditKeys.authors(), queryFn: () => auditApi.authors() })
}
