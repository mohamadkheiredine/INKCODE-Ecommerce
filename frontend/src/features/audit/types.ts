/** What a User did to a record, mirroring `App\Enums\AuditAction`. */
export const AUDIT_ACTIONS = ['created', 'updated', 'deleted', 'password_changed'] as const
export type AuditAction = (typeof AUDIT_ACTIONS)[number]

/** A record's values as the audit trail kept them: only the fields that changed. */
export type AuditValues = Record<string, unknown>

/** One recorded change; `user` is null for changes the system made. */
export type AuditEntry = {
  id: number
  action: AuditAction
  /** A stable type such as `brand` or `store_settings` (see record-types.ts). */
  record_type: string
  record_id: number
  /** The record's name as it was when the change was made. */
  record_label: string
  before: AuditValues | null
  after: AuditValues | null
  user: AuditAuthor | null
  created_at: string
}

export type AuditAuthor = { id: number; name: string }
