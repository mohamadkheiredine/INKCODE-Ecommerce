import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type { AuditAuthor, AuditEntry } from '../types'

export const auditKeys = {
  all: ['audit'] as const,
  list: (query: ListQuery) => [...auditKeys.all, 'list', query] as const,
  authors: () => [...auditKeys.all, 'authors'] as const,
}

/** The audit log is read-only: the API has no way to change an entry. */
export const auditApi = {
  async list(query: ListQuery): Promise<ListPage<AuditEntry>> {
    const { data } = await http.get<ListPage<AuditEntry>>('/audit-entries', {
      params: toApiParams(query),
    })
    return data
  },

  async authors(): Promise<AuditAuthor[]> {
    const { data } = await http.get<{ data: AuditAuthor[] }>('/audit-entries/users')
    return data.data
  },
}
