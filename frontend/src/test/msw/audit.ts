import { HttpResponse, http } from 'msw/http'
import type { AuditEntry } from '@/features/audit/types'
import { apiError } from './handlers'
import { listResponse } from './lists'
import { server } from './server'

let nextId = 900

export function anAuditEntry(overrides: Partial<AuditEntry> = {}): AuditEntry {
  return {
    id: nextId++,
    action: 'updated',
    record_type: 'brand',
    record_id: 3,
    record_label: 'Atlas Outdoor',
    before: { name: 'Atlas' },
    after: { name: 'Atlas Outdoor' },
    user: { id: 1, name: 'Ahmed Ali' },
    created_at: '2026-10-19T09:30:00+00:00',
    ...overrides,
  }
}

/** The audit log API over a fixed set of entries, or failing with a 500 when `fail` is set. */
export function auditStore(entries: AuditEntry[], { fail = false } = {}) {
  server.use(
    http.get('*/api/v1/audit-entries', ({ request }) =>
      fail
        ? apiError(500, 'server_error', 'Something went wrong.')
        : listResponse(entries, request, {
            searchIn: ['record_label'],
            filters: {
              record_type: (entry, value) => entry.record_type === value,
              action: (entry, value) => entry.action === value,
            },
            defaultSort: '-created_at',
          }),
    ),
    http.get('*/api/v1/audit-entries/users', () =>
      HttpResponse.json({ data: [{ id: 1, name: 'Ahmed Ali' }] }),
    ),
  )
}
